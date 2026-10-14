<?php

use Mgamalhz\Foundation\Tests\TestCase;

uses(TestCase::class)->in(
    __DIR__,
    __DIR__.'/../packages/support/tests',
    __DIR__.'/../packages/audit/tests',
    __DIR__.'/../packages/lifecycle/tests',
);
