<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'setting_slug',
        'setting_name',
        'setting_value',
        'setting_group',
        'input_type',
        'help_text',
        'sort_order',
    ];

    public const GROUPS = [
        'general' => 'General',
        'theme' => 'Theme',
        'ai' => 'AI Operations',
        'security' => 'Security',
        'seo' => 'SEO',
    ];

    public const TYPES = [
        'text' => 'Text',
        'textarea' => 'Long text',
        'number' => 'Number',
        'email' => 'Email',
        'url' => 'URL',
        'color' => 'Colour',
        'boolean' => 'Yes / No',
        'password' => 'Secret',
        'upload_file' => 'Upload file',
    ];

    public function isFile(): bool
    {
        return $this->input_type === 'upload_file';
    }

    public function isSecret(): bool
    {
        return $this->input_type === 'password';
    }

    public function groupLabel(): string
    {
        return self::GROUPS[$this->setting_group] ?? ucfirst((string) $this->setting_group);
    }

    public function recommendedImageSize(): string
    {
        $slug = strtolower((string) $this->setting_slug);
        if (str_contains($slug, 'favicon')) {
            return '512 × 512 px (1:1)';
        }
        if (str_contains($slug, 'logo')) {
            return '400 × 200 px (2:1)';
        }
        if (str_contains($slug, 'banner') || str_contains($slug, 'hero')) {
            return '1920 × 700 px (about 2.74:1)';
        }

        return '1200 × 630 px (1.91:1)';
    }
}
