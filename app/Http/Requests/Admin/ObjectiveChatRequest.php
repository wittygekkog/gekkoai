<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ObjectiveChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid'],
            'objective_id' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:4000'],
            'title' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
