<?php

namespace CssCrush\UnitTest;

use CssCrush\Color;

class ColorTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $color = new Color('papayawhip');
        $this->assertEquals('#ffefd5', (string) $color);

        $color = new Color('#ccc');
        $this->assertEquals('#cccccc', (string) $color);

        $color = new Color('hsla(120,50%,50%,.8)');
        $this->assertEquals('rgba(64,191,64,0.8)', (string) $color);
    }

    public function testAdjust()
    {
        $color = new Color('rgb(255,0,0)');
        $color->toHsl()->adjust([0, 0, 0, -20]);
        $this->assertEquals('rgba(255,0,0,0.8)', (string) $color);
    }

    public function testGetHsl()
    {
        $color = new Color('red');
        $this->assertEquals([0, 1, .5, 1], $color->getHsl());
    }

    public function testGetHex()
    {
        $color = new Color('red');
        $this->assertEquals('#ff0000', $color->getHex());
    }

    public function testGetRgb()
    {
        $color = new Color('red');
        $this->assertEquals([255, 0, 0, 1], $color->getRgb());
    }

    public function testGetComponent()
    {
        $color = new Color('red');
        $this->assertEquals(255, $color->getComponent(0));
        $this->assertEquals(255, $color->getComponent('red'));
        $this->assertEquals(0, $color->getComponent('green'));
        $this->assertEquals(0, $color->getComponent('blue'));
        $this->assertEquals(1, $color->getComponent('alpha'));
    }

    public function testSetComponent()
    {
        $color = new Color('red');
        $color->setComponent(0, 0);
        $this->assertEquals(0, $color->getComponent(0));

        $color = new Color('#000000');
        $color->setComponent(3, '1');
        $this->assertEquals('#000000', $color->__toString());
        $color->setComponent(3, .5);
        $this->assertEquals('rgba(0,0,0,0.5)', $color->__toString());

        $color->setComponent('red', 100);
        $color->setComponent('green', 100);
        $color->setComponent('blue', 100);
        $color->setComponent('alpha', .1);
        $this->assertEquals('rgba(100,100,100,0.1)', $color->__toString());
    }

    public function testColorSplit()
    {
        list($base_color, $opacity) = Color::colorSplit('red');
        $this->assertEquals('red', $base_color);
        $this->assertEquals(1, $opacity);
    }
}
