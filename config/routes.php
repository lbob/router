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


$routes->missing(function() { echo '<p>Routing missed;</p>'; });
$routes->error(function($message) { echo "<p>$message</p>"; });


$routes->bindFilter(array(
    'namespace' => 'uav/admin',
    'controller' => 'airline',
    'action' => '*',
), array('before' => 'auth'));

$routes->bindFilter('api', array('before' => 'auth|uavAuth'));


$routes->registerFilter('auth', function() {
    var_dump('auth');
    var_dump(func_get_arg(0));
});

$routes->registerFilter('uavAuth', function() {
    var_dump('uavAuth');
    var_dump(func_get_arg(0));
});