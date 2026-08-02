<?php

declare(strict_types=1);

namespace App\Domain\Sessions;

use App\Domain\Api\ApiException;
use App\Domain\Subscription\EntitlementService;
use App\Enums\Api\ErrorCode;
use App\Enums\Sessions\SessionResult;
use App\Enums\Sessions\SessionStatus;
use App\Models\Device;
use App\Models\SessionGrant;
use App\Models\UsageDaily;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Owns the quota boundary for paid browser operations.
 *
 * Authorization is atomic: the daily counter is incremented with a
 * conditional UPDATE in the same transaction that creates the session grant,
 * so concurrent requests cannot exceed a finite quota. Replays of the same
 * idempotency key return the original grant without double-counting.
 */
final class SessionAuthorizationService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Atomically check entitlement and reserve a browser session.
     *
     * @throws ApiException
     */
    public function authorize(
        User $user,
        Device $device,
        string $operation,
        string $idempotencyKey,
        ?string $clientVersion = null,
    ): SessionAuthorizationResult {
        $replayed = $this->findReplay($user, $idempotencyKey);

        if ($replayed !== null) {
            return $this->resultFor($replayed, $user, replayed: true);
        }

        $this->ensureAllowed($user, $device, $clientVersion);

        $periodStart = $this->periodStart();
        $limit = $this->sessionLimit($user);
        $configuredLease = filter_var(config('browser.session.lease_minutes', 30), FILTER_VALIDATE_INT);
        $leaseMinutes = $configuredLease === false ? 30 : $configuredLease;

        try {
            $grant = DB::transaction(function () use (
                $user,
                $device,
                $operation,
                $idempotencyKey,
                $periodStart,
                $limit,
                $leaseMinutes,
            ): SessionGrant {
                $this->ensureDailyRow($user->id, $periodStart);

                $where = ['user_id' => $user->id, 'period_start' => $periodStart];

                if ($limit === null) {
                    DB::table('usage_daily')->where($where)->increment('sessions_started');
                } else {
                    $incremented = DB::table('usage_daily')
                        ->where($where)
                        ->where('sessions_started', '<', $limit)
                        ->increment('sessions_started');

                    if ($incremented === 0) {
                        throw new QuotaExceededException(
                            'The daily session limit has been reached.',
                            [
                                'limit' => $limit,
                                'used' => $this->integerValue(
                                    DB::table('usage_daily')->where($where)->value('sessions_started'),
                                ),
                                'reset_at' => $this->periodEnd($periodStart)->toIso8601String(),
                            ],
                        );
                    }
                }

                return SessionGrant::create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'operation' => $operation,
                    'idempotency_key' => $idempotencyKey,
                    'status' => SessionStatus::Reserved,
                    'authorized_at' => now(),
                    'expires_at' => now()->addMinutes($leaseMinutes),
                ]);
            });
        } catch (Throwable $throwable) {
            $replay = $this->findReplay($user, $idempotencyKey);

            if ($replay !== null) {
                return $this->resultFor($replay, $user, replayed: true);
            }

            throw $throwable;
        }

        return $this->resultFor($grant, $user, replayed: false);
    }

    /**
     * Mark a session as finished. Idempotent: repeated calls return the
     * already-finished grant unchanged.
     */
    public function finish(SessionGrant $grant, SessionResult $result): SessionGrant
    {
        if (! $grant->status->isActive()) {
            return $grant;
        }

        $grant->update([
            'status' => $result->toStatus(),
            'finished_at' => now(),
        ]);

        return $grant->fresh() ?? $grant;
    }

    /**
     * The current UTC quota day boundary.
     */
    public function periodStart(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC')->startOfDay();
    }

    /**
     * The exclusive end of the current UTC quota day.
     */
    public function periodEnd(CarbonImmutable $periodStart): CarbonImmutable
    {
        return $periodStart->addDay();
    }

    /**
     * Look up a previously granted session by idempotency key.
     */
    private function findReplay(User $user, string $idempotencyKey): ?SessionGrant
    {
        return SessionGrant::query()
            ->where('user_id', $user->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    /**
     * Reject unauthorized users and unsupported clients before touching usage.
     *
     * @throws ApiException
     */
    private function ensureAllowed(User $user, Device $device, ?string $clientVersion): void
    {
        $decision = $this->entitlements->canRun($user, $device);

        if (! $decision->allowed) {
            throw ApiException::make(
                $decision->reason ?? ErrorCode::SubscriptionRequired,
                $this->accessMessage($decision->reason ?? ErrorCode::SubscriptionRequired),
            );
        }

        if ($clientVersion !== null && ! $this->isClientVersionSupported($clientVersion)) {
            throw ApiException::make(
                ErrorCode::ClientVersionUnsupported,
                'This browser version is no longer supported.',
            );
        }
    }

    /**
     * The configured daily session limit for the user's plan (null = unlimited).
     */
    private function sessionLimit(User $user): ?int
    {
        $limits = $this->entitlements->resolve($user)->limits;

        return isset($limits['sessions_per_day']) ? (int) $limits['sessions_per_day'] : null;
    }

    /**
     * Create the daily usage row if it does not exist yet (race-safe).
     */
    private function ensureDailyRow(int $userId, CarbonImmutable $periodStart): void
    {
        DB::table('usage_daily')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'period_start' => $periodStart,
            'sessions_started' => 0,
            'profiles_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Build the result payload for a grant, reading usage inside the period.
     */
    private function resultFor(SessionGrant $grant, User $user, bool $replayed): SessionAuthorizationResult
    {
        $periodStart = $this->periodStart();
        $limit = $this->sessionLimit($user);

        $used = $this->integerValue(UsageDaily::query()
            ->where('user_id', $grant->user_id)
            ->where('period_start', $periodStart)
            ->value('sessions_started'));

        return new SessionAuthorizationResult(
            grant: $grant,
            replayed: $replayed,
            used: $used,
            limit: $limit,
            remaining: $limit === null ? null : max($limit - $used, 0),
            resetAt: $this->periodEnd($periodStart),
        );
    }

    /**
     * Human-readable message for access denials (never branched on by clients).
     */
    private function accessMessage(ErrorCode $reason): string
    {
        return match ($reason) {
            ErrorCode::EmailNotVerified => 'The account email has not been verified.',
            ErrorCode::DeviceRevoked => 'This device has been revoked.',
            ErrorCode::PaymentPastDue => 'The subscription payment is past due.',
            default => 'An active subscription is required to run the browser.',
        };
    }

    /**
     * Version support policy: the current release is the minimum accepted.
     * Extend with a grace window when a compatibility policy is defined.
     */
    private function isClientVersionSupported(string $version): bool
    {
        $minimum = config('browser.minimum_client_version');

        if (! is_string($minimum) || $minimum === '') {
            return true;
        }

        return version_compare($version, $minimum, '>=');
    }

    /**
     * Normalize a database scalar returned by the query builder.
     */
    private function integerValue(mixed $value): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT);

        return $result === false ? 0 : $result;
    }
}
