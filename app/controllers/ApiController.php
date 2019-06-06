<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

use MorpheneDB\Models\Account;
use MorpheneDB\Models\AccountHistory;
use MorpheneDB\Models\Block30d;
use MorpheneDB\Models\PropsHistory;
use MorpheneDB\Models\Statistics;
use MorpheneDB\Models\Witness;
use MongoDB\BSON\ObjectID;

class ApiController extends ControllerBase
{

  public function initialize()
  {
    header('Content-type:application/json');
    $this->view->disable();
    ini_set('precision', 20);
  }

  public function growthAction()
  {
    $users = Statistics::find([
      [
        'key' => 'users',
        'date' => ['$gt' => new UTCDateTime(strtotime("-90 days") * 1000)],
      ],
    ]);
    $gpd = array();
    foreach($users as $day) {
      $gpd[$day->date->toDateTime()->format('U')] = $day->value;
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function newbiesAction()
  {
    $data = AccountHistory::agg([
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("midnight") * 1000),
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => '$account',
          'dates' => [
            '$push' => [
              '$dateToString' => [
                'format' => '%Y-%m-%d',
                'date' => '$date'
              ]
            ]
          ],
          'days' => [
            '$sum' => 1
          ]
        ],
      ],
      [
        '$match' => [
          'days' => 1
        ]
      ],
      [
        '$limit' => 10
      ],
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function supplyAction()
  {
    $data = AccountHistory::agg([
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-30 days") * 1000),
            '$lte' => new UTCDateTime(strtotime("midnight") * 1000),
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$date'],
            'year' => ['$year' => '$date'],
            'month' => ['$month' => '$date'],
            'day' => ['$dayOfMonth' => '$date'],
          ],
          'morph' => [
            '$sum' => '$balance'
          ],
          'vests' => [
            '$sum' => '$vesting_shares'
          ]
        ],
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ],
      [
        '$limit' => 30
      ],
    ])->toArray();
    foreach($data as $idx => $date) {
      $data[$idx]->sp = (float) $this->convert->vest2sp($data[$idx]->vests, null);
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function morphAction()
  {
    $data = AccountHistory::agg([
      [
        '$match' => [
          'date' => [
            '$gte' => new UTCDateTime(strtotime("-60 days") * 1000),
            '$lte' => new UTCDateTime(strtotime("midnight") * 1000),
          ]
        ]
      ],
      [
        '$group' => [
          '_id' => [
            'doy' => ['$dayOfYear' => '$date'],
            'year' => ['$year' => '$date'],
            'month' => ['$month' => '$date'],
            'day' => ['$dayOfMonth' => '$date'],
          ],
          'morph' => [
            '$sum' => '$balance'
          ],
          'vests' => [
            '$sum' => '$vesting_shares'
          ]
        ],
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ],
      [
        '$limit' => 60
      ],
    ], [
      'allowDiskUse' => true,
      'cursor' => [
        'batchSize' => 0
      ]
    ])->toArray();
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function propsAction()
  {
    $data = PropsHistory::find([
      [],
      'sort' => array('time' => -1),
      'limit' => 500
    ]);
    foreach($data as $idx => $document) {
      $data[$idx] = $document->toArray();
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function percentageAction()
  {
    $results = PropsHistory::find([
      [],
      'sort' => array('date' => -1),
      'limit' => 500
    ]);
    $data = [];
    foreach($results as $doc) {
      $key = $doc->time->toDateTime()->format("U");
      $data[$key] = $doc->total_vesting_fund_morph / $doc->current_supply;
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function topwitnessesAction() {
    $witnesses = Witness::find(array(
      array(
      ),
      "sort" => array(
        'votes' => -1
      ),
      "limit" => 50
    ));
    $data = array();
    foreach($witnesses as $witness) {
      $data[$witness->owner] = Account::agg(array(
        ['$match' => [
            'witness_votes' => $witness->owner,
        ]],
        ['$project' => [
          'name' => '$name',
          'weight' => ['$sum' => ['$vesting_shares', '$proxy_witness']]
        ]],
        ['$sort' => ['weight' => -1]]
      ))->toArray();
    }
    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function accountsAction() {

    $query = array();
    $sorting = array();

    $filter = $this->request->get('sort');
    switch($filter) {
      case "morph":
        $sorting = array('total_balance' => -1);
        break;
      case "vest":
        $sorting = array('vesting_balance' => -1);
        break;
    }

    $account = $this->request->get('account');
    if($account) {
      if(is_array($account)) {
        $query['name'] = ['$in' => $account];
      } else {
        $query['name'] = (string) $account;
      }

    }

    $page = $this->request->get('page') ?: 1;
    $perPage = 100;
    $skip = $perPage * ($page - 1);

    $data = Account::find(array(
      $query,
      "sort" => $sorting,
      "limit" => $perPage,
      "skip" => $skip
    ));

    foreach($data as $idx => $document) {
      $data[$idx] = $document->toArray();
    }

    echo json_encode($data, JSON_PRETTY_PRINT);
  }

  public function powerupAction() {
    $transactions = Block30d::agg([
      [
        '$match' => [
          'transactions' => [
            '$elemMatch' => ['operations.0.0' => 'transfer_to_vesting']
          ]
        ]
      ],
      [
        '$unwind' => '$transactions'
      ],
      [
        '$unwind' => '$transactions.operations',
      ],
      [
        '$match' => [
          'transactions.operations.0' => 'transfer_to_vesting'
        ]
      ],
      [
        '$unwind' => '$transactions.operations',
      ],
      [
        '$match' => [
          'transactions.operations.to' => ['$exists' => true]
        ]
      ],
      [
        '$project' => [
          'target' => '$transactions.operations',
          'date' => [
            'doy' => ['$dayOfYear' => '$_ts'],
            'year' => ['$year' => '$_ts'],
            'month' => ['$month' => '$_ts'],
            'day' => ['$dayOfMonth' => '$_ts'],
          ],
        ]
      ],
      [
        '$group' => [
          '_id' => '$date',
          'count' => ['$sum' => 1],
          'instances' => ['$addToSet' => '$target.amount']
        ],
      ],
      [
        '$sort' => [
          '_id.year' => 1,
          '_id.doy' => 1
        ]
      ]
    ])->toArray();
    foreach($transactions as $idx => $tx) {
      $transactions[$idx]['total'] = 0;
      foreach($tx['instances'] as $powerup) {
        $transactions[$idx]['total'] += (float) explode(" ", $powerup)[0];
      }
      unset($transactions[$idx]['instances']);
    }
    echo json_encode($transactions, JSON_PRETTY_PRINT);
  }

  public function powerdown1000Action() {
    $accounts = Account::agg([
      ['$sort' => [
        'vesting_shares' => -1
        ]],
      ['$limit' => 1000]
    ])->toArray();
    $count = 0;
    foreach($accounts as $account) {
      if($account->next_vesting_withdrawal->toDateTime()->getTimestamp() > 0) {
        $count++;
      }
    }
    echo $count . " / 1000"; exit;
  }

}
