<?php
namespace App\Controller;
use App\Controller\AppController;

class Sc03OrdersController extends AppController
{
    /**
     * 発注リスト表示
     */
    public function orderslist()
    {
        // === モデルをロード ===
        $this->loadModel('MCenters');
        $this->loadModel('MLocations');
        $this->loadModel('TDemandRows');
        $this->loadModel('TOrderControls');

        // === GETパラメータの取得 ===
        $productName = $this->request->getQuery('product_name');
        $centerId = $this->request->getQuery('center_id');
        $locationId = $this->request->getQuery('location_id');

        // === 店舗IDの取得ロジック ===
        if ($centerId) {
            $storeIds = $this->MLocations->getStoreIdsByCenter((int)$centerId);
        } else {
            // センターIDが指定されていない場合は、すべての店舗IDを取得
            $storeIds = $this->MLocations->getStoreIdsByLocation(null);
        }

        // === ストアIDの正規化 ===
        $storeIds = $storeIds ?? []; 
        $invalid = array_filter($storeIds, function($v) { return !(is_scalar($v) || (is_object($v) && method_exists($v, '__toString'))); });
        if (!empty($invalid)) {
            \Cake\Log\Log::warning('orderslist: getStoreIdsByCenter returned non-scalar storeIds: ' . var_export($invalid, true));
        }
        $storeIds = array_map(function($v){ return is_object($v) && method_exists($v, '__toString') ? (string)$v : (string)$v; }, $storeIds);

        // === データ取得とViewへのセット ===
        $viewData = $this->TDemandRows->getOrdersListData($productName, $storeIds);
        $centers = $this->MCenters->find('list')->toArray();

        // === 発注制御フラグの取得 ===
        $isOrderable = false;
        if ($centerId) {
            $orderControl = $this->TOrderControls->find()
                ->where(['center_id' => $centerId, 'order_flag' => 1])
                ->first();
            $isOrderable = ($orderControl !== null);
        }
        // 【デバッグコード】取得したデータを行数を確認 (そのまま残します)
        if (!empty($viewData['demandRows'])) {
            \Cake\Log\Log::info('Demand rows count: ' . count($viewData['demandRows']), ['orders']);
        } else {
            \Cake\Log\Log::warning('Demand rows is empty. Store IDs used: ' . var_export($storeIds, true), ['orders']);
        }

        $this->set($viewData);
        $this->set(compact('storeIds'));
        $this->set('products', $viewData['products'] ?? []);
        $this->set(compact('isOrderable'));
        $this->set(compact('centers'));

        // === Ajax処理 ===
        if ($this->request->is('ajax')) {
            $this->viewBuilder()->disableAutoLayout();
            // Ajaxリクエストは常にテーブル全体を返すテンプレートをレンダリングする
            return $this->render('/Sc03/order_list_ajax_update');
        }

        // ビューレンダリング
        return $this->render('/Sc03/Orderslist'); 
    }
}