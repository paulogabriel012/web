<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\BrowserRelease;
use App\Models\Device;
use App\Models\UsageDaily;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\TestCase;

final class BrowserApiTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_SCOPES = ['browser:read', 'browser:run', 'device:manage'];

    public function test_unauthenticated_errors_use_the_api_envelope_and_request_id(): void
    {
        $response = $this->getJson('/api/v1/me');

        $response
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer')
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('error.code', 'unauthorized')
            ->assertJsonPath('error.details', [])
            ->assertJsonPath('meta.request_id', fn (mixed $id): bool => is_string($id) && $id !== '');
    }

    public function test_a_verified_user_without_a_subscription_can_read_entitlements(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user, ['browser:read']);

        $response = $this->getJson('/api/v1/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email_verified', true)
            ->assertJsonPath('data.subscription.status', 'none')
            ->assertJsonPath('data.subscription.access.can_run_browser', false)
            ->assertJsonPath('data.subscription.access.reason', 'subscription_required')
            ->assertJsonPath('data.entitlements.limits.profiles', null)
            ->assertJsonPath('data.usage.sessions_today.used', 0)
            ->assertJsonStructure([
                'data' => ['server_time'],
                'meta' => ['request_id'],
            ]);
    }

    public function test_device_registration_is_idempotent_and_revocation_is_fail_closed(): void
    {
        $user = User::factory()->create();
        $this->authenticate($user, ['device:manage']);

        $payload = $this->devicePayload();
        $first = $this->postJson('/api/v1/devices', $payload);
        $deviceId = (string) $first->json('data.id');

        $first->assertCreated()->assertJsonMissingPath('data.installation_id');

        $second = $this->postJson('/api/v1/devices', [
            ...$payload,
            'name' => 'Renamed MacBook',
        ]);

        $second
            ->assertOk()
            ->assertJsonPath('data.id', $deviceId)
            ->assertJsonPath('data.name', 'Renamed MacBook');

        $this->getJson('/api/v1/devices')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/devices/{$deviceId}")->assertOk()->assertJsonPath('data.id', $deviceId);

        $this->deleteJson("/api/v1/devices/{$deviceId}")
            ->assertNoContent()
            ->assertHeader('X-Request-Id');

        $this->postJson("/api/v1/devices/{$deviceId}/heartbeat", ['app_version' => '1.0.1'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'device_revoked');
    }

    public function test_session_authorization_reserves_quota_once_and_finish_is_idempotent(): void
    {
        $user = User::factory()->subscribed('sub_starter', 'price_starter_monthly')->create();
        $this->authenticate($user, self::ALL_SCOPES);
        $device = Device::create([
            ...$this->devicePayload(),
            'user_id' => $user->id,
            'last_seen_at' => now(),
        ]);

        $headers = ['Idempotency-Key' => 'operation-123'];
        $payload = [
            'device_id' => $device->id,
            'operation' => 'browser_run',
            'client_version' => '1.0.0',
        ];

        $first = $this->postJson('/api/v1/sessions/authorize', $payload, $headers);
        $sessionId = (string) $first->json('data.session_id');

        $first
            ->assertOk()
            ->assertJsonPath('data.authorized', true)
            ->assertJsonPath('data.usage.used', 1)
            ->assertJsonPath('data.usage.limit', 50)
            ->assertJsonPath('data.usage.remaining', 49)
            ->assertJsonPath('data.plan', 'starter');

        $this->postJson('/api/v1/sessions/authorize', $payload, $headers)
            ->assertOk()
            ->assertJsonPath('data.session_id', $sessionId)
            ->assertJsonPath('data.usage.used', 1);

        $this->assertDatabaseCount('session_grants', 1);
        $this->assertDatabaseHas('usage_daily', [
            'user_id' => $user->id,
            'sessions_started' => 1,
        ]);

        $this->getJson("/api/v1/sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved');

        $this->postJson("/api/v1/sessions/{$sessionId}/finish", ['result' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.authorized', true)
            ->assertJsonPath('data.status', 'completed');

        $this->postJson("/api/v1/sessions/{$sessionId}/finish", ['result' => 'crashed'])
            ->assertOk()
            ->assertJsonPath('data.authorized', true)
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('session_grants', [
            'id' => $sessionId,
            'status' => 'completed',
        ]);
    }

    public function test_missing_idempotency_key_is_rejected(): void
    {
        $user = User::factory()->subscribed('sub_starter', 'price_starter_monthly')->create();
        $this->authenticate($user, self::ALL_SCOPES);
        $device = Device::create([
            ...$this->devicePayload(),
            'user_id' => $user->id,
            'last_seen_at' => now(),
        ]);

        $this->postJson('/api/v1/sessions/authorize', ['device_id' => $device->id])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.errors.idempotency_key.0', 'A chave de idempotência é obrigatória.');
    }

    public function test_quota_exceeded_returns_http_429_and_does_not_create_a_grant(): void
    {
        $user = User::factory()->subscribed('sub_starter', 'price_starter_monthly')->create();
        $this->authenticate($user, self::ALL_SCOPES);
        $device = Device::create([
            ...$this->devicePayload(),
            'user_id' => $user->id,
            'last_seen_at' => now(),
        ]);
        $periodStart = CarbonImmutable::now('UTC')->startOfDay();

        UsageDaily::create([
            'user_id' => $user->id,
            'period_start' => $periodStart,
            'sessions_started' => 50,
            'profiles_count' => 0,
        ]);

        $this->postJson('/api/v1/sessions/authorize', [
            'device_id' => $device->id,
            'operation' => 'browser_run',
        ], ['Idempotency-Key' => 'operation-over-limit'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'quota_exceeded')
            ->assertJsonPath('error.details.limit', 50)
            ->assertJsonPath('error.details.used', 50)
            ->assertJsonPath('error.details.reset_at', fn (mixed $value): bool => is_string($value));

        $this->assertDatabaseCount('session_grants', 0);
    }

    public function test_unverified_user_cannot_authorize_a_browser_operation(): void
    {
        $user = User::factory()->unverified()->subscribed('sub_unverified', 'price_starter_monthly')->create();
        $this->authenticate($user, self::ALL_SCOPES);
        $device = Device::create([
            ...$this->devicePayload(),
            'user_id' => $user->id,
            'last_seen_at' => now(),
        ]);

        $this->postJson('/api/v1/sessions/authorize', [
            'device_id' => $device->id,
            'operation' => 'browser_run',
        ], ['Idempotency-Key' => 'operation-unverified'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'email_not_verified');
    }

    public function test_foreign_devices_are_not_discoverable(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::create([
            ...$this->devicePayload(),
            'user_id' => $owner->id,
            'last_seen_at' => now(),
        ]);
        $this->authenticate($otherUser, ['device:manage']);

        $this->getJson("/api/v1/devices/{$device->id}")
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');
    }

    public function test_public_release_metadata_returns_a_short_lived_artifact_url(): void
    {
        Storage::fake('releases');
        Storage::disk('releases')->put('browser/invisiboll-1.1.0.dmg', 'artifact');

        BrowserRelease::create([
            'version' => '1.1.0',
            'platform' => 'macos',
            'architecture' => 'arm64',
            'artifact_key' => 'browser/invisiboll-1.1.0.dmg',
            'artifact_size' => 8,
            'sha256' => hash('sha256', 'artifact'),
            'minimum_version' => '1.0.0',
            'mandatory' => false,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/releases/latest?platform=macos&architecture=arm64&current_version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.version', '1.1.0')
            ->assertJsonPath('data.artifact.size_bytes', 8)
            ->assertJsonPath('data.artifact.sha256', hash('sha256', 'artifact'))
            ->assertJsonPath('data.artifact.download_url_expires_at', fn (mixed $value): bool => is_string($value));
    }

    public function test_openapi_marks_public_operations_as_public_and_names_the_bearer_scheme(): void
    {
        $document = $this->getJson('/api/v1/openapi.json')->assertOk()->json();

        self::assertSame('bearerAuth', array_key_first($document['security'][0]));
        self::assertSame('http', $document['components']['securitySchemes']['bearerAuth']['type']);
        self::assertSame([], $document['paths']['/v1/health']['get']['security']);
        self::assertSame([], $document['paths']['/v1/releases/latest']['get']['security']);
        self::assertArrayNotHasKey('security', $document['paths']['/v1/me']['get']);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function authenticate(User $user, array $scopes): void
    {
        Passport::actingAs($user, $scopes, 'api');
    }

    /**
     * @return array{installation_id: string, name: string, platform: string, architecture: string, app_version: string, os_version: string}
     */
    private function devicePayload(): array
    {
        return [
            'installation_id' => '3b3d2fbb-45bf-4d5a-bb76-6c4f0fbf8ec8',
            'name' => "Paulo's MacBook Pro",
            'platform' => 'macos',
            'architecture' => 'arm64',
            'app_version' => '1.0.0',
            'os_version' => '15.5',
        ];
    }
}
