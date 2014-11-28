<?php

namespace Nebula;


/**
 * Class RouteResult
 *
 * @package Nebula
 * @author lbob created at 2014/11/28 9:24
 */
class RouteResult
{
    /**
     * @var array
     */
    public $params;
    /**
     * @var string
     */
    public $url;
    /**
     * @var string
     */
    public $mappingName;
    /**
     * @var boolean
     */
    public $isMatched;

    public function __construct($url, $mappingName, $isMatched, $params)
    {
        $this->url         = $url;
        $this->mappingName = $mappingName;
        $this->isMatched   = $isMatched;
        $this->params      = $params;
    }
}

 