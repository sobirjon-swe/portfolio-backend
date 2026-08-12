<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GuestResponseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A browser hitting a protected endpoint sends `Accept: text/html`, not
     * JSON. The Authenticate middleware then tries to build a redirect to
     * route('login') — which this API does not define — and the resulting
     * RouteNotFoundException surfaced as a 500 instead of a 401.
     */
    public function test_a_guest_without_a_json_accept_header_gets_401_not_500(): void
    {
        $this->get('/api/v1/messages', ['Accept' => 'text/html'])
            ->assertUnauthorized();
    }

    public function test_a_guest_asking_for_json_still_gets_401(): void
    {
        $this->getJson('/api/v1/messages')->assertUnauthorized();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function protectedEndpoints(): array
    {
        return [
            'messages' => ['get', '/api/v1/messages'],
            'admin posts' => ['get', '/api/v1/admin/posts'],
            'page view stats' => ['get', '/api/v1/page-views/stats'],
            'current user' => ['get', '/api/v1/me'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_protected_endpoint_answers_401_to_a_browser(string $method, string $uri): void
    {
        $this->{$method}($uri, ['Accept' => 'text/html'])->assertUnauthorized();
    }
}
