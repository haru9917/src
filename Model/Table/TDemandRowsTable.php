<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

/**
 * TDemandRows Model
 */
class TDemandRowsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('t_demand_rows'); // 使用するテーブル名
        $this->setPrimaryKey('id');      // プライマリキー
    }

    /**
     * このモデルが使用するデータベース接続名を返します。
     *
     * @return string
     */
    public static function defaultConnectionName(): string
    {
        return 'manso_eos'; // 'manso_eos' データベース用の接続名を指定
    }

    /**
     * 発注リスト画面用のデータを取得する (ページネーション対応)
     *
     * @param string|null $productName 検索する商品名
     * @param array $storeIds 店番の配列 (センターIDからControllerで取得済み)
     * @return array
     */
    public function getOrdersListData(?string $productName = null, array $storeIds = []): array
    {
        // ★修正: $centerIdを受け取る代わりに、$storeIdsを受け取るように変更。
        // ★以前の centerId を使った店番取得ロジックは削除します。

        // 指定センター無し、または取得できなかった場合のフォールバック（既存のダミーを維持）
        if (empty($storeIds)) {
            $storeIds = [101, 102, 103, 104, 105]; //
        }

        // DBからユニークな商品データを取得 (hinmei, irisu)
        $query = $this->find()
            ->select(['hinmei', 'irisu'])
            ->distinct(['hinmei', 'irisu'])
            ->order(['hinmei' => 'ASC']);

        if ($productName) {
            $query->where(['hinmei LIKE' => '%' . $productName . '%']);
        }

        $allDbProducts = $query->all()->toArray();
        $totalProducts = count($allDbProducts);

        // ダミーの発注データ (この部分は固定)
        // ★修正: 全商品数に基づいてダミーデータを生成する
        $storeOrdersByProduct = [];
        $totalRows = $totalProducts * 4; // 1商品あたり4便
        for ($rowId = 1; $rowId <= $totalRows; $rowId++) {
            $dummyOrders = [];
            foreach ($storeIds as $storeId) {
                // IDとストアIDに基づいて適当な値を生成
                $dummyOrders[$storeId] = (($rowId + $storeId) % 10) + 1;
            }
            $storeOrdersByProduct[$rowId] = $dummyOrders;
        }

        // 画面表示用のデータ構造を生成
        $products = [];
        $demandRows = [];
        $rowId = 1; // 画面上の各行を識別するためのユニークなID

        foreach ($allDbProducts as $product) {
            $productDemands = []; // 商品ごとのdemandを格納する一時配列
            // 商品ごとに4つの出荷便を生成
            for ($i = 1; $i <= 4; $i++) {
                $orderKey = $rowId; // rowId をそのまま orderKey として使用
                $orders = $storeOrdersByProduct[$orderKey] ?? [];
                $totalOrderQty = empty($orders) ? 0 : array_sum($orders); // 発注総数を計算

                $demand = [
                    'id' => $orderKey,
                    'hinmei' => $product->hinmei,
                    'irisu' => $product->irisu,
                    'shipping_flight_number' => "{$i}便",
                    'total_order_qty' => $totalOrderQty,
                ];
                $productDemands[] = (object)$demand;
                $rowId++;
            }
            $products[] = ['hinmei' => $product->hinmei, 'demands' => $productDemands];
            $demandRows = array_merge($demandRows, $productDemands); // 全体のdemandRowsにも追加
        }

        return [
            'products' => $products, // ページごとに切り出された商品リスト
            'demandRows' => $demandRows,
            'storeIds' => $storeIds,
            'storeOrdersByProduct' => $storeOrdersByProduct,
        ];
    }
}