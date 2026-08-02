<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Domain\Subscription\Entitlements;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class MeResource extends JsonResource
{
    private readonly User $user;

    /**
     * @param  array{
     *     period: array{type: string, starts_at: string, ends_at: string},
     *     counters: array<string, array{used: int, limit: int|null, remaining: int|null}>
     * }  $usage
     */
    public function __construct(
        User $user,
        private readonly Entitlements $entitlements,
        private readonly array $usage,
    ) {
        $this->user = $user;

        parent::__construct($user);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->entitlements->toArray();
        $sessions = $this->usage['counters']['sessions'];
        $profiles = $this->usage['counters']['profiles'];

        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'email_verified' => $this->user->hasVerifiedEmail(),
            ],
            'subscription' => [
                'status' => $snapshot['status'],
                'plan' => $snapshot['plan'],
                'trial_ends_at' => $snapshot['trial_ends_at'],
                'current_period_ends_at' => $snapshot['current_period_ends_at'],
                'cancel_at_period_end' => $snapshot['cancel_at_period_end'],
                'access' => $snapshot['access'],
            ],
            'entitlements' => [
                'limits' => $snapshot['limits'],
                'features' => $snapshot['features'],
            ],
            'usage' => [
                'profiles' => $profiles,
                'sessions_today' => [
                    'used' => $sessions['used'],
                    'limit' => $sessions['limit'],
                    'remaining' => $sessions['remaining'],
                    'reset_at' => $this->usage['period']['ends_at'],
                ],
            ],
            'server_time' => now()->toIso8601String(),
        ];
    }
}
