<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\UTCDateTime;

use MorpheneDB\Models\Account;
use MorpheneDB\Models\Block30d;
use MorpheneDB\Models\Status;
use MorpheneDB\Models\VestingDeposit;
use MorpheneDB\Models\VestingWithdraw;

class LabsController extends ControllerBase
{
  public function indexAction()
  {

  }

  public function powerdownAction() {
    $props = $this->morphened->getProps();
    $converted = array(
      'current' => (float) explode(" ", $props['current_supply'])[0],
      'vesting' => (float) explode(" ", $props['total_vesting_fund_morph'])[0],
    );
    $converted['liquid'] = $converted['current'] - $converted['vesting'];
    $this->view->props = $converted;
    $this->view->dow = array('', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat');
    $transactions = Account::agg([
      [
        '$match' => [
          'next_vesting_withdrawal' => [
            '$gte' => new UTCDateTime(strtotime(date("Y-m-d")) * 1000)
          ],
          'vesting_withdraw_rate' => ['$gt' => 0]
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$next_vesting_withdrawal'],
            'year' => ['$year' => '$next_vesting_withdrawal'],
            'month' => ['$month' => '$next_vesting_withdrawal'],
            'day' => ['$dayOfMonth' => '$next_vesting_withdrawal'],
            'dow' => ['$dayOfWeek' => '$next_vesting_withdrawal'],
          ],
          'count' => ['$sum' => 1],
          'withdrawn' => ['$sum' => '$vesting_withdraw_rate'],
        ],
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ],
      [
        '$limit' => 7
      ]
    ])->toArray();
    $this->view->upcoming_total = array_sum(array_column($transactions, 'withdrawn'));
    $this->view->upcoming = $transactions;
    $transactions = VestingWithdraw::agg([
      [
        '$match' => [
          '_ts' => [
            '$gte' => new UTCDateTime((strtotime(date("Y-m-d")) - (86400 * 7)) * 1000),
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
            'dow' => ['$dayOfWeek' => '$_ts'],
          ],
          'count' => ['$sum' => 1],
          'withdrawn' => ['$sum' => '$withdrawn'],
          'deposited' => ['$sum' => '$deposited'],
        ],
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ],
      [
        '$limit' => 8
      ]
    ])->toArray();
    $this->view->previous_total = array_sum(array_column($transactions, 'withdrawn'));
    $this->view->previous = $transactions;

    $transactions = VestingWithdraw::agg([
      [
        '$match' => [
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
            '$lte' => new UTCDateTime(strtotime("midnight") * 1000),
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'user' => '$from_account',
          ],
          'count' => ['$sum' => 1],
          'withdrawn' => ['$sum' => '$withdrawn'],
          'deposited' => ['$sum' => '$deposited'],
          'deposited_to' => ['$addToSet' => '$to_account'],
        ],
      ],
      [
        '$lookup' => [
          'from' => 'account',
          'localField' => '_id.user',
          'foreignField' => 'name',
          'as' => 'account'
        ]
      ],
      [
        '$sort' => [
          'withdrawn' => -1
        ]
      ],
      [
        '$limit' => 100
      ]
    ])->toArray();
    $this->view->powerdowns = $transactions;
  }

  public function powerupAction() {
    // {transactions: {$elemMatch: {'operations.0.0': 'transfer_to_vesting'}}
    $days = 30;
    $this->view->filter = $filter = $this->request->get('filter');
    switch($filter) {
      case "week":
        $days = 7;
        break;
      case "day":
        $days = 1;
        break;
    }
    $powerups = VestingDeposit::agg([
      [
        '$match' => [
          '_ts' => [
            '$gte' => new UTCDateTime(strtotime("-".$days." days") * 1000),
          ],
        ]
      ],
      [
        '$project' => [
          'date' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts'],
          ],
          'to' => '$to',
          'amount' => '$amount',
          'from' => '$from',
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'user' => [ '$cond' => [
              'if' => ['$eq' => ['$to', '']],
              'then' => '$from',
              'else' => '$to',
            ] ]
          ],
          'count' => ['$sum' => 1],
          'instances' => ['$addToSet' => '$amount']
        ],
      ],
      [
        '$limit' => 1000
      ],
      [
        '$lookup' => [
          'from' => 'account',
          'localField' => '_id.user',
          'foreignField' => 'name',
          'as' => 'account'
        ]
      ],
    ], [
      'allowDiskUse' => true,
      'cursor' => [
        'batchSize' => 0
      ]
    ])->toArray();
    // var_dump($powerups); exit;
    foreach($powerups as $idx => $tx) {
      $powerups[$idx]['total'] = 0;
      foreach($tx['instances'] as $powerup) {
        $powerups[$idx]['total'] += (float) explode(" ", $powerup)[0];
      }
    }
    usort($powerups, function($a, $b) {
      return $b['total'] - $a['total'];
    });
    $this->view->powerups = $powerups;
  }

  public function clientsAction() {
    $results = Status::findFirst([['_id' => 'clients-snapshot']]);
    $this->view->dates = $results->data;
  }
}
