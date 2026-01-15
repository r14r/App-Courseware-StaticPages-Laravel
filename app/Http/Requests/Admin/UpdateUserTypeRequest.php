<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'user_type' => [
                'required',
                Rule::in(array_map(
                    static fn (UserType $type): string => $type->value,
                    UserType::cases()
                )),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_type.required' => 'User type is required.',
            'user_type.in' => 'User type must be Admin, Trainer, or Student.',
        ];
    }
}
