<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Stripe\Customer;
use Stripe\StripeClient;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Swap the Stripe client for a mocked instance.
     */
    private function fakeStripeClient(): void
    {
        $customers = Mockery::mock();
        $customers->shouldReceive('create')->once()->andReturn(new Customer(['id' => 'cus_123']));

        $sessions = Mockery::mock();
        $sessions->shouldReceive('create')->once()->andReturn(Session::constructFrom([
            'id' => 'cs_123',
            'url' => 'https://checkout.stripe.com/c/pay/cs_123',
        ]));

        $checkout = new \stdClass;
        $checkout->sessions = $sessions;

        $client = Mockery::mock(StripeClient::class);
        $client->shouldReceive('getService')->with('customers')->andReturn($customers);
        $client->shouldReceive('getService')->with('checkout')->andReturn($checkout);

        $this->app->bind(StripeClient::class, static fn (): StripeClient => $client);
    }

    public function test_users_are_redirected_to_the_stripe_checkout_session(): void
    {
        $user = User::factory()->create();

        $this->fakeStripeClient();

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'plan' => 'pro',
        ]);

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_123');
        $this->assertSame('cus_123', $user->fresh()->stripe_id);
    }

    public function test_checkout_requires_an_available_plan(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'plan' => 'enterprise',
        ]);

        $response->assertSessionHasErrors('plan');
    }

    public function test_checkout_requires_authentication(): void
    {
        $this->post(route('billing.checkout'), [
            'plan' => 'pro',
        ])->assertRedirect(route('login'));
    }
}
