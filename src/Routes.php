<?php

namespace Nebula;


/**
 * Class Router
 *
 * @package Nebula
 * @author lbob created at 2014/11/19 19:52
 */
class Routes
{
    const PATTERN_BASE = '[a-z][a-z0-9\_\-]*';
    const PATTERN_ID = '[\d]+';
    const PATTERN_NAME = '[a-z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
    const PATTERN_TAIL = '.+';
    const PATTERN_TOKEN = '#\{([^\{\}]+)\}#i';
    const PATTERN_TOKEN_LESS = '#(\\/)(\{[^\{\}]+\}\?)#i';
    const PATTERN_HANDLER_DECLARE = '#([a-zA-z][a-zA-Z0-9\_]*)\@([a-z][a-z0-9\_]*)#i';

    public $mappings = array();

    private $tokenDefaultPatterns
        = array(
            'controller' => self::PATTERN_BASE,
            'action'     => self::PATTERN_BASE,
            'id'         => self::PATTERN_ID,
            'name'       => self::PATTERN_NAME,
            'tail'       => self::PATTERN_TAIL,
        );
    private $mappingMatchedHandlers = array();
    private $mappingBeforeHandlers = array();
    private $mappingAfterHandlers = array();
    private $expressions = array();
    private $mappingTokens = array();
    private $baseMappings
        = array(
            'default' => array(
                '/{controller}/{action}/{id}',
            ),
        );
    private $isMatched = false;
    private $matchedMappingsName = '';
    private $isAbort = false;

    private $abortingHandlers = array();
    private $missingHandlers = array();
    private $errorHandlers = array();

    public function __construct()
    {
        foreach ($this->baseMappings as $key => $item) {
            $this->mappings[$key] = $item;
        }
    }

    public function compileRoutes()
    {
        $results = array();
        if (isset($this->mappings) && !empty($this->mappings)) {
            foreach ($this->mappings as $key => $item) {
                $expression = $item[0];
                if (!isset($expression) || empty($expression)) {
                    $this->onError("Expression error [$key]");
                    return;
                }
                $paramsLen = count($item);

                //Router 表达式预处理
                $expression = str_replace('/', '\/', $expression);
                $expression = preg_replace(self::PATTERN_TOKEN_LESS, '$1?$2', $expression);

                //表达式 和 Pattern 处理
                $patterns = array();
                if ($paramsLen > 2) {
                    $patterns = $item[2];
                }

                //装载 Token 匹配模式
                $tokenPatterns = array();
                if (isset($patterns) && is_array($patterns)) {
                    $tokenPatterns = $patterns;
                }

                //载入默认的 Token 模式
                foreach ($this->tokenDefaultPatterns as $defaultKey => $defaultItem) {
                    if (!array_key_exists($defaultKey, $tokenPatterns)) {
                        $tokenPatterns[$defaultKey] = $defaultItem;
                    }
                }

                //解析表达式，载入 Tokens
                $this->mappingTokens[$key] = array();
                if (preg_match_all(self::PATTERN_TOKEN, $expression, $tokens)) {
                    foreach ($tokens[1] as $token) {
                        $this->mappingTokens[$key][] = $token;
                    }
                }
                $this->mappingTokens[$key][] = 'tail';

                //将 Router 表达式转换成正则表达式，用于匹配 URL
                foreach ($tokenPatterns as $tokenKey => $tokenPattern) {
                    $expression
                        = str_replace('{' . $tokenKey . '}', '(?<' . $tokenKey . '>' . $tokenPattern . ')', $expression);
                }
                $expression    = '^' . $expression . '\/?(?:\?(?<tail>' . self::PATTERN_TAIL . '))?' . '$';
                $results[$key] = $expression;

                //Handlers 处理
                if ($paramsLen > 1) {
                    $thirdParam = $item[1];
                    if (isset($thirdParam) && is_array($thirdParam)) {
                        if (array_key_exists('matched', $thirdParam)) {
                            $this->mappingMatchedHandlers[$key] = $thirdParam['matched'];
                        }
                        if (array_key_exists('before', $thirdParam)) {
                            $this->mappingBeforeHandlers[$key] = $thirdParam['before'];
                        }
                        if (array_key_exists('after', $thirdParam)) {
                            $this->mappingAfterHandlers[$key] = $thirdParam['after'];
                        }
                    }
                }
            }
        }
        $this->expressions = $results;
    }

