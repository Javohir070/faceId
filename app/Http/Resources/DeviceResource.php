<?php

namespace App\Http\Resources;

use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Device */
class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($request->routeIs('admin.devices.index')) {
            return $this->indexFields($request);
        }

        if ($request->routeIs('admin.devices.edit')) {
            return $this->formFields();
        }

        return $this->showFields();
    }

    /**
     * @return array<string, mixed>
     */
    private function indexFields(Request $request): array
    {
        /** @var DeviceService $deviceService */
        $deviceService = app(DeviceService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'user' => $this->user,
            'has_password' => filled($this->password),
            'type' => $this->type,
            'type_label' => $deviceService->typeLabel($this->resource),
            'is_connected' => $this->is_online,
            'autoconnect' => $this->autoconnect,
            'is_online' => $this->is_online,
            'webhook_configured' => $this->webhook_configured,
            'last_connected_at' => $this->last_connected_at?->format('Y-m-d H:i:s'),
            'last_ping_at' => $this->last_ping_at?->format('Y-m-d H:i:s'),
            'category' => $this->category?->only(['id', 'name']),
            'owner' => $this->owner?->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showFields(): array
    {
        /** @var DeviceService $deviceService */
        $deviceService = app(DeviceService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'use_https' => $this->use_https,
            'isapi_base_url' => $this->isapiBaseUrl(),
            'user' => $this->user,
            'has_password' => filled($this->password),
            'type' => $this->type,
            'type_label' => $deviceService->typeLabel($this->resource),
            'autoconnect' => $this->autoconnect,
            'is_connected' => $this->is_online,
            'is_online' => $this->is_online,
            'webhook_configured' => $this->webhook_configured,
            'last_connected_at' => $this->last_connected_at?->format('Y-m-d H:i:s'),
            'last_ping_at' => $this->last_ping_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'category' => $this->category?->only(['id', 'name']),
            'owner' => $this->owner?->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formFields(): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'name' => $this->name,
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'port' => $this->port,
            'use_https' => $this->use_https,
            'user' => $this->user,
            'type' => $this->type,
            'autoconnect' => $this->autoconnect,
        ];
    }
}
