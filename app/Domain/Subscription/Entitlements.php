<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Enums\Billing\SubscriptionStatus;
use Carbon\CarbonImmutable as Carbon;

/**
 * Full snapshot of what the account can do, ready for the desktop client.
 */
final readonly class Entitlements
{
    /**
     * @param  array<string, int|null>  $limits
     * @param  array<string, bool>  $features
     * @param  array<int, string>  $planFeatures
     */
    public function __construct(
        public SubscriptionStatus $status,
        public ?string $planId,
        public ?string $planName,
        public array $planFeatures,
        public array $limits,
        public array $features,
        public ?Carbon $trialEndsAt,
        public ?Carbon $currentPeriodEndsAt,
        public bool $cancelAtPeriodEnd,
        public AccessDecision $access,
    ) {}

    /**
     * Serialize the snapshot for API resources.
     *
     * @return array{
     *     status: string,
     *     plan: array{id: string, name: string, features: array<int, string>}|null,
     *     limits: array<string, int|null>,
     *     features: array<string, bool>,
     *     trial_ends_at: string|null,
     *     current_period_ends_at: string|null,
     *     cancel_at_period_end: bool,
     *     access: array{can_run_browser: bool, reason: string|null}
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'plan' => $this->planId !== null ? [
                'id' => $this->planId,
                'name' => $this->planName ?? $this->planId,
                'features' => $this->planFeatures,
            ] : null,
            'limits' => $this->limits,
            'features' => $this->features,
            'trial_ends_at' => $this->trialEndsAt?->toIso8601String(),
            'current_period_ends_at' => $this->currentPeriodEndsAt?->toIso8601String(),
            'cancel_at_period_end' => $this->cancelAtPeriodEnd,
            'access' => [
                'can_run_browser' => $this->access->allowed,
                'reason' => $this->access->reason?->value,
            ],
        ];
    }
}
