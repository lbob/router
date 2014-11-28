<?php namespace Nebula;

/**
 * Class Routes
 *
 * @author lbob created at 2014/11/27 10:11
 */
class Router
{

    const PATTERN_BASE = '[a-z][a-z0-9\_\-]*';
    const PATTERN_TOKEN = '#\{([^\{\}]+)\}#i';
    const PATTERN_ID = '[\d]+';
    const PATTERN_NAME = '[a-z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';
    const PATTERN_TAIL = '.+';
    const PATTERN_NAMESPACE = '#^\/?([^\{\}]+[a-zA-Z0-9])#i';

    const PATTERN_TOKEN_LESS = '#\/(\{[^\{\}]+\}\?)#i';
    const PATTERN_TOKEN_NOT_LESS = '#\/(\{[^\{\}]+\})#i';

    /**
     * @var array 映射表，用于对路由进行配置
     *
     * 包括以下字段：
     * expression
     * pattern
     * handler
     * default
     * filter
     */
    public $mappings = array();

    public $baseMappings
        = array(
            'default' => array(
                'expression' => '/{controller}?/{action}?/{id}?',
                'default'    => array(
                    'controller' => 'home',
                    'action'     => 'index'
                )
            )
        );

    /**
     * @var array Nebula\Route 编译 mappings 之后得到的路由表
     *
     * 包括以下字段：
     * expression: 原表达式解释成用于匹配URL的正则表达式
     * patterns
     * tokens: token集合
     * beforeHandlers
     * matchedHandlers
     * afterHandlers
     * defaultValues
     * beforeFilters
     * afterFilters
     */
    private $routes = array();

    private $defaultTokenPatterns
        = array(
            'controller' => self::PATTERN_BASE,
            'action'     => self::PATTERN_BASE,
            'id'         => self::PATTERN_ID,
            'name'       => self::PATTERN_NAME,
            'tail'       => self::PATTERN_TAIL,
        );

    /**
     * @var \Nebula\RouteResult
     */
    public $routeResult;

    /**
     * @var array Nebula\Filter
     */
    private $filters;

    private $isAbort = false;

    /**
     * @var string
     */
    private $filterDir;

    private static $instance;

    private $timestamp = -1;

    private $source;

    /**
     * @var \Nebula\RouteCache
     */
    private $routeCache;

    public function __construct()
    {
        if (isset($this->baseMappings) && !empty($this->baseMappings)) {
            foreach ($this->baseMappings as $key => $value) {
                if (!array_key_exists($key, $this->mappings)) {
                    $this->mappings[$key] = $value;
                }
            }
        }
        $this->routeCache = new RouteCache();
    }

    public static function getInstance($configDir, $filterDir)
    {
        if (!isset(self::$instance)) {
            self::$instance = self::make($configDir, $filterDir);
        }
        return self::$instance;
    }

    private static function make($configDir, $filterDir)
    {
        if (!isset($router)) {
            $router = new Router();
        }
        if (isset($configDir)) {
            if (is_file($configDir) && is_readable($configDir)) {
                $router->timestamp = filemtime($configDir);
                $router->source    = $configDir;
                require $configDir;
            }
        }
        if (isset($filterDir)) {
            $router->filterDir = $filterDir;
        }
        return $router;
    }

    private function getBaseRouteData($mappingName, $mapping)
    {
        if (!$this->routeCache->isCacheExpired($this->timestamp)) {
            $data = $this->routeCache->getData($mappingName);
            if (isset($data)) return $data;
        }

        if (array_key_exists('expression', $mapping)) {
            $expression = $mapping['expression'];
            $tokens     = $this->getTokens($expression);
            $patterns   = $this->getTokenPatterns($mappingName, $tokens);

            //namespace
            $namespace = null;
            if (preg_match(self::PATTERN_NAMESPACE, $expression, $matches)) {
                if (!empty($matches[1])) $namespace = $matches[1];
            }

            $result = array(
                $this->parseExpression($expression, $tokens, $patterns),
                $patterns,
                $tokens,
                $namespace
            );
            $this->routeCache->setData($mappingName, $result);
            return $result;
        }
        return null;
    }

    public function compileRoutes()
    {
        unset($this->routes);
        $this->routes = array();
        $this->routeCache->readCache();
        foreach ($this->mappings as $mappingName => $mapping) {
            $route              = new Route();
            $route->mappingName = $mappingName;

            //expression, patterns, tokens 能缓存的也就是这些数据了
            list($expression, $patterns, $tokens, $namespace) = $this->getBaseRouteData($mappingName, $mapping);
            $route->namespace  = $namespace;
            $route->expression = $expression;
            $route->patterns   = $patterns;
            $route->tokens     = $tokens;

            //handler
            if (array_key_exists('handler', $mapping)) {
                foreach ($mapping['handler'] as $handlerName => $handler) {
                    if ($handlerName === 'before') $beforeHandlers[] = $handler;
                    if ($handlerName === 'matched') $matchedHandlers[] = $handler;
                    if ($handlerName === 'after') $afterHandlers[] = $handler;
                }
                $route->beforeHandlers  = isset($beforeHandlers) ? $beforeHandlers : null;
                $route->matchedHandlers = isset($matchedHandlers) ? $matchedHandlers : null;
                $route->afterHandlers   = isset($afterHandlers) ? $afterHandlers : null;
            }

            //defaultValues
            if (array_key_exists('default', $mapping)) {
                $route->defaultValues = $mapping['default'];
            }

            //beforeFilters, afterFilters
            if (array_key_exists('filter', $mapping)) {
                foreach ($mapping['filter'] as $filterName => $filters) {
                    if ($filterName === 'before') $beforeFilters[] = $filters;
                    if ($filterName === 'after') $afterFilters[] = $filters;
                }
                $route->beforeFilters = isset($beforeFilters) ? $beforeFilters : null;
                $route->afterFilters  = isset($afterFilters) ? $afterFilters : null;
            }

            $this->routes[$mappingName] = $route;
        }
        $this->routeCache->writeCache();
    }

