<?php

namespace CssCrush\UnitTest;

use CssCrush\Url;
use CssCrush\Crush;

class UrlTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        bootstrap_process(['minify' => false]);
    }

    public function testConstruct()
    {
        $url = new Url('http://www.public.com');
        $this->assertEquals('http', $url->protocol);
        $this->assertTrue($url->isAbsolute);
        $this->assertFalse($url->isRelative);
        $this->assertFalse($url->isRooted);
        $this->assertFalse($url->isData);

        $url = new Url('//www.public.com');
        $this->assertEquals('relative', $url->protocol);
        $this->assertTrue($url->isAbsolute);
        $this->assertFalse($url->isRelative);
        $this->assertFalse($url->isRooted);
        $this->assertFalse($url->isData);

        $url = new Url('local/resource.png');
        $this->assertNull($url->protocol);
        $this->assertFalse($url->isAbsolute);
        $this->assertTrue($url->isRelative);
        $this->assertFalse($url->isRooted);
        $this->assertFalse($url->isData);

        $url = new Url('/local/resource.png');
        $this->assertNull($url->protocol);
        $this->assertFalse($url->isAbsolute);
        $this->assertFalse($url->isRelative);
        $this->assertTrue($url->isRooted);
        $this->assertFalse($url->isData);

        $url = new Url('data:text/html');
        $this->assertEquals('data', $url->protocol);
        $this->assertFalse($url->isAbsolute);
        $this->assertFalse($url->isRelative);
        $this->assertFalse($url->isRooted);
        $this->assertTrue($url->isData);
    }

    public function testToString()
    {
        $url = new Url('resource/with(parens)');
        $this->assertEquals('url("resource/with(parens)")', (string) $url);

        $url = new Url('simple/url');
        $this->assertEquals('url(simple/url)', (string) $url);
    }

    public function testUpdate()
    {
        $url = new Url('simple/url');
        $update_url = 'different/url';
        $url->update($update_url);
        $this->assertEquals($update_url, $url->value);
    }

    public function testGetAbsolutePath()
    {
        $url_raw = 'simple/url';
        $url = new Url($url_raw);
        $this->assertEquals(Crush::$process->docRoot . "/$url_raw", $url->getAbsolutePath());
    }

    public function testPrepend()
    {
        $url = new Url('simple/url');
        $this->assertEquals('../simple/url', $url->prepend('../')->value);
    }

    public function testToRoot()
    {
        $url = new Url('simple/url');
        $this->assertEquals(Crush::$process->input->dirUrl . '/simple/url', $url->toRoot()->value);
    }

    public function testToData()
    {
        $test_filename = str_replace('\\', '_', __CLASS__) . '.svg';
        $test_fileurl = "/tests/unit/$test_filename";
        $test_filepath = Crush::$process->docRoot . $test_fileurl;

        if (is_writable(dirname($test_filepath))) {
            $svg = '<svg><path d="M0,0 h10 l-10,10z"/></svg>';
            file_put_contents($test_filepath, $svg);
            $url = new Url($test_fileurl);
            $url->toData();
            unlink($test_filepath);

            $this->assertEquals(
                'data:image/svg+xml;utf8,<svg><path d="M0,0 h10 l-10,10z"/></svg>',
                $url->value);
        }
        else {
            $this->markTestSkipped('Cannot write test SVG file to disk.');
        }

        $url = new Url('/tests/unit/dummy-data/tiny.png');
        $url->toData();
        $this->assertStringStartsWith(
            'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAASoAAAEqCAMAAACV5O0dAAAA',
            $url->value);
    }

    public function testSetType()
    {
        $url = new Url('simple/url');
        $url->setType('absolute');

        $this->assertTrue($url->isAbsolute);
        $this->assertFalse($url->isRelative);
        $this->assertFalse($url->isRooted);
        $this->assertFalse($url->isData);
    }

    public function testSimplify()
    {
        $url = new Url("/some/../path/../something.css");
        $this->assertEquals('/something.css', $url->simplify()->value);

        $url = new Url("/some/../..//..\path/../something.css");
        $this->assertEquals('/../../something.css', $url->simplify()->value);

        $url = new Url("../../../blah/../../something.css");
        $this->assertEquals('../../../../something.css', $url->simplify()->value);
    }
}
