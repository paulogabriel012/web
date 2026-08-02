<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\SessionGrant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SessionGrant
 */
final class SessionGrantResource extends JsonResource
{
    private readonly SessionGrant $grant;

    /**
     * @param  array{used: int, limit: int|null, remaining: int|null, reset_at: string}|null  $usage
     */
    public function __construct(
        SessionGrant $grant,
        private readonly ?string $planId = null,
        private readonly ?array $usage = null,
    ) {
        $this->grant = $grant;

        parent::__construct($grant);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this->grant->id,
            'operation' => $this->grant->operation,
            'status' => $this->grant->status->value,
            'authorized' => true,
            'authorized_at' => $this->grant->authorized_at?->toIso8601String(),
            'expires_at' => $this->grant->expires_at?->toIso8601String(),
            'finished_at' => $this->grant->finished_at?->toIso8601String(),
            'plan' => $this->planId,
            'usage' => $this->usage,
        ];
    }
}
