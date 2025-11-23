<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Response;

class Sc01Controller extends AppController
{
    /**
     * Minimal index action: redirect to Sc02 index with original query params.
     * This is a safe temporary shim to avoid MissingControllerException while
     * the original Sc01 implementation is not present.
     */
    public function index(): ?Response
    {
        $query = $this->getRequest()->getQueryParams();
        return $this->redirect([
            'controller' => 'Sc02',
            'action' => 'index',
            '?' => $query,
        ]);
    }
}
