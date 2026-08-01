<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Plans
    |--------------------------------------------------------------------------
    |
    | Source of truth for the billing plans offered by the application. The
    | "price" key references the Stripe price ID configured via environment
    | variables so the catalog can change without a deployment.
    |
    */

    'starter' => [
        'name' => 'Starter',
        'description' => 'For solo operators getting started with automation.',
        'price' => env('STRIPE_PRICE_STARTER_MONTHLY', 'price_starter_monthly'),
        'amount' => 2900,
        'currency' => 'usd',
        'features' => [
            '1 browser profile',
            '50 sessions per day',
            'Community support',
        ],
        'quotas' => [
            'profiles' => 1,
            'sessions_per_day' => 50,
        ],
    ],

    'pro' => [
        'name' => 'Pro',
        'description' => 'For professionals managing multiple accounts at scale.',
        'price' => env('STRIPE_PRICE_PRO_MONTHLY', 'price_pro_monthly'),
        'amount' => 5900,
        'currency' => 'usd',
        'features' => [
            '10 browser profiles',
            'Unlimited sessions',
            'Priority support',
            'Team sharing',
        ],
        'quotas' => [
            'profiles' => 10,
            'sessions_per_day' => null,
        ],
    ],

    'scale' => [
        'name' => 'Scale',
        'description' => 'For teams and agencies running operations at scale.',
        'price' => env('STRIPE_PRICE_SCALE_MONTHLY', 'price_scale_monthly'),
        'amount' => 9900,
        'currency' => 'usd',
        'features' => [
            'Unlimited browser profiles',
            'Unlimited sessions',
            'Dedicated support',
            'Team sharing & roles',
            'API access',
        ],
        'quotas' => [
            'profiles' => null,
            'sessions_per_day' => null,
        ],
    ],

];
