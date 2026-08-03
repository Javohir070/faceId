<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\MergesScopedDepartmentId;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroupRequest extends FormRequest
{
    use MergesScopedDepartmentId;

    public function authorize(): bool
    {
        return $this->user()?->can(config('menu_permissions.resources')['groups']) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->mergeScopedDepartmentId();
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('groups', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parentId = $this->integer('parent_id') ?: null;

            if ($parentId === null) {
                return;
            }

            $parent = Group::query()->find($parentId);

            if ($parent === null) {
                return;
            }

            if ((int) $parent->department_id !== (int) $this->input('department_id')) {
                $validator->errors()->add('parent_id', 'Parent guruh tanlangan bo‘limga tegishli bo‘lishi kerak.');
            }
        });
    }
}
