<?php

namespace CssCrush\UnitTest;

use CssCrush\Logger;
use Psr\Log\LoggerInterface;

class LoggerDummy extends Logger implements LoggerInterface {}

class LoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $logger = new LoggerDummy();

        $this->assertTrue($logger instanceof LoggerInterface);
    }
}
