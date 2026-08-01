<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_a_subscription_are_redirected_to_the_plan_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('billing.plan'));
    }

    public function test_subscribed_users_can_access_the_dashboard(): void
    {
        $user = User::factory()->subscribed()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk();
    }

    public function test_users_on_trial_can_access_the_dashboard(): void
    {
        $user = User::factory()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_123',
            'stripe_status' => 'trialing',
            'stripe_price' => 'price_123',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk();
    }

    public function test_cancelled_subscriptions_do_not_grant_access(): void
    {
        $user = User::factory()->create();
        $user->subscriptions()->create([
            'type' => 'default',
            'stripe_id' => 'sub_123',
            'stripe_status' => 'canceled',
            'stripe_price' => 'price_123',
            'ends_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('billing.plan'));
    }
}
