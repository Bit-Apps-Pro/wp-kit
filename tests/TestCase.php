<?php

namespace BitApps\WPKit\Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        resetWpKitTestState();
    }
}
