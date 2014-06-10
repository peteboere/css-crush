<?php

namespace CssCrush\UnitTest;

class ApiTest extends \PHPUnit_Framework_TestCase
{
    protected $sample;
    protected $sampleFile;
    protected $sampleExpected;

    public function setUp()
    {
        $this->sample = ".foo {bar: baz;}";
        $this->sampleExpected = ".foo{bar:baz}";
        $this->sampleFile = temp_file($this->sample);
        $this->originalWd = getcwd();
        chdir(dirname($this->sampleFile));
    }

    public function tearDown()
    {
        chdir($this->originalWd);
    }

    public function testString()
    {
        $this->assertEquals($this->sampleExpected, csscrush_string($this->sample));
    }

    public function testInline()
    {
        $this->assertEquals("<style>$this->sampleExpected</style>\n", csscrush_inline($this->sampleFile));
        $this->assertEquals("<style type=\"text/css\" id=\"foo\">$this->sampleExpected</style>\n", csscrush_inline($this->sampleFile, null, array(
            'type' => 'text/css',
            'id' => 'foo',
        )));
    }

    public function testFile()
    {
        $test_dir = dirname($this->sampleFile);
        $test_file = csscrush_file($this->sampleFile, array(
            'versioning' => false,
            'cache' => false,
            'doc_root' => $test_dir,
            'boilerplate' => false,
        ));
        $filepath = "$test_dir$test_file";

        $this->assertEquals($this->sampleExpected, file_get_contents($filepath));
    }

    public function testTag()
    {
        $test_dir = dirname($this->sampleFile);
        $base_options = array(
            'versioning' => false,
            'cache' => false,
            'doc_root' => $test_dir,
            'boilerplate' => false,
        );

        $url = '/' . basename($this->sampleFile) . '.crush.css';
        $test_tag = csscrush_tag($this->sampleFile, $base_options);

        $this->assertEquals("<link rel=\"stylesheet\" href=\"$url\" media=\"all\" />\n", $test_tag);

        $test_tag = csscrush_tag($this->sampleFile, $base_options, array('media' => 'print', 'id' => 'foo'));

        $this->assertEquals("<link rel=\"stylesheet\" href=\"$url\" media=\"print\" id=\"foo\" />\n", $test_tag);
    }

    public function testStat()
    {
        $sample = <<<TPL
.foo {bar: baz;}
.invalid {}
one, two, three, four, five {color: purple;}
TPL;

        csscrush_string($sample, array(
            'minify' => false,
            'trace' => array( 'selector_count', 'rule_count' ),
            'disable' => array( 'all' ),
        ));

        $stats = csscrush_stat();

        $this->assertEquals(6, $stats['selector_count']);
        $this->assertEquals(2, $stats['rule_count']);
        $this->assertEquals(array(), $stats['vars']);
        $this->assertEquals(array(), $stats['errors']);
        $this->assertTrue(isset($stats['compile_time']));
    }

    public function testGetSet()
    {
        csscrush_set('config', function ($config) {
            $config->foo = 'bar';
        });
        $this->assertEquals('bar', csscrush_get('config', 'foo'));

        csscrush_set('config', array(
            'hello' => 'world',
        ));
        $this->assertEquals('world', csscrush_get('config', 'hello'));

        $this->assertInstanceOf('stdClass', csscrush_get('config'));

        $this->assertInstanceOf('CssCrush\Options', csscrush_get('options'));

        csscrush_set('options', array('enable' => 'property-sorter'));

        $this->assertContains('property-sorter', csscrush_get('options', 'enable'));

        csscrush_set('options', array('enable' => array()));
    }

    public function testAddFunction()
    {
        csscrush_add_function(null);

        $this->assertEquals(array(), csscrush_add_function());

        csscrush_add_function('foo', function ($arguments) {return 'bar';});

        $functions = csscrush_add_function();
        $this->assertTrue(is_callable($functions['foo']['callback']));

        csscrush_add_function('foo', null);

        $functions = csscrush_add_function();
        $this->assertFalse(isset($functions['foo']));
    }
}
