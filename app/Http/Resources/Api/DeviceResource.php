<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
final class DeviceResource extends JsonResource
{
    private readonly Device $device;

    public function __construct(Device $device)
    {
        $this->device = $device;

        parent::__construct($device);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->device->id,
            'name' => $this->device->name,
            'platform' => $this->device->platform,
            'architecture' => $this->device->architecture,
            'app_version' => $this->device->app_version,
            'os_version' => $this->device->os_version,
            'last_seen_at' => $this->device->last_seen_at?->toIso8601String(),
            'revoked_at' => $this->device->revoked_at?->toIso8601String(),
            'created_at' => $this->device->created_at?->toIso8601String(),
        ];
    }
}
