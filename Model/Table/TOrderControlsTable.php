<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;

class TOrderControlsTable extends Table
{
    public function initialize(array $config): void
    {
        // 常に 'manso_eos' データソース（または 'default'）を使用するように設定します。
        // これにより、他のモデルから呼び出された場合でも、
        // 正しいデータベースに接続されることが保証されます。
        $this->setConnection(
            \Cake\Datasource\ConnectionManager::get('manso_eos')
        );
        parent::initialize($config);

        // テーブル名
        $this->setTable('t_order_controls');

        // 主キー
        $this->setPrimaryKey('id');
    }

    //接続先DB指定
    public static function defaultConnectionName(): string
    {
        return 'manso_wms';
    }
}