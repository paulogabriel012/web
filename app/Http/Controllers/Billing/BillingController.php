<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Enums\Billing\Plan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    /**
     * Show the plan selection page.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('billing/plan', [
            'plans' => array_map(
                static fn (Plan $plan): array => [
                    'id' => $plan->value,
                    'name' => $plan->label(),
                    'description' => Config::string("plans.{$plan->value}.description"),
                    'amount' => $plan->amount(),
                    'currency' => Config::string("plans.{$plan->value}.currency", 'usd'),
                    'features' => $plan->features(),
                ],
                Plan::catalog()
            ),
            'subscribed' => $request->user()?->subscribed() ?? false,
        ]);
    }

    /**
     * Redirect the user to a Stripe Checkout session for the chosen plan.
     */
    public function checkout(CheckoutRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $planValue = $request->validated('plan');

        if (! is_string($planValue)) {
            abort(422, __('Invalid plan.'));
        }

        $plan = Plan::tryFrom($planValue);

        if ($plan === null) {
            abort(422, __('Invalid plan.'));
        }

        return $user
            ->checkout($plan->priceId(), [
                'success_url' => route('billing.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
            ])
            ->redirect();
    }

    /**
     * Show the checkout success page.
     */
    public function success(Request $request): Response
    {
        return Inertia::render('billing/success', [
            'subscribed' => $request->user()?->subscribed() ?? false,
        ]);
    }

    /**
     * Show the checkout cancellation page.
     */
    public function cancel(): Response
    {
        return Inertia::render('billing/cancel');
    }

    /**
     * Report whether the user is subscribed (polled by the success page).
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'subscribed' => $request->user()?->subscribed() ?? false,
        ]);
    }

    /**
     * Redirect the user to the Stripe billing portal.
     */
    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->stripeId()) {
            abort(404);
        }

        return $user->redirectToBillingPortal(route('billing.plan'));
    }
}
