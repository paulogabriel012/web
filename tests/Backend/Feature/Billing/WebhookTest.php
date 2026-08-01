<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_secret';

    /**
     * Build a signed Stripe webhook request header.
     */
    private function signedHeader(string $payload): string
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", self::WEBHOOK_SECRET);

        return "t={$timestamp},v1={$signature}";
    }

    /**
     * Build a customer.subscription.created payload.
     *
     * @return array<string, mixed>
     */
    private function subscriptionCreatedPayload(string $customerId = 'cus_123'): array
    {
        return [
            'id' => 'evt_123',
            'object' => 'event',
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'object' => 'subscription',
                    'customer' => $customerId,
                    'status' => 'active',
                    'trial_end' => null,
                    'items' => [
                        'object' => 'list',
                        'data' => [
                            [
                                'id' => 'si_123',
                                'object' => 'subscription_item',
                                'price' => [
                                    'id' => 'price_123',
                                    'object' => 'price',
                                    'product' => 'prod_123',
                                ],
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_valid_webhook_creates_the_subscription(): void
    {
        config(['cashier.webhook.secret' => self::WEBHOOK_SECRET]);

        $user = User::factory()->create(['stripe_id' => 'cus_123']);
        $payload = $this->subscriptionCreatedPayload();

        $this->postJson(
            '/stripe/webhook',
            $payload,
            ['Stripe-Signature' => $this->signedHeader(json_encode($payload, JSON_THROW_ON_ERROR))],
        )->assertOk();

        $subscription = $user->subscriptions()->first();

        $this->assertNotNull($subscription);
        $this->assertSame('sub_123', $subscription->stripe_id);
        $this->assertSame('active', $subscription->stripe_status);
        $this->assertSame('price_123', $subscription->stripe_price);
        $this->assertSame(1, $subscription->items()->count());
        $this->assertTrue($user->fresh()->subscribed());
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        config(['cashier.webhook.secret' => self::WEBHOOK_SECRET]);

        User::factory()->create(['stripe_id' => 'cus_123']);
        $payload = $this->subscriptionCreatedPayload();

        $this->postJson(
            '/stripe/webhook',
            $payload,
            ['Stripe-Signature' => 't=123,v1=invalid'],
        )->assertForbidden();
    }

    public function test_webhook_for_unknown_customer_is_ignored(): void
    {
        config(['cashier.webhook.secret' => self::WEBHOOK_SECRET]);

        $payload = $this->subscriptionCreatedPayload('cus_unknown');

        $this->postJson(
            '/stripe/webhook',
            $payload,
            ['Stripe-Signature' => $this->signedHeader(json_encode($payload, JSON_THROW_ON_ERROR))],
        )->assertOk();

        $this->assertDatabaseCount('subscriptions', 0);
    }
}
