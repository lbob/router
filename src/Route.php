<?php

namespace Nebula;


/**
 * Class Route
 *
 * @package Nebula
 * @author lbob created at 2014/11/27 17:01
 */
class Route {
    public $mappingName;
    public $namespace;
    public $expression;
    public $patterns;
    public $tokens;
    public $beforeHandlers;
    public $matchedHandlers;
    public $afterHandlers;
    public $defaultValues;
    public $beforeFilters;
    public $afterFilters;
}

 