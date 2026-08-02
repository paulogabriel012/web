<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UsageResource extends JsonResource
{
    /**
     * @var array{
     *     period: array{type: string, starts_at: string, ends_at: string},
     *     counters: array<string, array{used: int, limit: int|null, remaining: int|null}>
     * }
     */
    private readonly array $usage;

    /**
     * @param  array{
     *     period: array{type: string, starts_at: string, ends_at: string},
     *     counters: array<string, array{used: int, limit: int|null, remaining: int|null}>
     * }  $usage
     */
    public function __construct(array $usage)
    {
        $this->usage = $usage;

        parent::__construct($usage);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period' => $this->usage['period'],
            'counters' => $this->usage['counters'],
        ];
    }
}
