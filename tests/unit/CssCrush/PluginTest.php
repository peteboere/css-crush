<?php

namespace CssCrush\UnitTest;

use CssCrush\Plugin;
use CssCrush\Crush;

class PluginTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $dummy_plugin_dir = sys_get_temp_dir();
        Crush::$config->pluginDirs[] = $dummy_plugin_dir;

        $dummy_plugin = <<<TPL
<?php namespace CssCrush;

Plugin::register('dummy', array(
    'enable' => function () {
        define('DUMMY_ENABLE_TEST', true);
    },
    'disable' => function () {
        define('DUMMY_DISABLE_TEST', true);
    },
));
TPL;
        file_put_contents("$dummy_plugin_dir/dummy.php", $dummy_plugin);

        Plugin::$plugins = array();
    }

    public function tearDown()
    {
        Plugin::$plugins = array();
    }

    public function testInfo()
    {
        $info = Plugin::info();

        $this->assertArrayHasKey('svg', $info);
    }

    public function testParseDoc()
    {
        $test_path = Crush::$dir . '/plugins/svg.php';
        $result = Plugin::parseDoc($test_path);

        $this->assertContains('SVG', $result[0]);
    }

    public function testLoad()
    {
        Plugin::load('dummy');

        $this->assertArrayHasKey('dummy', Plugin::$plugins);

        Plugin::enable('dummy');

        $this->assertTrue(DUMMY_ENABLE_TEST);

        Plugin::disable('dummy');

        $this->assertTrue(DUMMY_DISABLE_TEST);
    }
}
