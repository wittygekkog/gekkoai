<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:4000'],
            'fields' => ['nullable', 'array'],
            'fields.email' => ['nullable', 'email', 'max:190'],
            'fields.mobile' => ['nullable', 'string', 'max:40'],
            'fields.guests' => ['nullable', 'integer', 'min:1', 'max:18'],
            'fields.name' => ['nullable', 'string', 'max:120'],
            'fields.arrival' => ['nullable', 'date_format:Y-m-d'],
            'fields.departure' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
