<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use Illuminate\Support\Facades\Config;

enum Plan: string
{
    case Starter = 'starter';
    case Pro = 'pro';
    case Scale = 'scale';

    /**
     * Get the human-readable plan name.
     */
    public function label(): string
    {
        return Config::string("plans.{$this->value}.name", $this->value);
    }

    /**
     * Get the Stripe price ID for this plan.
     */
    public function priceId(): string
    {
        return Config::string("plans.{$this->value}.price");
    }

    /**
     * Get the monthly amount in cents.
     */
    public function amount(): int
    {
        return Config::integer("plans.{$this->value}.amount", 0);
    }

    /**
     * Get the list of plan features.
     *
     * @return array<int, string>
     */
    public function features(): array
    {
        return array_values(array_filter(
            Config::array("plans.{$this->value}.features", []),
            static fn (mixed $feature): bool => is_string($feature),
        ));
    }

    /**
     * Get the plan quotas (null means unlimited).
     *
     * @return array<string, int|null>
     */
    public function quotas(): array
    {
        $quotas = [];

        foreach (Config::array("plans.{$this->value}.quotas", []) as $key => $value) {
            if (is_string($key) && (is_int($value) || $value === null)) {
                $quotas[$key] = $value;
            }
        }

        return $quotas;
    }

    /**
     * Get the catalog of plans.
     *
     * @return array<int, Plan>
     */
    public static function catalog(): array
    {
        return self::cases();
    }

    /**
     * Get the plan values for validation.
     *
     * @return array<int, string>
     */
    public static function catalogValues(): array
    {
        return array_map(static fn (Plan $plan): string => $plan->value, self::cases());
    }
}
