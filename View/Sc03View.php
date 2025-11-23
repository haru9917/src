<?php
namespace App\View;

use App\View\AppView;

/**
 * Sc03-specific view class.
 * Place view-specific helpers or methods here for views under templates/Sc03.
 */
class Sc03View extends AppView
{
    public function initialize(): void
    {
        parent::initialize();
        // 必要なら helpers の追加やビュー設定をここで行う
        // $this->loadHelper('SomeHelper');
    }
}
