<?php

namespace App\Http\Requests;

use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGroupRequest extends FormRequest
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
        /** @var Group $group */
        $group = $this->route('group');

        return [
            'name' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id'),
                Rule::notIn([$group->id]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.not_in' => 'Guruh o‘zini parent qilib tanlanmaydi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Group $group */
            $group = $this->route('group');
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

                return;
            }

            $cursor = $parent;
            while ($cursor !== null) {
                if ($cursor->id === $group->id) {
                    $validator->errors()->add('parent_id', 'Pastki guruhni parent qilib tanlab bo‘lmaydi (sikl).');

                    return;
                }

                $cursor = $cursor->parent;
            }
        });
    }
}
