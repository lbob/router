<?php

namespace Nebula;


/**
 * Class Router
 * 路由配置示例：
 * $routes->mappings['default'] = array(
 *     'expression' => '/{controller}?/{action}?/{id}?',
 *     'handler' => array(
 *         'matched' => 'TestController@index',
 *         'before'  => function ($params, $route) {
 *             echo 'default2 before.';
 *         },
 *         'after'   => function ($params) {
 *             echo 'default2 after.';
 *         }
 *     ),
 *     'pattern' => array(
 *         'controller' => '',
 *         'action' => '',
 *         'id' => '',
 *     ),
 * );
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
    const PATTERN_NAMESPACE = '#^\/?([^\{\}]+[a-zA-Z0-9])#i';

    public $mappings = array();
    public $loaded = false;

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
    private $mappingNamespaces = array();
    private $mappingTokenDefaultValues = array();
    private $baseMappings
        = array(
            'default' => array(
                'expression' => '/{controller}?/{action}?/{id}?',
            ),
        );
    private $isMatched = false;
    private $matchedMappingsName = '';
    private $isAbort = false;

    private $abortingHandlers = array();
    private $missingHandlers = array();
    private $errorHandlers = array();
    private $matchedHandlers = array();

    private $source;
    private $timestamp = -1;
    private $defaultMappingMatchedHandlers = array();
    private $filterHandlers = array();
    private $filterBinders = array();
    private $mappingFilterBinders = array();
    private $filterDir;
    private $filterDirCache = array();

    /**
     * @var $instance Routes
     */
    private static $instance;

    public function __construct()
    {

    }

    public static function instance($config = null, $filterDir = null)
    {
        if (!isset(self::$instance)) {
            self::$instance = self::config($config, $filterDir);
        }
        return self::$instance;
    }

    protected static function config($config, $filterDir = null, Routes $routes = null)
    {
        if (!isset($routes)) {
            $class  = get_called_class();
            $routes = new $class();
        }
        if (!$routes->loaded) {
            $routes->loaded    = true;
            $routes->filterDir = $filterDir;
            if (is_file($config) && is_readable($config)) {
                $routes->source    = $config;
                $routes->timestamp = filemtime($config);
                require $config;
            }
        }
        return $routes;
    }

    public function compileRoutes()
    {
        $results = array();
        if (isset($this->baseMappings) && !empty($this->baseMappings)) {
            foreach ($this->baseMappings as $key => $item) {
                $this->mappings[$key] = $item;
            }
        }
        if (isset($this->mappings) && !empty($this->mappings)) {
            foreach ($this->mappings as $key => $item) {
                $expression = $item['expression'];
                if (!isset($expression) || empty($expression)) {
                    throw new \InvalidArgumentException("Expression error [$key]");
                }

                //解析Namespace
                if (preg_match(self::PATTERN_NAMESPACE, $expression, $matches)) {
                    if (!empty($matches[1]))
                        $this->mappingNamespaces[$key] = $matches[1];
                }

                //Router 表达式预处理
                $expression = str_replace('/', '\/', $expression);
                $expression = preg_replace(self::PATTERN_TOKEN_LESS, '$1?$2', $expression);

                //表达式 和 Pattern 处理
                $patterns = array();
                if (isset($item['pattern'])) {
                    $patterns = $item['pattern'];
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
                if (isset($item['handler'])) {
                    $thirdParam = $item['handler'];
                    if (is_array($thirdParam)) {
                        if (array_key_exists('matched', $thirdParam)) {
                            $this->mappingMatchedHandlers[$key][] = $thirdParam['matched'];
                        }
                        if (array_key_exists('before', $thirdParam)) {
                            $this->mappingBeforeHandlers[$key][] = $thirdParam['before'];
                        }
                        if (array_key_exists('after', $thirdParam)) {
                            $this->mappingAfterHandlers[$key][] = $thirdParam['after'];
                        }
                    }
                }

                // Token Default Values 处理
                if (isset($item['default'])) {
                    $defaultTokenValues = $item['default'];
                    if (is_array($defaultTokenValues)) {
                        $this->mappingTokenDefaultValues[$key] = $defaultTokenValues;
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
            $this->loadTokenDefaultValues($params);
            $this->onMatched($params, $this);
            $this->matchFilter($params);

            //处理 Before 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingBeforeHandlers)) {
                $beforeHandlers = $this->mappingBeforeHandlers[$this->matchedMappingsName];
                $this->invoke('Before', $beforeHandlers, $params);
            }

            if ($this->isAbort()) {
                $this->onAborting();
                return;
            }

            //处理 Matched 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingMatchedHandlers)) {
                $matchedHandlers = $this->mappingMatchedHandlers[$this->matchedMappingsName];
                $this->invoke('Matched', $matchedHandlers, $params);
            } else {
                $this->invoke('Matched', $this->defaultMappingMatchedHandlers, $params);
            }

            if ($this->isAbort()) {
                $this->onAborting();
                return;
            }

            //处理 After 事件
            if (array_key_exists($this->matchedMappingsName, $this->mappingAfterHandlers)) {
                $afterHandlers = $this->mappingAfterHandlers[$this->matchedMappingsName];
                $this->invoke('After', $afterHandlers, $params);
            }
        } else {
            $this->onMissing();
        }
    }

    private function invoke($flag, $handlers, $params)
    {
        if (isset($handlers) && !empty($handlers)) {
            foreach ($handlers as $handler) {
                if (is_callable($handler)) {
                    if ($handler($params, $this) === false) {
                        $this->abort(); //返回值为 false 则中止事件栈
                        return;
                    }
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

                        //如果构造函数带有形参，则此方法行不通。
                        $object = new $className();
                        if (call_user_func_array(array($object, $methodName), array($params, $this)) === false) {
                            $this->abort();
                            return;
                        }
                    } else {
                        $this->onError("Router [" . $this->matchedMappingsName . "] can't find handler [$flag]");
                        return;
                    }
                }
            }
        }
    }

    private function parseStr($str)
    {
        $params = array();
        foreach ($this->expressions as $key => $item) {
            if (preg_match('#' . $item . '#i', $str, $matches)) {
                if (array_key_exists($key, $this->mappingNamespaces)) {
                    $params['namespace'] = $this->mappingNamespaces[$key];
                }
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
                break;
            }
        }
        return $params;
    }

    private function loadTokenDefaultValues(&$params)
    {
        if ($this->isMatched() && isset($this->mappingTokenDefaultValues) && !empty($this->mappingTokenDefaultValues)) {
            if (array_key_exists($this->matchedMappingsName, $this->mappingTokenDefaultValues)) {
                foreach ($this->mappingTokenDefaultValues[$this->matchedMappingsName] as $key => $value) {
                    if (!array_key_exists($key, $params)) {
                        $params[$key] = $value;
                    }
                }
            }
        }
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

    public function matched($handler = null)
    {
        if (isset($handler) && is_callable($handler)) {
            $this->matchedHandlers[] = $handler;
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

    private function onMatched($param, $routes = null)
    {
        if (isset($this->matchedHandlers)) {
            foreach ($this->matchedHandlers as $handler) {
                $handler($param, $routes);
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

    public function registerMatchedHandler($handler)
    {
        if (isset($handler)) {
            $this->defaultMappingMatchedHandlers[] = $handler;
        }
    }

    public static function registerFilter($key, $callback)
    {
        if (isset($key) && isset($callback)) {
            self::$instance->filterHandlers[$key] = $callback;
        }
    }

    public function filter($key, $callback)
    {
        if (isset($key) && isset($callback)) {
            $this->filterHandlers[$key] = $callback;
        }
    }

    public function bindFilter()
    {
        $paramsLen = func_num_args();
        if ($paramsLen > 0)
            $pattern = func_get_arg(0);
        if (!isset($pattern)) {
            throw new \InvalidArgumentException('Filter pattern invalid.');
        }
        if ($paramsLen > 1)
            $handler = func_get_arg(1);
        if (!isset($handler)) {
            throw new \InvalidArgumentException('Filter handler invalid.');
        }
        if (is_array($pattern)) {
            $filterKey = '';
            foreach ($pattern as $key => $value) {
                $realValue = $value;
                if ($realValue === '*') {
                    $realValue = '[^\]]+';
                }
                $filterKey = $filterKey . '\[' . $key . '=' . $realValue . '\]';
            }
            $this->filterBinders[$filterKey] = $handler;
        } else {
            $this->mappingFilterBinders[$pattern] = $handler;
        }
    }

    private function matchFilter($params)
    {
        if ($this->isMatched()) {
            $handlers = array();
            if (isset($params) && !empty($params)) {
                $filterKey = '';
                foreach ($params as $key => $value) {
                    $filterKey = $filterKey . '[' . $key . '=' . $value . ']';
                }
                if (isset($filterKey)) {
                    foreach ($this->filterBinders as $filterBinderPattern => $filterBinderValue) {
                        if (preg_match('#' . str_replace('/', '\/', $filterBinderPattern) . '#i', $filterKey, $matches)) {
                            $handlers = $this->filterBinders[$filterBinderPattern];
                            break;
                        }
                    }
                }
            }
            if (array_key_exists($this->matchedMappingsName, $this->mappingFilterBinders))
                $handlers = $this->mappingFilterBinders[$this->matchedMappingsName];
            if (isset($handlers) && !empty($handlers)) {
                foreach ($handlers as $key => $value) {
                    //取得Filter的Handler
                    if (isset($value)) {
                        $filterNames = explode('|', $value);
                        foreach ($filterNames as $filterName) {
                            if (!array_key_exists($filterName, $this->filterHandlers)) {
                                $this->filterConfig($filterName);
                            }
                            if (array_key_exists($filterName, $this->filterHandlers)) {
                                $filterHandler = $this->filterHandlers[$filterName];
                                if ($key === 'before')
                                    $this->mappingBeforeHandlers[$this->matchedMappingsName][] = $filterHandler;
                                // Filter 不支持 Matched 事件
//                                if ($key === 'matched')
//                                    $this->mappingMatchedHandlers[$this->matchedMappingsName][] = $filterHandler;
                                if ($key === 'after')
                                    $this->mappingAfterHandlers[$this->matchedMappingsName][] = $filterHandler;
                            }
                        }
                    }
                }
            }
        }
    }

    private function filterConfig($filterName)
    {
        // 尝试载入同名的 filter 定义文件
        if (array_key_exists($filterName, $this->filterDirCache)) {
            $filterPath = $this->filterDirCache[$filterName];
        } else {
            $filterPath = $this->filterDir . DIRECTORY_SEPARATOR . $filterName . '.php';
        }
        if (is_file($filterPath) && is_readable($filterPath)) {
            $this->filterDirCache[$filterName] = $filterPath;
            require_once $filterPath;
        } else {
            $this->onError("Can't find filter [$filterName].");
        }
    }
}