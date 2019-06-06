<?php
namespace MorpheneDB\Controllers;

use MorpheneDB\Models\Status;

class IndexController extends ControllerBase
{
  public function indexAction()
  {
    return $this->response->redirect('/');
  }
  public function liveAction()
  {

  }

  public function homepageAction() {
    $this->view->props = $props = $this->morphened->getProps();
    $this->view->inflation = round(max((1500 - $props['head_block_number'] / 98000), 95) / 100, 4);
    $this->view->totals = $totals = $this->util->distribution($props);
    # Transactions
    $tx = $results = Status::findFirst([['_id' => 'transactions-24h']]);
    $tx1h = Status::findFirst([['_id' => 'transactions-1h']]);
    $this->view->tx = $tx->data;
    $this->view->tx_per_sec = round(count($tx->data) / 86400, 3);
    $this->view->tx1h = count($tx1h->data);
    $this->view->tx1h_per_sec = round($tx1h / 3600, 3);
    # Operations
    $op = $results = Status::findFirst([['_id' => 'operations-24h']]);
    $op1h = Status::findFirst([['_id' => 'operations-1h']]);
    $this->view->op = $op->data;
    $this->view->op_per_sec = round(count($op->data) / 86400, 3);
    $this->view->op1h = count($op1h->data);
    $this->view->op1h_per_sec = round($op1h / 3600, 3);
  }

  public function show404Action() {

  }
}
