<?php
/**
 * Created by PhpStorm.
 * User: lbob
 * Date: 2014/11/19
 * Time: 19:57
 */
require 'vendor/autoload.php';

$routes = new Nebula\Routes();
$routes->mappings['default2'] = array(
    '/{controller}/{action}?/{name}?',
    array(
        'matched' => 'TestController@index',
        'before'  => function ($params, $route) {
            echo 'default2 before.';
            //var_dump($params);
            //$route->abort();
        },
        'after'   => function ($params) {
            echo 'default2 after.';
            //var_dump($params);
        }
    )
);
$routes->missing(function() {
    echo '<p>Routing missed;</p>';
});
$routes->error(function($message) {
    echo "<p>$message</p>";
});
$routes->routing('/airline/show/aa333?ddd=ss&dee=ggg&pp=测试来啦sdfsdf33234');

$routes = new Nebula\Routes();
$routes->missing(function() {
    echo '<p>Routing missed;</p>';
});
$routes->error(function($message) {
    echo "<p>$message</p>";
});
$routes->routing('/airline/show/33?ddd=ss&dee=ggg&pp=测试来啦sdfsdf33234');
/**
 * for ($i = 1; $i < 1000; $i++) {
 * //$routes->routing('uav/admin/airline/show/中文名字来啦asdfdsf33234?ddd=ss&dee=ggg&pp=测试来啦sdfsdf33234');
 * $routes->routing('airline/show/34?ddd=ss&dee=ggg&pp=测试来啦sdfsdf33234');
 * }
 * */