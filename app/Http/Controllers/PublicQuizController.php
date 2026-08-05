<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitQuizRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PublicQuizController extends Controller
{
    use ApiResponse;

    public function show(string $token): JsonResponse
    {
        $quiz = Quiz::query()
            ->with('approvedVersion')
            ->where('unique_token', $token)
            ->first();

        if (! $quiz) {
            return $this->error('الاختبار غير موجود.', 404);
        }

        if (! $quiz->is_active) {
            return $this->error('هذا الاختبار غير متاح حالياً.', 410);
        }

        return $this->success([
            'id' => $quiz->id,
            'unique_token' => $quiz->unique_token,
            'questions' => $quiz->approvedVersion->edited_content['questions'] ?? [],
        ], 'تم جلب الاختبار بنجاح.');
    }

    public function submit(SubmitQuizRequest $request, string $token): JsonResponse
    {
        $quiz = Quiz::query()->where('unique_token', $token)->first();

        if (! $quiz) {
            return $this->error('الاختبار غير موجود.', 404);
        }

        if (! $quiz->is_active) {
            return $this->error('هذا الاختبار غير متاح حالياً.', 410);
        }

        $existing = QuizSubmission::query()
            ->where('quiz_id', $quiz->id)
            ->where('email', $request->validated('email'))
            ->exists();

        if ($existing) {
            return $this->error('تم إرسال إجاباتك لهذا الاختبار مسبقاً من هذا البريد الإلكتروني.', 409);
        }

        $answers = $request->validated('answers');
        $score = 0;

        foreach ($answers as $answer) {
            if ($this->isCorrect($answer['participant_answer'], $answer['correct_answer'])) {
                $score++;
            }
        }

        $submission = DB::transaction(function () use ($quiz, $request, $answers, $score) {
            $submission = QuizSubmission::query()->create([
                'quiz_id' => $quiz->id,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'score' => $score,
            ]);

            foreach ($answers as $answer) {
                $submission->answers()->create([
                    'question_text' => $answer['question_text'],
                    'participant_answer' => $answer['participant_answer'],
                    'correct_answer' => $answer['correct_answer'],
                    'is_correct' => $this->isCorrect($answer['participant_answer'], $answer['correct_answer']),
                ]);
            }

            return $submission;
        });

        return $this->success([
            'score' => $score,
            'total' => count($answers),
            'submission_id' => $submission->id,
        ], 'تم إرسال إجاباتك بنجاح.', 201);
    }

    private function isCorrect(string $participantAnswer, string $correctAnswer): bool
    {
        return mb_strtolower(trim($participantAnswer)) === mb_strtolower(trim($correctAnswer));
    }
}
