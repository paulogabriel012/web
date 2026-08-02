<?php

declare(strict_types=1);

namespace App\Domain\Usage;

use App\Domain\Subscription\EntitlementService;
use App\Models\UsageDaily;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Current usage counters for the desktop client.
 *
 * The quota day is always UTC so every client sees the same boundary.
 */
final class UsageService
{
    public function __construct(
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * Current period usage snapshot.
     *
     * @return array{
     *     period: array{type: string, starts_at: string, ends_at: string},
     *     counters: array<string, array{used: int, limit: int|null, remaining: int|null}>
     * }
     */
    public function snapshot(User $user): array
    {
        $periodStart = $this->periodStart();
        $limits = $this->entitlements->resolve($user)->limits;

        $sessionsUsed = $this->integerValue(UsageDaily::query()
            ->where('user_id', $user->id)
            ->where('period_start', $periodStart)
            ->value('sessions_started'));

        $sessionLimit = isset($limits['sessions_per_day']) ? (int) $limits['sessions_per_day'] : null;
        $profileLimit = isset($limits['profiles']) ? (int) $limits['profiles'] : null;

        return [
            'period' => [
                'type' => 'utc_day',
                'starts_at' => $periodStart->toIso8601String(),
                'ends_at' => $this->periodEnd($periodStart)->toIso8601String(),
            ],
            'counters' => [
                'sessions' => [
                    'used' => $sessionsUsed,
                    'limit' => $sessionLimit,
                    'remaining' => $sessionLimit === null ? null : max($sessionLimit - $sessionsUsed, 0),
                ],
                'profiles' => [
                    'used' => 0,
                    'limit' => $profileLimit,
                    'remaining' => $profileLimit,
                ],
            ],
        ];
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
     * Normalize a database scalar returned by the query builder.
     */
    private function integerValue(mixed $value): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT);

        return $result === false ? 0 : $result;
    }
}
