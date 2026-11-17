<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

final class SettingService
{
    public function create(array $data, ?UploadedFile $file = null): Setting
    {
        $setting = new Setting();
        $this->fill($setting, $data, $file);

        return $setting;
    }

    public function update(Setting $setting, array $data, ?UploadedFile $file = null): Setting
    {
        $this->fill($setting, $data, $file);

        return $setting;
    }

    public function delete(Setting $setting): void
    {
        if ($setting->isFile() && $setting->setting_value) {
            Storage::disk('public')->delete($setting->setting_value);
        }
        $setting->delete();
        clearSettingsCache();
    }

    private function fill(Setting $setting, array $data, ?UploadedFile $file): void
    {
        $type = $data['input_type'];
        $value = $data['setting_value'] ?? null;
        $wasFile = $setting->exists && $setting->isFile();

        if ($type === 'upload_file') {
            $value = $wasFile ? $setting->setting_value : null;
            if ($file !== null) {
                if ($wasFile && $setting->setting_value) {
                    Storage::disk('public')->delete($setting->setting_value);
                }
                $value = $file->store('settings', 'public');
            }
        } elseif ($type === 'password' && ($value === null || $value === '') && $setting->exists && $setting->isSecret()) {
            $value = $setting->setting_value;
        } elseif ($type === 'password' && is_string($value) && $value !== '') {
            $value = 'enc:'.Crypt::encryptString($value);
        } elseif ($type === 'boolean') {
            $value = (string) (int) (bool) $value;
        }

        $setting->fill([
            'setting_slug' => $data['setting_slug'],
            'setting_name' => $data['setting_name'],
            'setting_group' => $data['setting_group'],
            'input_type' => $type,
            'help_text' => $data['help_text'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'setting_value' => $value,
        ])->save();
        clearSettingsCache();
    }
}
