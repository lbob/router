<?php
/**
 * @var $routes Nebula\Routes
 */

$routes->mappings['home'] = array(
    'expression' => '/',
    'handler' => array(
        'matched' => function() {
            echo 'Home!';
        }
    )
);

$routes->mappings['test'] = array(
    'expression' => '/uav/admin/{controller}/{action}?/{name}?',
    'handler' => array(
        'before'  => function ($params, $route) {
            var_dump('test before.');
        },
        'after'   => function ($params) {
            var_dump('test after.');
        }
    ),
);

$routes->mappings['api'] = array(
    'expression' => '/api',
    'handler' => array(
        'matched' => function() {
            echo 'API!';
        }
    )
);

$routes->bindFilter(array(
    'namespace' => 'uav/admin',
    'controller' => 'airline',
    'action' => '*',
), array('before' => 'auth|uavAuth'));

$routes->bindFilter('api', array('before' => 'auth|uavAuth'));



//$routes->filter('auth', function() {
//    var_dump('auth');
//    var_dump(func_get_arg(0));
//    $params = func_get_arg(0);
//    if (array_key_exists('url', $params) && isset($params['url'])) {
//        header('location:http://'.$params['url']);
//    }
//});


$routes->missing(function() { echo '<p>Routing missed;</p>'; });
$routes->error(function($message) { echo "<p>$message</p>"; });
