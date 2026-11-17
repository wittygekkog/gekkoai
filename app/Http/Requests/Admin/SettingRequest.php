<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('admin')?->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['setting_slug' => strtolower(trim((string) $this->input('setting_slug')))]);
    }

    public function rules(): array
    {
        $setting = $this->route('setting');

        return [
            'setting_slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', Rule::unique('settings', 'setting_slug')->ignore($setting?->id)],
            'setting_name' => ['required', 'string', 'max:191'],
            'setting_group' => ['required', Rule::in(array_keys(Setting::GROUPS))],
            'input_type' => ['required', Rule::in(array_keys(Setting::TYPES))],
            'setting_value' => ['nullable', 'string', 'max:5000'],
            'setting_file' => ['nullable', 'file', 'max:5120', 'mimes:png,jpg,jpeg,webp,gif,ico'],
            'help_text' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65000'],
        ];
    }
}