    public function routing()
    {
        if (func_num_args() > 0) {
            $str = func_get_arg(0);
        } else {
            $str = $_SERVER['REQUEST_URI'];
        }

        $this->compileRoutes();
        $params = $this->parseStr($str);
        if ($this->isMatched()) {
            //处理 Before 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingBeforeHandlers)) {
                $beforeHandler = $this->mappingBeforeHandlers[$this->matchedMappingsName];
                $this->invoke('Before', $beforeHandler, $params);
            }

            if ($this->isAbort()) {
                $this->onAborting();
                return;
            }

            //处理 Matched 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingMatchedHandlers)) {
                $matchedHandler = $this->mappingMatchedHandlers[$this->matchedMappingsName];
                $this->invoke('Matched', $matchedHandler, $params);
            }

            if ($this->isAbort()) {
                $this->onAborting();
                return;
            }

            //处理 After 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingAfterHandlers)) {
                $afterHandler = $this->mappingAfterHandlers[$this->matchedMappingsName];
                $this->invoke('After', $afterHandler, $params);
            }
        } else {
            $this->onMissing();
        }
    }

    private function invoke($flag, $handler, $params)
    {
        if (isset($handler)) {
            if (is_callable($handler)) {
                $handler($params, $this);
            } else if (is_string($handler)) {
                if (preg_match(self::PATTERN_HANDLER_DECLARE, $handler, $matches)) {
                    if (!empty($matches[1])) {
                        $className = $matches[1];
                    }
                    if (!empty($matches[2])) {
                        $methodName = $matches[2];
                    }

                    if (!isset($className) || !isset($methodName)) {
                        $this->onError("Router [" . $this->matchedMappingsName . "] can't find handler [$flag]");
                        return;
                    }
                    if (!class_exists($className)) {
                        $this->onError("Router [" . $this->matchedMappingsName . "] 's handler [$flag] can't find class [$className]");
                        return;
                    }
                    if (!method_exists($className, $methodName)) {
                        $this->onError("Router [" . $this->matchedMappingsName . "] 's handler [$flag] can't find method [$methodName] in class [$className]");
                        return;
                    }

                    call_user_func_array(array($className, $methodName), array($params, $this));
                } else {
                    $this->onError("Router [" . $this->matchedMappingsName . "] can't find handler [$flag]");
                    return;
                }
            }
        }
    }

    private function parseStr($str)
    {
        $params = array();
        foreach ($this->expressions as $key => $item) {
            if (preg_match('#' . $item . '#i', $str, $matches)) {
                foreach ($this->mappingTokens[$key] as $tokenKey) {
                    if ($tokenKey === 'tail') {
                        if (!empty($matches['tail'])) {
                            parse_str($matches['tail'], $others);
                            $params += $others;
                        }
                    } else {
                        if (!empty($matches[$tokenKey]))
                            $params[$tokenKey] = $matches[$tokenKey];
                    }
                }
                $this->setMatched($key);
            }
        }
        return $params;
    }

    private function setMatched($mappingName)
    {
        if (isset($mappingName)) {
            $this->matchedMappingsName = $mappingName;
            $this->isMatched           = true;
        }
    }

    public function isMatched()
    {
        return $this->isMatched;
    }

    public function abort()
    {
        $this->isAbort = true;
    }

    public function isAbort()
    {
        return $this->isAbort;
    }

    public function aborting($handler = null)
    {
        if ($this->isAbort()) {
            if (isset($handler) && is_callable($handler)) {
                $this->abortingHandlers[] = $handler;
            }
        }
    }

    public function missing($handler = null)
    {
        if (isset($handler) && is_callable($handler)) {
            $this->missingHandlers[] = $handler;
        }
    }

    private function onAborting()
    {
        if (isset($this->abortingHandlers)) {
            foreach ($this->abortingHandlers as $handler) {
                $handler();
            }
        }
    }

    private function onMissing()
    {
        if (isset($this->missingHandlers)) {
            foreach ($this->missingHandlers as $handler) {
                $handler();
            }
        }
    }

    public function error($handler = null)
    {
        if (isset($handler) && is_callable($handler)) {
            $this->errorHandlers[] = $handler;
        }
    }

    private function onError($message)
    {
        if (isset($this->errorHandlers)) {
            foreach ($this->errorHandlers as $handler) {
                $handler($message);
            }
        }
        $this->isAbort = true;
    }
}