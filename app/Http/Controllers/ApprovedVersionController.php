<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApprovedVersionRequest;
use App\Http\Traits\ApiResponse;
use App\Models\ApprovedVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApprovedVersionController extends Controller
{
    use ApiResponse;

    public function store(StoreApprovedVersionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $approvedVersion = DB::transaction(function () use ($validated, $request) {
            return ApprovedVersion::query()->create([
                'ai_output_id' => $validated['ai_output_id'],
                'edited_content' => $validated['edited_content'],
                'approver_id' => $request->user()->id,
            ]);
        });

        return $this->success($approvedVersion, 'تم اعتماد النسخة بنجاح.', 201);
    }
}
