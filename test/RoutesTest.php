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
        $this->router = new \Nebula\Router();
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
        $this->router->mappings['test'] = array(
            'expression' => '/{controller}/{action}?/{id}?//////'
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('test', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<id>[\d]+)?\/?(?:\?(?<tail>.+))?$#i', $routes['test']->expression);
    }

    public function testCompileRoutesName()
    {
        $this->router->mappings['test'] = array(
            'expression' => '/{controller}/{action}?/{name}?//////'
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('test', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<name>[a-z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)?\/?(?:\?(?<tail>.+))?$#i', $routes['test']->expression);
    }

    public function testCompileRoutesCustomerPattern()
    {
        $this->router->mappings['test'] = array(
            'expression' => '/{controller}/{action}?/{test}?//////',
            'pattern' => array(
                'test' => '\d{4}'
            )
        );

        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertArrayHasKey('test', $routes);
        $this->assertEquals('#^\/(?<controller>[a-z][a-z0-9\_\-]*)\/?(?<action>[a-z][a-z0-9\_\-]*)?\/?(?<test>\d{4})?\/?(?:\?(?<tail>.+))?$#i', $routes['test']->expression);
    }

    public function testNamespace()
    {
        $this->router->mappings['test'] = array(
            'expression' => '/admin/uav/airline/{controller}/{action}?/{id}?//////',
        );
        $this->router->compileRoutes();
        $routes = $this->router->getRoutes();

        $this->assertEquals('admin/uav/airline', $routes['test']->namespace);
    }

    public function testRouting()
    {
        $this->router->mappings['test'] = array(
            'expression' => '/admin/uav/{controller}/{action}?/{test}?//////',
            'pattern' => array(
                'test' => '\d{4}'
            ),
            'handler' => array(
                'before' => function($params) {return false;}
            )
        );

        $this->router->compileRoutes();
        $this->router->routing('/admin/uav/post/edit/1442');

        $this->assertTrue($this->router->isMatched());

        //var_dump($this->router->getRoutes());
    }
}

 