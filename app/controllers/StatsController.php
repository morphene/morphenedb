<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\UTCDateTime;

class StatsController extends ControllerBase
{

  public function indexAction()
  {
    $this->view->props = $props = $this->morphened->getProps();
    $this->view->totals = $totals = $this->util->distribution($props);
    var_dump($totals); exit;
  }

}
