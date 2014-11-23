<?php

Nebula\Routes::registerFilter('auth', function() {
    var_dump('auth');
    var_dump(func_get_arg(0));
    $params = func_get_arg(0);
    if (array_key_exists('url', $params) && isset($params['url'])) {
        header('location:http://'.$params['url']);
    }
});