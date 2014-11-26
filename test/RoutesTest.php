<?php

/**
 * Class RoutesTest
 *
 * @author lbob created at 2014/11/26 19:24
 */
class RoutesTest extends PHPUnit_Framework_TestCase
{
    public function testTest()
    {
        $stack = array();
        $this->assertEquals(0, count($stack));

        array_push($stack, 'foo');
        $this->assertEquals('foo', $stack[count($stack) - 1]);
        $this->assertEquals(1, count($stack));

        $this->assertEquals('foo', array_pop($stack));
        $this->assertEquals(0, count($stack));
    }
}

 