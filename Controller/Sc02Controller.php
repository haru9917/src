<?php
declare(strict_types=1);

namespace App\Controller;

class Sc02Controller extends AppController
{
    public function initialize(): void
    {
        parent::initialize();

        // モデルをロード
        $this->loadModel('MLocations');
        $this->loadModel('MCenters');
        $this->loadModel('TStoreOrders'); // 'TStoreOrder' から 'TStoreOrders' に修正
        $this->loadModel('TOrderControls');
    }

    public function index()
    {
        $center_id = $this->request->getQuery('center_id');
        $location_id = $this->request->getQuery('location_id');

        $center_id = ($center_id === null || $center_id === '') ? null : (int)$center_id;
        $location_id = ($location_id === null || $location_id === '') ? null : (int)$location_id;

        $service = new \App\Service\Sc02OrderSummaryService();
        $data = $service->getSummary($center_id, $location_id);


        // 渡された配列をそのまま view にセット（orderFlag も含む）
        $this->set($data);
    }

    /**
     * Sc03(店舗発注一覧)へのリダイレクト処理
     *
     * @return \Cake\Http\Response|null
     */
    public function list()
    {
        return $this->redirect([
            'controller' => 'Sc03Orders',
            'action' => 'orderslist',
            '?' => $this->request->getQueryParams(),
        ]);
    }

    public function download()
    {
        $center_id = $this->request->getQuery('center_id');
        $location_id = $this->request->getQuery('location_id');
        $order_control_id = $this->request->getQuery('order_control_id');
        $requestedDate = (string)($this->request->getData('date') ?? '');

        $center_id = ($center_id === null || $center_id === '') ? null : (int)$center_id;
        $location_id = ($location_id === null || $location_id === '') ? null : (int)$location_id;

        $service = new \App\Service\Sc02OrderSummaryService();
        $qs = $this->request->getQueryParams();

        // 過去データダウンロード（POST）
        if ($this->request->is('post')) {
            if ($requestedDate === '') {
                $this->Flash->error('日付を入力してください。');
                return $this->redirect(['action' => 'index', '?' => $qs]);
            }

            $result = $service->exportPastCsv($requestedDate, $center_id, $location_id);
            if (($result['order_control_id'] ?? null) === null) {
                $this->Flash->error('指定日のデータが見つかりません。');
                return $this->redirect(['action' => 'index', '?' => $qs]);
            }

            $csv = $result['csv'] ?? '';
            $businessDate = $result['business_date'] ?? $requestedDate;
            $centerIdPart = $center_id ? '_C' . $center_id : '';
            $filename = sprintf('orders_%s%s.csv', str_replace('-', '', $businessDate), $centerIdPart);

            $this->response = $this->response->withType('csv')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withStringBody($csv);

            return $this->response;
        }
        // 優先: order_control_id が渡されていればそれを使う
        $orderFlag = null;
        if ($order_control_id !== null && $order_control_id !== '') {
            $orderFlag = $service->getOrderFlag((int)$order_control_id, null, null);
        } else {
            $orderFlag = $service->getOrderFlag(null, $center_id, $location_id);
        }

        if ($orderFlag === '0') {
            // 例外を投げず、フラッシュを出して一覧へリダイレクト
            $this->Flash->error('発注未済のためダウンロードできません。');
            return $this->redirect(['action' => 'index', '?' => $qs]);
        }

        // 実際のCSV生成: order_control_id が必須
        if ($order_control_id === null || $order_control_id === '') {
            $this->Flash->error('ダウンロードに必要な order_control_id が指定されていません。');
            return $this->redirect(['action' => 'index', '?' => $qs]);
        }

        $result = $service->exportCsv((string)$order_control_id);
        $csv = $result['csv'] ?? '';
        $businessDate = $result['business_date'] ?? date('Y-m-d');
        $centerIdPart = $center_id ? '_C' . $center_id : '';
        $filename = sprintf('orders_%s%s.csv', str_replace('-', '', $businessDate), $centerIdPart);

        $this->response = $this->response->withType('csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withStringBody($csv);

        return $this->response;
    }

    /**
     * 発注締め処理を行うアクション
     * クエリパラメータ order_control_id を受け取り、該当レコードの order_flag を '1' に更新します。
     */
    public function toggleFlag()
    {
        // Require POST requests to perform state-changing action
        if (!$this->request->is('post')) {
            $this->Flash->error('発注締め処理はボタン押下時のみ実行できます。');
            $qs = $this->request->getQueryParams();
            return $this->redirect(['action' => 'index', '?' => $qs]);
        }

        $order_control_id = $this->request->getData('order_control_id')
            ?? $this->request->getQuery('order_control_id');
        $qs = array_filter(
            $this->request->getQueryParams(),
            static fn($v) => $v !== null && $v !== ''
        );
        $redirectUrl = ['action' => 'index', '?' => $qs];

        if ($order_control_id === null || $order_control_id === '') {
            $this->Flash->error('order_control_id が指定されていません。');
            return $this->redirect($redirectUrl);
        }

        $table = $this->TOrderControls;
        $entity = $table->find()->where(['id' => $order_control_id])->first();
        if (!$entity) {
            $this->Flash->error('対象の発注コントロールが見つかりません。');
            return $this->redirect($redirectUrl);
        }

        // 既に締め済みなら成功扱いで戻る
        if ((string)$entity->order_flag === '1') {
            $this->Flash->success('発注締め処理は既に完了しています。');
            return $this->redirect($redirectUrl);
        }

        $entity->order_flag = '1';
        if ($table->save($entity)) {
            $this->Flash->success('発注締め処理を完了しました。');
        } else {
            $this->Flash->error('発注締め処理に失敗しました。');
        }

        return $this->redirect($redirectUrl);
    }
}
