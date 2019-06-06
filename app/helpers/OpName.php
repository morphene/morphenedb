<?php
namespace MorpheneDB\Helpers;

use Phalcon\Tag;

class OpName extends Tag
{
  protected static $index = array(
    "account_create" => "Account Create",
    "account_update" => "Account Update",
    "account_witness_proxy" => "Witness Proxy",
    "account_witness_vote" => "Witness Vote",
    "fill_vesting_withdraw" => "Power Down",
    "pow" => "Mining",
    "transfer" => "Transfer",
    "transfer_to_vesting" => "Power Up",
    "witness_update" => "Witness Update",
  );

  public static function string($op, $account = null) {
    $name = $op[0];
    if(isset(static::$index[$op[0]])) {
      $name = static::$index[$op[0]];
    }
    return $name;
  }
}
