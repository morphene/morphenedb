<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\ObjectID;

use MorpheneDB\Models\Witness;
use MorpheneDB\Models\WitnessMiss;
use MorpheneDB\Models\WitnessVote;
use MorpheneDB\Models\Statistics;

class WitnessController extends ControllerBase
{
  public function listAction()
  {
    $this->view->queue = Statistics::findFirst(array(
      array(
        "key" => "miner_queue"
      )
    ));
    $witnesses = Witness::agg(array(
      [
        '$sort' => [
          'votes' => -1
        ]
      ],
      [
        '$limit' => 100
      ],
      [
        '$lookup' => [
          'from' => 'account',
          'localField' => 'owner',
          'foreignField' => 'name',
          'as' => 'account',
        ]
      ],
    ))->toArray();
    $pipeline = [
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-7 days") * 1000)
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => '$witness',
          'total' => [
            '$sum' => '$increase'
          ]
        ]
      ]
    ];
    $agg = WitnessMiss::agg($pipeline)->toArray();
    foreach($agg as $data) {
      $misses[$data['_id']] = $data['total'];
    }
    foreach($witnesses as $index => $witness) {
      // Highlight Green for top 19
      if($index < 20) {
        $witness->row_status = "positive";
      }
      // Highlight Red if the signing key is invalid
      if(!$witness->signing_key || $witness->signing_key == "" || $witness->signing_key == "MPH1111111111111111111111111111111114T1Anm") {
        $witness->row_status = "negative";
        $witness->invalid_signing_key = true;
      }
      // Add the new 7 day misses
      $witness->misses_7day = (isset($misses[$witness->owner]) ? $misses[$witness->owner] : 0);
    }
    $this->view->witnesses = $witnesses;
  }
  public function historyAction() {
    $this->view->votes = $votes = WitnessVote::agg([
      [
        '$sort' => ['_ts' => -1]
      ],
      [
        '$limit' => 100
      ],
      [
        '$lookup' => [
          'from' => 'account',
          'localField' => 'account',
          'foreignField' => 'name',
          'as' => '_account',
        ]
      ],
      [
        '$unwind' => '$_account'
      ],
      [
        '$project' => [
          '_id' => '$_id',
          'weight' => ['$sum' => ['$_account.vesting_shares', '$_account.proxy_witness']],
          'witness' => '$witness',
          'account' => '$account',
          'approve' => '$approve',
          '_ts' => '$_ts',
        ]
      ]
    ])->toArray();
    // var_dump($votes[2]->_account[0]->proxied_vsf_votes); exit;
    $this->view->misses = $misses = WitnessMiss::agg([
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-7 days") * 1000)
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => '$witness',
          'total' => [
            '$sum' => '$increase'
          ]
        ]
      ],
      [
        '$sort' => ['total' => -1]
      ]
    ])->toArray();
  }
  public function missesAction() {
    $this->view->history = WitnessMiss::find([
      [],
      "sort" => array("date" => -1),
      "limit" => 500,
    ]);
    $this->view->misses = $misses = WitnessMiss::agg([
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-7 days") * 1000)
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => '$witness',
          'total' => [
            '$sum' => '$increase'
          ]
        ]
      ],
      [
        '$sort' => ['total' => -1]
      ]
    ])->toArray();
  }
}
