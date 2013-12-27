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
        $color->toHsl()->adjust(array(0,0,0,-20));
        $this->assertEquals('rgba(255,0,0,0.8)', (string) $color);
    }

    public function testGetHsl()
    {
        $color = new Color('red');
        $this->assertEquals(array(0, 1, .5, 1), $color->getHsl());
    }

    public function testGetHex()
    {
        $color = new Color('red');
        $this->assertEquals('#ff0000', $color->getHex());
    }

    public function testGetRgb()
    {
        $color = new Color('red');
        $this->assertEquals(array(255, 0, 0, 1), $color->getRgb());
    }

    public function testGetComponent()
    {
        $color = new Color('red');
        $this->assertEquals(255, $color->getComponent(0));
    }

    public function testSetComponent()
    {
        $color = new Color('red');
        $color->setComponent(0, 0);
        $this->assertEquals(0, $color->getComponent(0));
    }

    public function testColorSplit()
    {
        list($base_color, $opacity) = Color::colorSplit('red');
        $this->assertEquals('red', $base_color);
        $this->assertEquals(1, $opacity);
    }
}
