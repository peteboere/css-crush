<?php

namespace CssCrush\UnitTest;

use CssCrush\Version;

class VersionTest extends \PHPUnit\Framework\TestCase
{
    public function test__toString()
    {
        $version = new Version('2.8.5');
        $version->minor = 9;
        $version->extra = 'beta';
        $this->assertEquals('v2.9.5-beta', (string) $version);

        unset($version->extra);
        $this->assertEquals('v2.9.5', (string) $version);
    }

    public function testCompare()
    {
        $version = new Version('1.8.5-beta');
        $this->assertEquals(1, $version->compare('1'));
        $this->assertEquals(-1, $version->compare('2'));
        $this->assertEquals(0, $version->compare('1.8.5'));
    }

    public function testProperties()
    {
        $version = new Version('1.8.5');
        $this->assertEquals(1, $version->major);
        $this->assertEquals(8, $version->minor);
        $this->assertEquals(5, $version->patch);
    }

    public function testGitDescribe()
    {
        if ($version = Version::gitDescribe()) {
            $this->assertMatchesRegularExpression('~^
                v
                \d+\.
                \d+\.
                \d+
                (-(?:alpha|beta)\.\d+)?
                -\d+
                -g.+
            $~x', $version->__toString());
        }
        else {
            $this->markTestSkipped('Returned null');
        }
    }
}
