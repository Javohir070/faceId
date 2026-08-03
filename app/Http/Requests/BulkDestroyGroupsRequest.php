<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDestroyGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['groups']) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_ids' => ['required', 'array', 'min:1'],
            'group_ids.*' => ['integer', Rule::exists('groups', 'id')],
        ];
    }
}
