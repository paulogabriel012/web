<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;

uses(RefreshDatabase::class);

it('completes the desktop authorization-code flow with PKCE', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $redirectUri = 'http://127.0.0.1:49152/oauth/callback';
    $client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: 'Browser test desktop',
        redirectUris: [$redirectUri],
        confidential: false,
    );

    $verifier = Str::random(64);
    $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    $state = Str::random(32);
    $authorizationQuery = [
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'browser:read browser:run device:manage',
        'state' => $state,
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];

    $approvalPage = $this->actingAs($user)->get('/oauth/authorize?'.http_build_query($authorizationQuery));
    $approvalPage->assertOk();
    preg_match('/name="auth_token" value="([^"]+)"/', $approvalPage->getContent(), $matches);
    expect($matches[1] ?? null)->not->toBeNull();

    $approval = $this->actingAs($user)->post('/oauth/authorize', [
        ...$authorizationQuery,
        'auth_token' => $matches[1],
        'approve' => '1',
    ]);
    $approval->assertRedirect();

    $callback = parse_url((string) $approval->headers->get('Location'));
    parse_str((string) ($callback['query'] ?? ''), $callbackQuery);

    expect($callbackQuery['state'] ?? null)->toBe($state)
        ->and($callbackQuery['code'] ?? null)->toBeString()->not->toBeEmpty();

    $token = $this->post('/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => $client->getKey(),
        'redirect_uri' => $redirectUri,
        'code' => $callbackQuery['code'],
        'code_verifier' => $verifier,
    ]);

    $token->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);

    $me = $this->withToken((string) $token->json('access_token'))
        ->getJson('/api/v1/me');

    $me->assertOk()
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.user.email_verified', true);

    $refreshed = $this->post('/oauth/token', [
        'grant_type' => 'refresh_token',
        'client_id' => $client->getKey(),
        'refresh_token' => $token->json('refresh_token'),
    ]);

    $refreshed->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token']);
});
