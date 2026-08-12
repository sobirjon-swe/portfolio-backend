<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;

abstract class TestCase extends BaseTestCase
{
    /**
     * Turn throttling off for the current test.
     *
     * Rate limiters are keyed by IP and every test shares 127.0.0.1, so a test
     * that fires many requests at one endpoint will trip them. Throttling stays
     * ON by default — it is a security control, and disabling it suite-wide
     * would leave the limiters themselves untested.
     */
    protected function withoutRateLimiting(): static
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        return $this;
    }
}
