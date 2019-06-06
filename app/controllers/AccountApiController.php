<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\ObjectID;
use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

use MorpheneDB\Models\Account;
use MorpheneDB\Models\AccountHistory;
use MorpheneDB\Models\Block30d;
use MorpheneDB\Models\Pow;
use MorpheneDB\Models\Statistics;
use MorpheneDB\Models\Transfer;
use MorpheneDB\Models\Vote;
use MorpheneDB\Models\VestingDeposit;
use MorpheneDB\Models\VestingWithdraw;
use MorpheneDB\Models\WitnessHistory;

class AccountApiController extends ControllerBase
{

  public function initialize()
  {
    header('Content-type:application/json');
    $this->view->disable();
    ini_set('precision', 20);
  }

  public function viewAction() {
    $account = $this->dispatcher->getParam("account");
    $data = Account::findFirst([
      ['name' => $account]
    ]);
    echo json_encode($data->toArray(), JSON_PRETTY_PRINT);
  }

  public function witnessvotesAction() {
    $account = $this->dispatcher->getParam("account");
    $data = Account::agg(array(
      ['$match' => [
          'witness_votes' => $account,
      ]],
      ['$project' => [
        'name' => '$name',
        'weight' => ['$sum' => ['$vesting_shares', '$proxy_witness']]
      ]],
      ['$sort' => ['weight' => -1]]
    ))->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function snapshotsAction() {
    $account = $this->dispatcher->getParam("account");
    $data = AccountHistory::find([
      ['account' => $account],
      'sort' => ['date' => 1],
      'limit' => 100
    ]);
    foreach($data as $idx => $document) {
      $data[$idx] = $document->toArray();
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function historyAction() {
    $account = $this->dispatcher->getParam("account");
    $data = AccountHistory::agg([
      [
        '$match' => [
          'name' => $account,
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
          ]
        ]
      ],
      [
        '$sort' => [
          'date' => -1
        ]
      ],
      [
        '$project' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$date'],
            'year' => ['$year' => '$date'],
            'month' => ['$month' => '$date'],
            'day' => ['$dayOfMonth' => '$date'],
          ],
          'vests' => '$vesting_shares',
        ]
      ],
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function miningAction() {
    $account = $this->dispatcher->getParam("account");
    $witness = Block30d::agg([
      [
        '$match' => [
          'witness' => $account,
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
          ],
        ]
      ],
      [
        '$project' => [
          'witness' => 1,
          '_ts' => 1,
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'week' => ['$week' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts']
          ],
          'blocks' => [
            '$sum' => 1
          ]
        ]
      ]
    ], [
      'allowDiskUse' => true,
      'cursor' => [
        'batchSize' => 0
      ]
    ])->toArray();
    $pow = Pow::agg([
      [
        '$match' => [
          'work.input.worker_account' => $account,
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
          ],
        ]
      ],
      [
        '$project' => [
          'work.input.worker_account' => 1,
          '_ts' => 1,
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'week' => ['$week' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts']
          ],
          'blocks' => [
            '$sum' => 1
          ]
        ]
      ]
    ], [
      'allowDiskUse' => true,
      'cursor' => [
        'batchSize' => 0
      ]
    ])->toArray();
    if(empty($pow)) {
      // Plottable doesn't play well when the first series is empty.
      $pow = array(array('_id' => ['doy' => 0,'year' => 0,'month' => 0,'week' => 0,'day' => 0], 'blocks' => 0));
    }
    echo json_encode(['pow' => $pow, 'witness' => $witness]);
  }

  public function witnessAction() {
    $account = $this->dispatcher->getParam("account");
    $data = WitnessHistory::agg([
      [
        '$match' => [
          'owner' => $account
        ]
      ],
      [
        '$project' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$created'],
            'year' => ['$year' => '$created'],
            'month' => ['$month' => '$created'],
            'day' => ['$dayOfMonth' => '$created'],
          ],
          'votes' => '$votes'
        ]
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ]
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function powerupAction() {
    $account = $this->dispatcher->getParam("account");
    $data = VestingDeposit::agg([
      [
        '$match' => [
          'to' => $account,
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-90 days") * 1000),
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts'],
          ],
          'value' => ['$sum' => '$amount'],
        ]
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ]
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function powerdownAction() {
    $account = $this->dispatcher->getParam("account");
    $data = VestingWithdraw::agg([
      [
        '$match' => [
          'from_account' => $account,
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-90 days") * 1000),
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts'],
          ],
          'value' => ['$sum' => '$deposited'],
        ]
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ]
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function transfersAction() {
    $account = $this->dispatcher->getParam("account");
    $data = Transfer::agg([
      [
        '$match' => [
          '$or' => [
            ['from' => $account],
            ['to' => $account],
          ],
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-365 days") * 1000),
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts'],
          ],
          'value' => ['$sum' => ['$cond' => [
            ['$eq' => ['$to', $account]],
            '$amount',
            ['$multiply' => ['$amount', -1]]
          ]]],
        ]
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ]
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }
}
