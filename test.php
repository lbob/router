<?php
/**
 * Created by PhpStorm.
 * User: lbob
 * Date: 2014/11/19
 * Time: 19:57
 * @var $routes Nebula\Routes;
 */
require 'vendor/autoload.php';

use Nebula\Routes;

define('SCRIPT_DIR', dirname($_SERVER['SCRIPT_FILENAME']), true);
defined('PROJECT_DIR') || define('PROJECT_DIR', dirname(SCRIPT_DIR), true);
defined('CONF_DIR') || define('CONF_DIR', SCRIPT_DIR . '/config', true);
defined('CONF_FILTER_DIR') || define('CONF_FILTER_DIR', CONF_DIR . '/filter', true);


$routes = Routes::instance(CONF_DIR.'/routes.php', CONF_FILTER_DIR);
$routes->registerMatchedHandler(function($params, $routes) {
    var_dump($params);
});

$routes->mappings['test'] = array(
    'expression' => '/admin/{controller}?/{action}?/{id}?',
    'default' => array(
        'controller' => 'home',
        'action' => 'index'
    )
);

$routes->routing('/admin/home/32');
//var_dump($_SERVER['REQUEST_URI']);

echo '1::'.($routes->reverse('/admin/ddd', array(
    'id' => 12,
))).'<br/>';

echo '2::'.($routes->reverseByRoute('test', array(
    'controller' => 'home',
    'action' => 'sdfdsf',
    'name' => 'ddd',
    'ddd' => 'sss'
))).'<br/>';

//var_dump($routes);