<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LogicItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid'],
            'kind' => ['required', 'in:rule,instruction'],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:5000'],
            'intent' => ['nullable', 'required_if:kind,instruction', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'intent.required_if' => 'An instruction needs the intent it should map to.',
        ];
    }
}
