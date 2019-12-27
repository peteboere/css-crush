<?php

namespace CssCrush\UnitTest;

use CssCrush\Util;
use CssCrush\Tokens;
use CssCrush\Url;

class UtilTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->process = bootstrap_process(['minify' => false]);
        $this->tokens = $this->process->tokens;
    }

    public function testNormalizePath()
    {
        $this->assertEquals('/Some/crazy/Path', Util::normalizePath('C:\\Some\crazy/Path\\', true));
        $this->assertEquals('/Some/crazy/Path', Util::normalizePath('/\Some//./crazy\\\/Path/'));
        $this->assertEquals('sane/path', Util::normalizePath('./sane/path/'));
    }

    public function testHtmlAttributes()
    {
        $attributes = [
            'rel' => 'stylesheet',
            'id' => 'foo',
            'media' => 'screen',
        ];

        $this->assertEquals(
            ' rel="stylesheet" id="foo" media="screen"',
             Util::htmlAttributes($attributes));
        $this->assertEquals(
            ' id="foo" media="screen" rel="stylesheet"',
             Util::htmlAttributes($attributes, ['id', 'media', 'rel']));
    }

    public function testSimplifyPath()
    {
        $this->assertEquals('bar', Util::simplifyPath('foo/../bar'));
        $this->assertEquals('./../', Util::simplifyPath('./foo/../bar/../../'));
    }

    public function testVlqEncode()
    {
        $this->assertEquals('A', Util::vlqEncode(0));
        $this->assertEquals('C', Util::vlqEncode(1));
        $this->assertEquals('gB', Util::vlqEncode(16));
        $this->assertEquals('6H', Util::vlqEncode(125));
        $this->assertEquals('qmC', Util::vlqEncode(1125));
    }

    public function testStripCommentTokens()
    {
        $this->assertEquals('', Util::stripCommentTokens('?ca??cb?'));
    }

    public function testResolveUserPath()
    {
        $this->assertEquals(__FILE__, Util::resolveUserPath(__FILE__));
        $this->assertFalse(Util::resolveUserPath(__FILE__ . 'nothing'));

        // Relative path resolution.
        $original_path = getcwd();
        chdir(__DIR__);
        $this_filename = basename(__FILE__);
        // Case-insensitive file systems may normalize case.
        $this->assertEquals(strtolower(__FILE__), strtolower(Util::resolveUserPath($this_filename)));
        chdir($original_path);
    }

    public function testNormalizeWhiteSpace()
    {
        $this->assertEquals(
            '.foo[class]{rgb(0,0,0);}',
            Util::normalizeWhiteSpace(".foo[  class ] { \t rgb( \t0\n , 0, 0\r\n ) ; }  "));
    }

    public function testSplitDelimList()
    {
        $this->assertEquals(['foo(1,2)','3','4'], Util::splitDelimList("foo(1,2), 3,4"));
        $this->assertEquals([], Util::splitDelimList(" ; ; ", ['delim' => ';']));
        $this->assertEquals(['', ''], Util::splitDelimList(" , ", ['allow_empty_strings' => true]));
    }

    public function testGetLinkBetweenPaths()
    {
        $path1 = __DIR__;
        $path2 = realpath(__DIR__ . '/../../');
        $this->assertEquals('../../', Util::getLinkBetweenPaths($path1, $path2));
        $this->assertEquals('unit/CssCrush/', Util::getLinkBetweenPaths($path2, $path1));
    }

    public function testFilePutContents()
    {
        $test_file = sys_get_temp_dir() . '/' . str_replace('\\', '_', __CLASS__);
        $this->assertTrue(Util::filePutContents($test_file, 'Hello Mum'));
    }

    public function testRawValue()
    {
        $url1 = $this->tokens->add(new Url('foo.jpg'));
        $url2 = $this->tokens->add(new Url('foo.jpg'));
        $this->assertNotEquals($url1, $url2);
        $this->assertEquals(Util::rawValue($url1), Util::rawValue($url2));
        $this->assertEquals(Util::rawValue($url1), 'foo.jpg');

        $string1 = $this->tokens->add('"bar"', 's');
        $string2 = $this->tokens->add('"bar"', 's');
        $this->assertNotEquals($string1, $string2);
        $this->assertEquals(Util::rawValue($string1), Util::rawValue($string2));
        $this->assertEquals(Util::rawValue($string1), '"bar"');

        $this->assertEquals(Util::rawValue('foobar'), 'foobar');
        $this->assertNotEquals(Util::rawValue('foobar'), 'notFoobar');
    }

    public function testReadConfigFile()
    {
        $contents = <<<'NOW_DOC'
<?php

$plugins = ['svg', 'px2em'];
$boilerplate = true;
$unrecognised_option = true;

NOW_DOC;

        $options = Util::readConfigFile(temp_file($contents));
        $this->assertArrayHasKey('plugins', $options);
        $this->assertArrayNotHasKey('unrecognised_option', $options);
    }
}
