<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class MCentersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('m_centers');
        $this->setPrimaryKey('id');
    }

    //接続先DB指定
    public static function defaultConnectionName(): string
    {
        return 'manso_wms';
    }

    /**
     * プルダウン用センター一覧
     */
    public function getCenterOptions(): array
    {
        return $this->find()
            ->select(['id', 'name'])
            ->order(['id' => 'ASC'])
            ->enableHydration(false)
            ->combine('id', 'name')
            ->toArray();
    }
}
