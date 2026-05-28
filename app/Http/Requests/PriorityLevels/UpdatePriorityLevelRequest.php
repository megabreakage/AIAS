<?php

declare(strict_types=1);

namespace App\Http\Requests\PriorityLevels;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdatePriorityLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $identifier = $this->route('priority_level');

        return [
            'name'        => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('priority_levels', 'name')
                    ->whereNull('deleted_at')
                    ->ignore($identifier, 'identifier'),
            ],
            'description' => ['nullable', 'string', 'max:65535'],
            'level'       => ['sometimes', 'required', 'integer', 'min:1'],
            'color'       => ['nullable', 'string', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'The priority level name is required.',
            'name.unique' => 'A priority level with this name already exists.',
        ];
    }
}
