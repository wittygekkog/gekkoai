<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Read one platform setting. Password values are stored with an enc: prefix.
 */
function getSetting(string $slug, ?string $default = null): ?string
{
    $all = Cache::remember('settings.map', 3600, static function (): array {
        return Setting::query()->pluck('setting_value', 'setting_slug')->all();
    });

    $value = $all[$slug] ?? $default;
    if (! is_string($value)) {
        return $default;
    }
    if (str_starts_with($value, 'enc:')) {
        try {
            return Crypt::decryptString(substr($value, 4));
        } catch (Throwable) {
            return $default;
        }
    }

    return $value;
}

function clearSettingsCache(): void
{
    Cache::forget('settings.map');
}
