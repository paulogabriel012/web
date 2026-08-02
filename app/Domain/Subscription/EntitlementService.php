<?php

declare(strict_types=1);

namespace App\Domain\Subscription;

use App\Enums\Api\ErrorCode;
use App\Enums\Billing\Plan;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\Device;
use App\Models\User;

/**
 * Single source of truth for plan entitlements and browser access.
 *
 * Every API response and every middleware reads access decisions from here;
 * controllers must never re-implement subscription policy.
 */
final class EntitlementService
{
    /**
     * Resolve the full entitlement snapshot for a user.
     */
    public function resolve(User $user): Entitlements
    {
        $status = $this->statusFor($user);
        $plan = $this->planFor($user);
        $subscription = $user->subscription('default');

        return new Entitlements(
            status: $status,
            planId: $plan?->value,
            planName: $plan?->label(),
            planFeatures: $plan?->features() ?? [],
            limits: $plan?->quotas() ?? ['profiles' => null, 'sessions_per_day' => null, 'devices' => null],
            features: [
                'cloud_profiles' => false,
                'team_sharing' => $plan !== null && in_array('Team sharing', $plan->features(), true),
                'api_access' => $plan !== null && in_array('API access', $plan->features(), true),
            ],
            trialEndsAt: $subscription?->trial_ends_at,
            currentPeriodEndsAt: $subscription?->ends_at,
            cancelAtPeriodEnd: $subscription?->ends_at !== null,
            access: $this->canRun($user),
        );
    }

    /**
     * Decide whether a user may start a paid browser operation.
     */
    public function canRun(User $user, ?Device $device = null): AccessDecision
    {
        if (! $user->hasVerifiedEmail()) {
            return AccessDecision::deny(ErrorCode::EmailNotVerified);
        }

        if ($device !== null && $device->isRevoked()) {
            return AccessDecision::deny(ErrorCode::DeviceRevoked);
        }

        $status = $this->statusFor($user);

        if ($status->allowsRun()) {
            return AccessDecision::allow();
        }

        if ($status === SubscriptionStatus::PastDue && $this->pastDueWithinGrace($user)) {
            return AccessDecision::allow();
        }

        return AccessDecision::deny($status->denialCode());
    }

    /**
     * Get the normalized subscription status for a user.
     */
    public function statusFor(User $user): SubscriptionStatus
    {
        $subscription = $user->subscription('default');

        if ($subscription === null) {
            return SubscriptionStatus::None;
        }

        return SubscriptionStatus::tryFrom($subscription->stripe_status) ?? SubscriptionStatus::None;
    }

    /**
     * Resolve the configured plan that matches the user's Stripe price.
     */
    public function planFor(User $user): ?Plan
    {
        $subscription = $user->subscription('default');

        if ($subscription === null || $subscription->stripe_price === null) {
            return null;
        }

        foreach (Plan::catalog() as $plan) {
            if ($plan->priceId() === $subscription->stripe_price) {
                return $plan;
            }
        }

        return null;
    }

    /**
     * Whether a past-due subscription is still inside the configured grace period.
     */
    protected function pastDueWithinGrace(User $user): bool
    {
        $configuredGraceDays = filter_var(config('browser.access.past_due_grace_days', 0), FILTER_VALIDATE_INT);
        $graceDays = $configuredGraceDays === false ? 0 : $configuredGraceDays;

        if ($graceDays <= 0) {
            return false;
        }

        $subscription = $user->subscription('default');

        if ($subscription === null || $subscription->ends_at === null) {
            return false;
        }

        return $subscription->ends_at->isFuture();
    }
}
