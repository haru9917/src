<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\Query\SelectQuery;

class TStoreOrdersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('t_store_orders');
        $this->setPrimaryKey(['tencd', 'shohincd', 'bin', 'hatymd']);
    }

    //接続先DB指定
    public static function defaultConnectionName(): string
    {
        return 'manso_eos';
    }


    /**
     * 指定営業日の発注済店舗一覧を取得（distinct）
     */
    public function getCompletedStoreIds(string $businessDate): array
    {
        return $this->find()
            ->select(['tencd'])
            ->where(['hatymd' => $businessDate])
            ->distinct(['tencd'])
            ->enableHydration(false)
            ->extract('tencd')
            ->toArray();
    }

    /**
     * 過去データダウンロード用
     */
    public function getOrdersByDate(string $date): SelectQuery
    {
        return $this->find()
            ->where(['hatymd' => $date]);
    }

    /**
     * 発注済店舗数
     */
    public function countCompletedStores(string $businessDate): int
    {
        return count($this->getCompletedStoreIds($businessDate));
    }
}
