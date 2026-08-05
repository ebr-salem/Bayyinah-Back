<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Http\Traits\ApiResponse;
use App\Models\GlobalSetting;
use Illuminate\Http\JsonResponse;

class GlobalSettingsController extends Controller
{
    use ApiResponse;

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $settings = $request->validated('settings');

        foreach ($settings as $key => $value) {
            GlobalSetting::query()->updateOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value]
            );
        }

        $updated = GlobalSetting::query()->whereIn('setting_key', array_keys($settings))
            ->pluck('setting_value', 'setting_key');

        return $this->success($updated, 'تم تحديث الإعدادات بنجاح.');
    }
}
