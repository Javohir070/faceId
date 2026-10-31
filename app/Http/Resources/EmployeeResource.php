<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin Employee */
class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($request->routeIs('admin.employees.index')) {
            return $this->indexFields();
        }

        if ($request->routeIs('admin.employees.edit')) {
            return $this->formFields();
        }

        return $this->showFields();
    }

    /**
     * @return array<string, mixed>
     */
    private function indexFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'card' => $this->card,
            'gender' => $this->gender,
            'gender_label' => $this->genderLabel(),
            'position' => $this->position,
            'has_face' => $this->face !== null,
            'valid_from_dt' => $this->valid_from_dt?->format('Y-m-d H:i'),
            'valid_to_dt' => $this->valid_to_dt?->format('Y-m-d H:i'),
            'department' => $this->department?->only(['id', 'name']),
            'schedule' => $this->schedule?->only(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'card' => $this->card,
            'gender' => $this->gender,
            'gender_label' => $this->genderLabel(),
            'country' => $this->country,
            'country_name' => $this->countryName(),
            'position' => $this->position,
            'valid_from_dt' => $this->valid_from_dt?->format('Y-m-d H:i'),
            'valid_to_dt' => $this->valid_to_dt?->format('Y-m-d H:i'),
            'created_at' => $this->created_at,
            'department' => $this->department?->only(['id', 'name']),
            'group' => $this->group?->only(['id', 'name']),
            'schedule' => $this->schedule === null ? null : [
                'id' => $this->schedule->id,
                'name' => $this->schedule->name,
                'time' => $this->schedule->enter_time?->format('H:i').'–'.$this->schedule->exit_time?->format('H:i'),
            ],
            'face_preview' => self::facePreviewUrl($this->face?->face),
            'devices' => $this->devices
                ->sortBy('name')
                ->values()
                ->map(fn ($device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'is_synced' => filled($device->pivot->synced_at),
                    'synced_at' => filled($device->pivot->synced_at)
                        ? Carbon::parse($device->pivot->synced_at)->format('Y-m-d H:i')
                        : null,
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'card' => $this->card,
            'gender' => $this->gender,
            'country' => $this->country,
            'position' => $this->position,
            'schedule_id' => $this->schedule_id,
            'department_id' => $this->department_id,
            'group_id' => $this->group_id,
            'valid_from_dt' => $this->valid_from_dt?->format('Y-m-d\TH:i'),
            'valid_to_dt' => $this->valid_to_dt?->format('Y-m-d\TH:i'),
            'face_preview' => self::facePreviewUrl($this->face?->face),
            'devices' => $this->devices
                ->sortBy('name')
                ->values()
                ->map(fn ($device): array => [
                    'id' => $device->id,
                    'name' => $device->name,
                    'ip_address' => $device->ip_address,
                    'is_synced' => filled($device->pivot->synced_at),
                    'synced_at' => filled($device->pivot->synced_at)
                        ? Carbon::parse($device->pivot->synced_at)->format('Y-m-d H:i')
                        : null,
                ]),
        ];
    }

    public static function facePreviewUrl(?string $face): ?string
    {
        if ($face === null || $face === '') {
            return null;
        }

        if (str_starts_with($face, 'data:')) {
            return $face;
        }

        return 'data:image/jpeg;base64,'.$face;
    }
}
