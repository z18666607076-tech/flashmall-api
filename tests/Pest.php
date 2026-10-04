<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests boot the Laravel application, migrate MySQL, and flush Redis
| so catalog cache and rate-limiter state cannot leak between examples.
|
*/

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function () {
        Cache::flush();
    })
    ->in('Feature');

uses(TestCase::class, DatabaseMigrations::class)
    ->beforeEach(function () {
        Cache::flush();
    })
    ->in('Concurrency');
