<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiters are keyed by IP; every test shares 127.0.0.1, so leave
        // throttling out of the suite (there is no throttle-specific test).
        $this->withoutMiddleware(ThrottleRequests::class);
    }
}
