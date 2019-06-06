<?php
namespace MorpheneDB\Controllers;

use MongoDB\BSON\Regex;
use MongoDB\BSON\UTCDateTime;

use MorpheneDB\Models\Account;
use MorpheneDB\Models\AccountHistory;
use MorpheneDB\Models\Block30d;
use MorpheneDB\Models\Statistics;
use MorpheneDB\Models\Pow;
use MorpheneDB\Models\Transfer;
use MorpheneDB\Models\VestingDeposit;
use MorpheneDB\Models\VestingWithdraw;
use MorpheneDB\Models\WitnessMiss;
use MorpheneDB\Models\WitnessHistory;
use MorpheneDB\Models\WitnessVote;

class AccountController extends ControllerBase
{

  private function getAccount()
  {
    $account = strtolower($this->dispatcher->getParam("account"));
    $cacheKey = 'account-'.$account;
    // Load account from the database
    $this->view->account = Account::findFirst(array(
      array(
        'name' => $account
      )
    ));
    // Check the cache for this account from the blockchain
    $cached = $this->memcached->get($cacheKey);
    // No cache, let's load
    if($cached === null) {
      $this->view->live = $this->morphened->getAccount($account);
      $this->memcached->save($cacheKey, $this->view->live, 60);
    } else {
      // Use cache
      $this->view->live = $cached;
    }
    return $account;
  }

  public function viewAction()
  {
    $account = $this->getAccount();
    $this->view->props = $this->morphened->getProps();
    try {
      $this->view->activity = $this->morphened->getAccountHistory($account);
    } catch (Exception $e) {
      $this->view->activity = false;
    }
    $this->view->mining = Pow::find(array(
      array(
        'witness' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function propsAction()
  {
    $account = $this->getAccount();
    $this->view->history = WitnessHistory::find(array(
      ['owner' => $account],
      'sort' => array('created' => -1),
      'limit' => 100
    ));
    $this->view->pick("account/view");
  }

  public function witnessAction()
  {
    $account = $this->getAccount();
    $this->view->votes = WitnessVote::agg([
      ['$match' => [
        'witness' => $account
      ]],
      ['$sort' => [
        '_ts' => -1
      ]],
      ['$limit' => 100],
      ['$lookup' => [
        'from' => 'account',
        'localField' => 'account',
        'foreignField' => 'name',
        'as' => 'voter'
      ]],
    ]);
    $this->view->witnessing = Account::agg([
      ['$match' => [
          'witness_votes' => $account,
      ]],
      ['$project' => [
        'name' => '$name',
        'weight' => ['$sum' => ['$vesting_shares', '$proxy_witness']]
      ]],
      ['$sort' => ['weight' => -1]]
    ])->toArray();
    $this->view->witness_votes = array_sum(array_map(function($item) {
      return $item['weight'];
    }, $this->view->witnessing));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function blocksAction()
  {
    $account = $this->getAccount();
    $query = array(
      array(
        'witness' => $account,
      ),
      'sort' => array('_ts' => -1),
      'limit' => 100
    );
    // var_dump($query); exit;
    $this->view->mining = Block30d::find($query);
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function missedAction()
  {
    $account = $this->getAccount();
    $this->view->mining = WitnessMiss::find(array(
      array(
        'witness' => $account,
      ),
      'sort' => array('date' => -1),
      'limit' => 100
    ));
    $this->view->pick("account/view");
  }

  public function proxiedAction()
  {
    $account = $this->getAccount();
    $this->view->proxied = Account::find(array(
      array('proxy' => $account)
    ));
    $this->view->pick("account/view");
  }

  public function powerupAction()
  {
    $account = $this->getAccount();
    $this->view->powerup = VestingDeposit::find(array(
      array('to' => $account),
      'sort' => array('_ts' => -1)
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function powerdownAction()
  {
    $account = $this->getAccount();
    $this->view->powerdown = VestingWithdraw::find(array(
      array('from_account' => $account),
      'sort' => array('_ts' => -1)
    ));
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function transfersAction()
  {
    $account = $this->getAccount();
    $this->view->page = $page = (int) $this->request->get("page") ?: 1;
    $limit = 500;
    $this->view->transfers = Transfer::find(array(
      array(
        '$or' => array(
          array('from' => $account),
          array('to' => $account),
        )
      ),
      'sort' => array('_ts' => -1),
      'skip' => $limit * ($page - 1),
      'limit' => $limit,
    ));
    $this->view->pages = ceil(Transfer::count(array(
      array(
        '$or' => array(
          array('from' => $account),
          array('to' => $account),
        )
      ),
    )) / $limit);
    $this->view->chart = true;
    $this->view->pick("account/view");
  }

  public function dataAction()
  {
    $account = $this->getAccount();
    $this->view->pick("account/view");
  }
}
