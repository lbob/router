<?php

/**
 * Class RoutesTest
 *
 * @author lbob created at 2014/11/27 10:10
 */
class RoutesTest extends PHPUnit_Framework_TestCase
{

    /**
     * @var Nebula\Router;
     */
    protected $router;

    protected function setUp()
    {
        require '../vendor/autoload.php';
        $this->router = \Nebula\Router::getInstance(__DIR__.'/../config/routes.php', __DIR__.'/../config/filter');

        $this->router->mappings['test'] = array(
            'expression' => '/admin/uav/{controller}/{action}?/{test}?//////',
            'pattern' => array(
                'test' => '\d{4}'
            ),
            'filter' => array(
                'before' => function($params) {
                    var_dump('testCache before filter');
                    $this->assertEquals('admin/uav', $params['namespace']);
                    $this->assertEquals('post', $params['controller']);
                    $this->assertEquals('edit', $params['action']);
                    $this->assertEquals('1442', $params['test']);
                },
                'after' => function($params) {
                    var_dump('testCache after filter');
                    $this->assertEquals('admin/uav', $params['namespace']);
                    $this->assertEquals('post', $params['controller']);
                    $this->assertEquals('edit', $params['action']);
                    $this->assertEquals('1442', $params['test']);
                }
            ),
            'handler' => array(
                'before' => function($params) {
                    var_dump('testHandler before handler');
                    $this->assertEquals('admin/uav', $params['namespace']);
                    $this->assertEquals('post', $params['controller']);
                    $this->assertEquals('edit', $params['action']);
                    $this->assertEquals('1442', $params['test']);
                },
                'matched' => function($params) {
                    var_dump('testHandler matched handler');
                    $this->assertEquals('admin/uav', $params['namespace']);
                    $this->assertEquals('post', $params['controller']);
                    $this->assertEquals('edit', $params['action']);
                    $this->assertEquals('1442', $params['test']);
                },
                'after' => function($params) {
                    var_dump('testHandler after handler');
                    $this->assertEquals('admin/uav', $params['namespace']);
                    $this->assertEquals('post', $params['controller']);
                    $this->assertEquals('edit', $params['action']);
                    $this->assertEquals('1442', $params['test']);
                }
            )
        );
    }

    protected function tearDown()
    {
        unset($this->router);
    }

    public function testBaseMappings()
    {
        $baseMappings = $this->router->baseMappings;

        $this->assertArrayHasKey('default', $baseMappings);
        $this->assertArrayHasKey('default', $baseMappings['default']);
        $this->assertEquals('/{controller}?/{action}?/{id}?', $baseMappings['default']['expression']);
    }

    public function testInitRoutes()
    {
        $this->assertArrayHasKey('default', $this->router->mappings);
        $this->assertArrayHasKey('default', $this->router->mappings['default']);
        $this->assertEquals('/{controller}?/{action}?/{id}?', $this->router->mappings['default']['expression']);
    }

    public function testCompileRoutesNormal()
    {
        $this->router->mappings['testCompileRoutesNormal'] = array(
            'expression' => '/{controller}/{action}?/{id}?//////'
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('testCompileRoutesNormal', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<id>[\d]+)?\/?(?:\?(?<tail>.+))?$#i', $routes['testCompileRoutesNormal']->expression);
    }

    public function testCompileRoutesName()
    {
        $this->router->mappings['testCompileRoutesName'] = array(
            'expression' => '/{controller}/{action}?/{name}?//////'
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('testCompileRoutesName', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<name>[a-z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?\/?(?:\?(?<tail>.+))?$#i', $routes['testCompileRoutesName']->expression);
    }

    public function testCompileRoutesCustomerPattern()
    {
        $this->router->mappings['testCompileRoutesCustomerPattern'] = array(
            'expression' => '/{controller}/{action}?/{test}?//////',
            'pattern' => array(
                'test' => '\d{4}'
            )
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('testCompileRoutesCustomerPattern', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<test>\d{4})?\/?(?:\?(?<tail>.+))?$#i', $routes['testCompileRoutesCustomerPattern']->expression);
    }

    public function testNamespace()
    {
        $this->router->mappings['testNamespace'] = array(
            'expression' => '/admin/uav/airline/{controller}/{action}?/{id}?//////',
        );
        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertEquals('admin/uav/airline', $routes['testNamespace']->namespace);
    }

    public function testRouting()
    {
        $this->router->mappings['testRouting'] = array(
            'expression' => '/admin/uav/{controller}/{action}?/{test}?//////',
            'pattern' => array(
                'test' => '\d{4}'
            ),
            'handler' => array(
                'before' => function($params) {return false;}
            )
        );

        $this->router->routing('/admin/uav/post/edit/1442');

        $this->assertTrue($this->router->isMatched());

        //var_dump($this->router->getRoutes());
    }

    public function testHandler()
    {
        $this->router->routing('/admin/uav/post/edit/1442');

        $this->assertTrue($this->router->isMatched());
    }

    public function testFilter()
    {
        $this->router->routing('/admin/uav/post/edit/1442');

        $this->assertTrue($this->router->isMatched());
    }

    public function testCache()
    {
        $this->router->registerReadCacheHandler(function() {
            if (is_file('D:\cache\routes.dat'))
                return unserialize(file_get_contents('D:\cache\routes.dat'));
        });
        $this->router->registerIsCacheExpiredHandler(function($timestamp) {
            if (is_file('D:\cache\routes.dat'))
                return filemtime('D:\cache\routes.dat') < $timestamp;
        });
        $this->router->registerWriteCacheHandler(function($data) {
            $content = serialize($data);
            $cache = 'D:\cache\routes.dat';
            $fp = fopen($cache, 'w+');
            fwrite($fp, $content);
            fclose($fp);
        });
        $this->router->routing('/admin/uav/post/edit/1442');
    }

    public function testReverse()
    {
        $url = $this->router->reverse('/post/edit', array(
            'id' => 111,
            'ee' => 'dd'
        ));
        $this->assertEquals('/post/edit/111?ee=dd', $url);
    }

    public function testReverseByRoute()
    {
        $url = $this->router->reverseByRoute('test', array(
            'id' => 111,
            'ee' => 'dd'
        ));
        $this->assertEquals('/post/edit/111?ee=dd', $url);
    }
}

 