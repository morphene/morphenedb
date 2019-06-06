<?php
/*
 * Define custom routes. File gets included in the router service definition.
 */
$router = new Phalcon\Mvc\Router();
$router->removeExtraSlashes(true);

/*
  account view routes
*/

$router->add('/@([-a-zA-Z0-9.]+)', [
  'controller' => 'account',
  'action' => 'view',
  'account' => 1
])->setName("account-view");

$router->add('/@([-a-zA-Z0-9.]+)/([-a-zA-Z0-9]+)', [
  'controller' => 'account',
  'account' => 1,
  'action' => 2
])->setName("account-view-section");

/*
  accounts aggregation
*/

$router->add('/accounts[/]?{filter}?', [
  'controller' => 'accounts',
  'action' => 'list'
]);

/*
  block routes
*/

$router->add('/block/([a-zA-Z0-9]+)', [
  'controller' => 'block',
  'action' => 'view',
  'height' => 1
])->setName("block-view");

$router->add('/tx/([a-zA-Z0-9]+)', [
  'controller' => 'tx',
  'action' => 'view',
  'id' => 1
])->setName("tx-view");

$router->add('/', [
  'controller' => 'index',
  'action' => 'homepage'
]);

/*
  witness routes
*/

$router->add('/witnesses', [
  'controller' => 'witness',
  'action' => 'list'
]);

/*
  API routes
*/

$router->add('/api/account/{account}', [
  'controller' => 'account_api',
  'action' => 'view'
]);

$router->add('/api/account/{account}/{action}', [
  'controller' => 'account_api'
]);

return $router;
