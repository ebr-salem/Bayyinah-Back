<?php

namespace Tests\Feature;

use App\Models\AiOutput;
use App\Models\ApprovedVersion;
use App\Models\Document;
use App\Models\Operation;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicQuizTest extends TestCase
{
    use RefreshDatabase;

    private function makeActiveQuiz(): Quiz
    {
        $user = User::factory()->subAdmin()->create();

        $operation = Operation::query()->create([
            'document_id' => Document::query()->create([
                'uploader_id' => $user->id,
                'source_type' => 'text',
                'extracted_text' => 'محتوى',
            ])->id,
            'category' => 'aqeedah',
            'operation_type' => 'question_generation',
            'status' => 'completed',
        ]);

        $aiOutput = AiOutput::query()->create([
            'operation_id' => $operation->id,
            'raw_json' => ['questions' => [['question' => 'ما هي أركان الإسلام؟']]],
        ]);

        $approved = ApprovedVersion::query()->create([
            'ai_output_id' => $aiOutput->id,
            'edited_content' => [
                'questions' => [
                    ['question' => 'ما هي أركان الإسلام؟', 'answer' => 'خمسة أركان'],
                    ['question' => 'كم عدد الصلوات المفروضة؟', 'answer' => 'خمس صلوات'],
                ],
            ],
            'approver_id' => $user->id,
        ]);

        return Quiz::query()->create([
            'approved_version_id' => $approved->id,
            'unique_token' => 'quiz-token-abc123',
            'is_active' => true,
        ]);
    }

    public function test_public_can_view_an_active_quiz(): void
    {
        $this->makeActiveQuiz();

        $this->getJson('/api/v1/quizzes/quiz-token-abc123')
            ->assertOk()
            ->assertJsonPath('data.unique_token', 'quiz-token-abc123')
            ->assertJsonCount(2, 'data.questions');
    }

    public function test_viewing_unknown_token_returns_404(): void
    {
        $this->getJson('/api/v1/quizzes/does-not-exist')->assertNotFound();
    }

    public function test_viewing_inactive_quiz_returns_410(): void
    {
        $quiz = $this->makeActiveQuiz();
        $quiz->update(['is_active' => false]);

        $this->getJson('/api/v1/quizzes/quiz-token-abc123')->assertStatus(410);
    }

    public function test_participant_can_submit_and_score_is_calculated(): void
    {
        $this->makeActiveQuiz();

        $response = $this->postJson('/api/v1/quizzes/quiz-token-abc123/submit', [
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'answers' => [
                [
                    'question_text' => 'ما هي أركان الإسلام؟',
                    'participant_answer' => 'خمسة أركان',
                    'correct_answer' => 'خمسة أركان',
                ],
                [
                    'question_text' => 'كم عدد الصلوات المفروضة؟',
                    'participant_answer' => 'أربع صلوات',
                    'correct_answer' => 'خمس صلوات',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.score', 1)
            ->assertJsonPath('data.total', 2);

        $this->assertDatabaseHas('quiz_submissions', ['score' => 1]);
        $this->assertDatabaseCount('quiz_answers', 2);
    }

    public function test_duplicate_email_submission_is_rejected(): void
    {
        $this->makeActiveQuiz();

        $payload = [
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'answers' => [
                ['question_text' => 'س', 'participant_answer' => 'أ', 'correct_answer' => 'أ'],
            ],
        ];

        $this->postJson('/api/v1/quizzes/quiz-token-abc123/submit', $payload)->assertCreated();

        $this->postJson('/api/v1/quizzes/quiz-token-abc123/submit', $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('quiz_submissions', 1);
    }
}
