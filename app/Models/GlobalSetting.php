<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['setting_key', 'setting_value'])]
class GlobalSetting extends Model
{
    public const AI_MONTHLY_LIMIT_KEY = 'ai_operations_monthly_limit';

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()
            ->where('setting_key', $key)
            ->value('setting_value') ?? $default;
    }
}
