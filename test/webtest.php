<?php

require '../vendor/autoload.php';
$router = \Nebula\Router::getInstance(__DIR__.'/../config/routes.php', __DIR__.'/../config/filter');

$router->mappings['test'] = array(
    'expression' => '/admin/uav/{controller}/{action}?/{test}?//////',
    'pattern' => array(
        'test' => '\d{4}'
    ),
    'handler' => array(
        'before' => function($params) {
            var_dump('before handler');
        },
        'matched' => function($params) {
            var_dump('matched handler');
        },
        'after' => function($params) {
            var_dump('after handler');
        }
    )
);

define('ROUTES_CACHE', 'D:\cache\routes.dat');
define('ROUTE_RESULT_CACHE', 'D:\cache\url_route_result.dat');

$router->registerReadCacheHandler(function() {
    if (is_file(ROUTES_CACHE))
        return unserialize(file_get_contents(ROUTES_CACHE));
});
$router->registerIsCacheExpiredHandler(function($timestamp) {
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

$router->routing('/admin/uav/home/?idws=333&hea=erere');

//var_dump($router);

var_dump($router->isMatched());

var_dump($router->reverse('/post/edit', array(
    'id' => 111,
    'ee' => 'dd'
)));

var_dump($router->reverseByRoute('test', array(
    'controller' => 'home',
    'action' => 'index',
    'id' => 111,
    'ee' => 'dd'
)));