    public function registerIsCacheExpiredHandler($handler)
    {
        $this->routeCache->registerIsExpiredHandler($handler);
    }

    public function registerReadCacheHandler($handler)
    {
        $this->routeCache->registerReadCacheHandler($handler);
    }

    public function registerWriteCacheHandler($handler)
    {
        $this->routeCache->registerWriteCacheHandler($handler);
    }

    public function routing()
    {
        if (func_num_args() > 0)
            $url = func_get_arg(0);
        else
            $url = $_SERVER['REQUEST_URI'];

        $this->compileRoutes();
        $this->routeResult = $this->match($url);
        if ($this->routeResult->isMatched === true) {
            /**
             * @var $route \Nebula\Route
             */
            $route = $this->routes[$this->routeResult->mappingName];

            //beforeFilters
            $this->invokeFilters($route->beforeFilters);
            if ($this->isAbort()) return;

            //beforeHandlers
            $this->invokeHandlers($route->beforeHandlers);
            if ($this->isAbort()) return;

            //matchedHandlers
            $this->invokeHandlers($route->matchedHandlers);
            if ($this->isAbort()) return;

            //afterFilters
            $this->invokeFilters($route->afterFilters);
            if ($this->isAbort()) return;

            //afterHandlers
            $this->invokeHandlers($route->afterHandlers);
            if ($this->isAbort()) return;
        }
    }

    public function isMatched()
    {
        return $this->routeResult->isMatched;
    }

    public function registerFilter($name, $handler)
    {
        if (isset($name) && isset($handler)) {
            $this->filters[$name] = new Filter($name, $this->getFilterPath($name), $handler);
        }
    }

    public function getRoutes()
    {
        return $this->routes;
    }

    private function getFilterPath($filter)
    {
        return $this->filterDir . DIRECTORY_SEPARATOR . $filter . '.php';
    }

    private function invokeHandlers($handlers)
    {
        if (isset($handlers)) {
            foreach ($handlers as $handler) {
                if (is_callable($handler)) {
                    if ($handler($this->routeResult->params) === false) {
                        $this->abort();
                        return;
                    }
                }
            }
        }
    }

    private function invokeFilters($filters)
    {
        if (isset($filters)) {
            foreach ($filters as $filter) {
                $handlers = array();
                if (is_callable($filter)) {
                    $handlers[] = $filter;
                }
                if (is_string($filter)) {
                    if (!array_key_exists($filter, $this->filters)) {
                        $path = $this->getFilterPath($filter);
                        if (is_file($path) && is_readable($path))
                            require $path;
                        if (!array_key_exists($filter, $this->filters)) {
                            throw new \InvalidArgumentException("Can't find filter [$filter] in route [" . $this->routeResult->mappingName . "]");
                        }
                    } else {
                        $handlers = $this->filters[$filter]->handlers;
                    }
                }
                $this->invokeHandlers($handlers);
                if ($this->isAbort()) return;
            }
        }
    }

    private function match($url)
    {
        if (isset($url)) {
            $params = array();
            /**
             * @var $route Route
             */
            foreach ($this->routes as $route) {
                if (preg_match_all($route->expression, $url, $matches, PREG_SET_ORDER)) {
                    if (isset($route->namespace))
                        $params['namespace'] = $route->namespace;
                    $match = $matches[0];
                    foreach ($route->tokens as $token) {
                        if (!empty($match[$token]))
                            $params[$token] = $match[$token];
                    }
                    return new RouteResult($url, $route->mappingName, true, $params);
                }
            }
        }
        return null;
    }

    private function parseExpression($expression, $tokens, $patterns)
    {
        $expression = preg_replace('#(/+)$#i', '', $expression);
        $expression = preg_replace(self::PATTERN_TOKEN_LESS, '\/?$1', $expression);
        $expression = preg_replace(self::PATTERN_TOKEN_NOT_LESS, '\/$1', $expression);
        foreach ($tokens as $token) {
            $expression = str_replace('{' . $token . '}', '(?<' . $token . '>' . $patterns[$token] . ')', $expression);
        }
        return '#^' . $expression . '\/?(?:\?(?<tail>' . self::PATTERN_TAIL . '))?$#i';
    }

    private function getTokens($expression)
    {
        $tokens = array();
        if (preg_match_all(self::PATTERN_TOKEN, $expression, $tokenMatches, PREG_SET_ORDER)) {
            foreach ($tokenMatches as $tokenMatch) {
                $tokens[] = $tokenMatch[1];
            }
        }
        return $tokens;
    }

    private function getTokenPatterns($mappingName, $tokens)
    {
        $allPatterns = array();
        if (array_key_exists('pattern', $this->mappings[$mappingName])) {
            $allPatterns = $this->mappings[$mappingName]['pattern'];
            foreach ($this->defaultTokenPatterns as $key => $value) {
                if (!array_key_exists($key, $allPatterns))
                    $allPatterns[$key] = $value;
            }
        } else {
            $allPatterns = $this->defaultTokenPatterns;
        }

        $patterns = array();
        foreach ($tokens as $token) {
            if (array_key_exists($token, $allPatterns)) {
                $patterns[$token] = $allPatterns[$token];
            } else {
                throw new \InvalidArgumentException("Missing token pattern [$token] in mapping [$mappingName].");
            }
        }
        return $patterns;
    }

    private function abort()
    {
        $this->isAbort = true;
    }

    private function isAbort()
    {
        return $this->isAbort;
    }
}
 