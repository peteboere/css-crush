<?php

namespace CssCrush\UnitTest;

use CssCrush\ExtendArg;

class ExtendArgTest extends \PHPUnit\Framework\TestCase
{
    public function test__construct()
    {
        $extend_arg = new ExtendArg('.foo :hover!');
        $this->assertEquals('.foo :hover', $extend_arg->name);
        $this->assertEquals(':hover', $extend_arg->pseudo);
    }
}
