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

$router->registerReadCacheHandler(function() {
    if (is_file('D:\cache\routes.dat'))
        return unserialize(file_get_contents('D:\cache\routes.dat'));
});
$router->registerIsCacheExpiredHandler(function($timestamp) {
    if (is_file('D:\cache\routes.dat'))
        return filemtime('D:\cache\routes.dat') < $timestamp;
});
$router->registerWriteCacheHandler(function($data) {
    $content = serialize($data);
    $cache = 'D:\cache\routes.dat';
    $fp = fopen($cache, 'w+');
    fwrite($fp, $content);
    fclose($fp);
});

$router->routing('/admin/uav/post/edit/1442');

//var_dump($router);

var_dump($router->isMatched());