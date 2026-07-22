<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_issued_token_authorizes_admin_write_endpoints(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        $this->withToken($token)
            ->postJson('/api/v1/technologies', ['name' => 'Laravel'])
            ->assertCreated();
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        $token = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/logout')->assertNoContent();

        // The token is revoked in the database.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset the auth guard so the next request re-resolves from scratch
        // (the guard memoizes the user within a single test run).
        $this->app['auth']->forgetGuards();

        // The same token must no longer authenticate.
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
