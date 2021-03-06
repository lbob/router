<?php

require '../vendor/autoload.php';
$router = \Nebula\Router::getInstance(__DIR__.'/../config/routes.php', __DIR__.'/../config/filter');

define('ROUTES_CACHE', 'D:\cache\routes.dat');
define('ROUTE_RESULT_CACHE', 'D:\cache\url_route_result.dat');

$router->registerReadCacheHandler(function() {
    if (is_file(ROUTES_CACHE))
        return unserialize(file_get_contents(ROUTES_CACHE));
});
$router->registerIsCacheExpiredHandler(function($timestamp) {
    return true;
    if (is_file(ROUTES_CACHE))
        return filemtime(ROUTES_CACHE) < $timestamp;
});
$router->registerWriteCacheHandler(function($data) {
    $content = serialize($data);
    $cache = ROUTES_CACHE;
    $fp = fopen($cache, 'w+');
    fwrite($fp, $content);
    fclose($fp);
});
$router->registerMatchedHandler(function($params) {
    var_dump($params);
    var_dump('must run');
});

//$router->routing('/admin/uav/home/?idws=333&hea=erere');
//
////var_dump($router);
//
//var_dump($router->isMatched());
//
//var_dump($router->reverse('/post/edit', array(
//    'id' => 111,
//    'ee' => 'dd'
//)));
//
//var_dump($router->reverseByRoute('test', array(
//    'controller' => 'home',
//    'action' => 'index',
//    'id' => 111,
//    'ee' => 'dd'
//)));
//
//var_dump($router->reverse('admin/home/index', array(
//    'id' => 111,
//    'ee' => 'dd'
//)));
//
//var_dump($router);

//$router->routing('/account/setpassword/?id=4&password=123');
$router->routing('/test2');
//var_dump($router);
var_dump($router->isMatched());
var_dump($router->routeResult->mappingName);