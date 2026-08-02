<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Domain\Subscription\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Entitlements
 */
final class SubscriptionResource extends JsonResource
{
    private readonly Entitlements $entitlements;

    public function __construct(Entitlements $entitlements)
    {
        $this->entitlements = $entitlements;

        parent::__construct($entitlements);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->entitlements->toArray();

        return [
            'status' => $snapshot['status'],
            'plan' => $snapshot['plan'] !== null
                ? ['id' => $snapshot['plan']['id'], 'name' => $snapshot['plan']['name']]
                : null,
            'limits' => $snapshot['limits'],
            'trial_ends_at' => $snapshot['trial_ends_at'],
            'current_period_ends_at' => $snapshot['current_period_ends_at'],
            'cancel_at_period_end' => $snapshot['cancel_at_period_end'],
            'access' => $snapshot['access'],
            'billing_portal_url' => null,
        ];
    }
}
