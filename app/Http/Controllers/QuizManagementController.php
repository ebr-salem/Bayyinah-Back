<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuizRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuizManagementController extends Controller
{
    use ApiResponse;

    public function store(StoreQuizRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $quiz = Quiz::query()->create([
            'approved_version_id' => $validated['approved_version_id'],
            'unique_token' => Str::random(32),
            'is_active' => true,
        ]);

        return $this->success($quiz, 'تم إنشاء الاختبار بنجاح.', 201);
    }

    public function results(Quiz $quiz): JsonResponse
    {
        $quiz->load([
            'submissions' => fn ($q) => $q->with('answers'),
            'approvedVersion.aiOutput.operation',
        ]);

        return $this->success($quiz, 'تم جلب نتائج الاختبار بنجاح.');
    }
}
