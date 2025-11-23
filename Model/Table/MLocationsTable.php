<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\ORM\Query; // Cake\ORM\Query\SelectQuery から変更済み

class MLocationsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('m_locations');
        $this->setPrimaryKey('id');

        // エリア（center）をJOINできるようにする
        $this->belongsTo('MCenters', [
            'foreignKey' => 'center_id',
            'joinType'   => 'LEFT'
        ]);
    }

    //接続先DB指定
    public static function defaultConnectionName(): string
    {
        return 'manso_wms';
    }

    /**
     * 有効な全店舗を取得（type=2 かつ disable_flg=0）
     */
    public function getActiveStores(): Query 
    {
        return $this->find()
            // 修正点: 後続ロジックで使用する列を明示的に取得。store_id の代わりに no を含める
            ->select(['id', 'center_id', 'no', 'name']) 
            ->where([
                'type' => 2,
                'disable_flg' => 0,
                'name LIKE' => '%店%',
            ]);
    }

    /**
     * センターIDに基づいて店番のリストを取得します。
     *
     * @param int|null $centerId センターID。nullの場合は空の配列を返します。
     * @return array 店番の配列。
     */
    public function getStoreIdsByCenter(?int $centerId): array
    {
        if ($centerId === null) {
            return [];
        }

        return $this->getActiveStores()
            ->where(['center_id' => $centerId])
            // 修正点: store_id を no に変更
            ->order(['no' => 'ASC'])
            ->all()
            // 修正点: store_id を no に変更
            ->extract('no')
            ->toArray();
    }

    /**
     * ロケーションIDに基づいて、そのロケーションが所属するセンターの全店舗IDを取得します。
     *
     * @param int|null $locationId ロケーションID。nullの場合は空の配列を返します。
     * @return array 店番の配列。
     */
    public function getStoreIdsByLocation(?int $locationId): array
    {
        // location_id が指定されていない場合は、有効な全店舗のIDを返す
        if ($locationId === null) {
            return $this->getActiveStores()
                // 修正点: store_id を no に変更
                ->find('list', ['keyField' => 'id', 'valueField' => 'no'])
                // 修正点: store_id を no に変更
                ->order(['no' => 'ASC'])
                ->toArray();
        }

        // location_id から center_id を特定
        $location = $this->find()
            // 修正点: store_id を no に変更
            ->select(['center_id', 'no'])
            ->where(['id' => $locationId, 'deleted' => 0])
            ->first();

        if (!$location || !$location->center_id) {
            return [];
        }

        return $this->getStoreIdsByCenter($location->center_id);
    }
}