<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Existing authorization tests exercise legacy multi-company fixtures.
        config(['erp.single_user_mode' => false]);
    }
}
