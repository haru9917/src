<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');
    }

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);

        // ① GET パラメータから店番を取得
        //    - ブラウザから渡される location_id を取得してローカル変数に格納します。
        //    - その後リクエスト属性にもセットし、他のコントローラやビューで利用できるようにします。
        $location_id = $this->request->getQuery('location_id');

        // ② store_no が無い場合はエラー画面へ（任意）
        //    - location_id が空の場合は処理を続けず、エラーページへリダイレクトします。
        // 一部アクション（例: toggleFlag）は location_id が無くても処理させたい
        $action = $this->request->getParam('action');

        // toggleFlag は UI の確認ダイアログ経由で呼ばれるため、
        // location_id が無くても処理を許可する。
        // 注意: ルーティングによってはアクション名が 'toggle-flag' のように
        // ハイフン区切りに変換される場合があるため両方を受け入れる。
        if ($action === 'toggleFlag' || $action === 'toggle-flag') {
            // request attribute は設定しておく（null でも OK）
            $this->request = $this->request->withAttribute('location_id', $location_id);
            return;
        }

        if (empty($location_id)) {
            return $this->redirect([
                'controller' => 'Error',
                'action' => 'noStore',
            ]);
        }

        // ③ リクエストに店番属性をセット（コントローラでも使える）
        //    - withAttribute を使ってリクエストに location_id を埋め込みます。
        //    - これにより、他の処理中に $this->request->getAttribute('location_id') で取得可能になります。
        $this->request = $this->request->withAttribute('location_id', $location_id);

        // ④ 本部判定
        //    - location_id が '1' の場合を本部ユーザとみなし、それ以外は店舗ユーザとみなします。
        $isHeadOffice = ($location_id === '1');

        // ⑤ 現在のコントローラ名を取得
        //    - 現在処理中のコントローラ名を取得して、リダイレクト要否の判定に用います。
        $current = $this->request->getParam('controller');
        
        // ⑥ 店舗ユーザ → Sc01 へ誘導
        //    - 本部ユーザでない場合（店舗ユーザ）は Sc01 コントローラへ誘導します。
        //    - ただし既に Sc01 を呼んでいる場合は無限リダイレクトを避けるためスキップします。
        if (!$isHeadOffice && $current !== 'Sc01') {
            return $this->redirect([
                'controller' => 'Sc01',
                'action' => 'index',
                '?' => ['location_id' => $location_id]
            ]);
        }

        // ⑦ 本部ユーザ → Sc02 または Sc03Orders へ誘導
        //    - location_id が本部を示す場合は、アクセスが許可されているコントローラ（Sc02, Sc03Orders）の
        //      いずれかではない場合にのみ Sc02 へリダイレクトします。
        // 【修正点】Sc03Orders を許可されたコントローラリストに追加
        $allowedHeadOfficeControllers = ['Sc02', 'Sc03Orders'];
        
        if ($isHeadOffice && !in_array($current, $allowedHeadOfficeControllers)) {
             return $this->redirect([
                 'controller' => 'Sc02',
                 'action' => 'index',
                 '?' => ['location_id' => $location_id]
             ]);
        }
        
        // 旧ロジック: if ($isHeadOffice && $current !== 'Sc02') { ... } 
        // 置き換え済み
    }

}