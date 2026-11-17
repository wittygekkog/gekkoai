<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['setting_slug' => 'site-name', 'setting_name' => 'Site name', 'setting_value' => 'GekkoAi', 'setting_group' => 'general', 'input_type' => 'text', 'help_text' => 'Name shown in the administration workspace and the Android app.', 'sort_order' => 10],
            ['setting_slug' => 'site-logo', 'setting_name' => 'Application logo', 'setting_value' => null, 'setting_group' => 'general', 'input_type' => 'upload_file', 'help_text' => 'Recommended size: 400 × 200 px (2:1). Formats: WebP, JPG, PNG. Maximum file size: 5 MB. Shown on the Android login screen and headers. Leave empty to use the built-in fallback.', 'sort_order' => 15],
            ['setting_slug' => 'theme-primary', 'setting_name' => 'Primary colour', 'setting_value' => '#7c3aed', 'setting_group' => 'theme', 'input_type' => 'color', 'help_text' => 'Accent colour taken from the GekkoAi logo.', 'sort_order' => 20],
            ['setting_slug' => 'theme-sidebar-bg', 'setting_name' => 'Sidebar colour', 'setting_value' => '#14122c', 'setting_group' => 'theme', 'input_type' => 'color', 'help_text' => 'Sidebar background.', 'sort_order' => 30],
            ['setting_slug' => 'theme-body-bg', 'setting_name' => 'Page background', 'setting_value' => '#f4f6fb', 'setting_group' => 'theme', 'input_type' => 'color', 'help_text' => 'Page background behind the cards.', 'sort_order' => 40],
            ['setting_slug' => 'ai-auto-execution-enabled', 'setting_name' => 'Automatic writes', 'setting_value' => '0', 'setting_group' => 'ai', 'input_type' => 'boolean', 'help_text' => 'Leave off until calibration and approval policy allow automatic writes.', 'sort_order' => 50],
            ['setting_slug' => 'ai-min-auto-confidence', 'setting_name' => 'Minimum automatic confidence', 'setting_value' => '0.90', 'setting_group' => 'ai', 'input_type' => 'number', 'help_text' => 'Platform floor for automatic writes. Uncalibrated scores cannot reach this.', 'sort_order' => 60],
            ['setting_slug' => 'ai-max-clarification-rounds', 'setting_name' => 'Clarification rounds', 'setting_value' => '3', 'setting_group' => 'ai', 'input_type' => 'number', 'help_text' => 'Unanswered clarification stops after this many rounds and escalates.', 'sort_order' => 70],
        ];

        foreach ($settings as $setting) {
            Setting::query()->firstOrCreate(
                ['setting_slug' => $setting['setting_slug']],
                $setting,
            );
        }

        clearSettingsCache();
    }
}
