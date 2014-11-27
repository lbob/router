<?php namespace Nebula;
use Prophecy\Exception\InvalidArgumentException;

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
     * @var array 编译 mappings 之后得到的路由表
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

    private $isMatched = false;

    private $matchedMappingName;

    public function __construct()
    {
        if (isset($this->baseMappings) && !empty($this->baseMappings)) {
            foreach ($this->baseMappings as $key => $value) {
                if (!array_key_exists($key, $this->mappings)) {
                    $this->mappings[$key] = $value;
                }
            }
        }
    }

    public function compileRoutes()
    {
        unset($this->routes);
        $this->routes = array();
        foreach ($this->mappings as $mappingName => $mapping) {
            $route              = new Route();
            $route->mappingName = $mappingName;

            //expression, patterns, tokens 能缓存的也就是这些数据了
            if (array_key_exists('expression', $mapping)) {
                $expression = $mapping['expression'];
                $tokens     = $this->getTokens($expression);
                $patterns   = $this->getTokenPatterns($mappingName, $tokens);

                //namespace
                if (preg_match(self::PATTERN_NAMESPACE, $expression, $matches)) {
                    if (!empty($matches[1])) $route->namespace = $matches[1];
                }

                $route->expression = $this->parseExpression($expression, $tokens, $patterns);
                $route->patterns   = $patterns;
                $route->tokens     = $tokens;
            }

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
    }

    public function routing()
    {
        if (func_num_args() > 0)
            $url = func_get_arg(0);
        else
            $url = $_SERVER['REQUEST_URI'];

        $this->compileRoutes();
        list($isMatched, $mappingName, $params) = $this->match($url);
        if ($isMatched === true) {
            $this->isMatched          = true;
            $this->matchedMappingName = $mappingName;

            //beforeFilters

            //beforeHandlers

            //matchedHandlers

            //afterFilters

            //afterHandlers
        }
        var_dump(array($isMatched, $mappingName, $params));
    }

    public function isMatched()
    {
        return $this->isMatched;
    }

    public function getRoutes()
    {
        return $this->routes;
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
                    return array(true, $route->mappingName, $params);
                }
            }
        }
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
                throw new InvalidArgumentException("Missing token pattern [$token] in mapping [$mappingName].");
            }
        }
        return $patterns;
    }
}
 