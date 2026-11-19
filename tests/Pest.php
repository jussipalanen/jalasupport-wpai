<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Unit tests run without a WordPress installation. WordPress functions are
| mocked with Brain Monkey; see tests/TestCase.php.
|
*/

require_once __DIR__ . '/Stubs/wordpress.php';

uses(Tests\TestCase::class)->in('Unit');
