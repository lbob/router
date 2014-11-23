<?php

Nebula\Routes::registerFilter('uavAuth', function() {
    var_dump('uavAuth');
    var_dump(func_get_arg(0));
});