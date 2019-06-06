<?php

use MongoDB\BSON\UTCDateTime;

class Utilities
{

  public function __construct($di) {
    $this->di = $di;
  }

  public function distribution($props) {
    $cacheKey = 'distribution-30d';
    $cached = $this->di->get('memcached')->get($cacheKey);
    if($cached !== null) {
      return $cached;
    }
    $totals = [
      'witnesses' => $this->di->get('convert')->sp2vest($props['current_supply'] * 0.095 * 0.1 / 12, false),
    ];
    // Set Date Range
    $start = new UTCDateTime(strtotime("-30 days") * 1000);
    $end = new UTCDateTime(strtotime("midnight") * 1000);
    $this->di->get('memcached')->save($cacheKey, $totals, 60);
    return $totals;
  }

}
