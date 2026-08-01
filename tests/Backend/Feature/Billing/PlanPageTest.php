<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_page_renders_the_plan_catalog_for_verified_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('billing.plan'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('billing/plan')
                ->has('plans', 3)
                ->where('subscribed', false)
                ->where('plans.0.id', 'starter')
                ->where('plans.0.name', 'Starter')
                ->has('plans.0.features'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('billing.plan'))
            ->assertRedirect(route('login'));
    }

    public function test_unverified_users_are_redirected_to_verification_notice(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('billing.plan'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_subscribed_users_see_the_plan_page_as_satisfied(): void
    {
        $user = User::factory()->subscribed()->create();

        $response = $this->actingAs($user)->get(route('billing.plan'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page->where('subscribed', true));
    }
}
