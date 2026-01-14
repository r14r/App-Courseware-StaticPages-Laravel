<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DataWriteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'data' => ['required', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'data.required' => 'Data payload is required.',
            'data.array' => 'Data payload must be a JSON object or array.',
        ];
    }
}
