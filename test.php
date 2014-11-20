<?php
/**
 * Created by PhpStorm.
 * User: lbob
 * Date: 2014/11/19
 * Time: 19:57
 */
require 'vendor/autoload.php';

use Nebula\Routes;

define('SCRIPT_DIR', dirname($_SERVER['SCRIPT_FILENAME']), true);
defined('PROJECT_DIR') || define('PROJECT_DIR', dirname(SCRIPT_DIR), true);
defined('CONF_DIR') || define('CONF_DIR', SCRIPT_DIR . '/config', true);


$routes = Routes::getInstance(CONF_DIR.'/routes.php');
$routes->registerMatchedHandler(function($params, $routes) {
    var_dump($params);
});
$routes->routing('/airline/show/aa333?ddd=ss&dee=ggg&pp=测试来啦sdfsdf33234');
