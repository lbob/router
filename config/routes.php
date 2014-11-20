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
    'expression' => '/{controller}/{action}?/{name}?',
    'handler' => array(
        'matched' => 'TestController@index',
        'before'  => function ($params, $route) {
            echo 'test before.';
        },
        'after'   => function ($params) {
            echo 'test after.';
        }
    ),
);


$routes->missing(function() { echo '<p>Routing missed;</p>'; });
$routes->error(function($message) { echo "<p>$message</p>"; });