<?php

namespace Tests\Feature;

use App\Models\AiOutput;
use App\Models\ApprovedVersion;
use App\Models\Document;
use App\Models\Operation;
use App\Models\Quiz;
use App\Models\QuizSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuizManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsSubAdmin(): User
    {
        $user = User::factory()->subAdmin()->create();

        $this->actingAs($user);

        return $user;
    }

    private function makeQuiz(User $user, string $token): Quiz
    {
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
                ],
            ],
            'approver_id' => $user->id,
        ]);

        return Quiz::query()->create([
            'approved_version_id' => $approved->id,
            'unique_token' => $token,
            'is_active' => true,
        ]);
    }

    public function test_listing_quizzes_requires_authentication(): void
    {
        $this->getJson('/api/v1/quizzes')->assertUnauthorized();
    }

    public function test_admin_can_list_created_quizzes(): void
    {
        $user = $this->actingAsSubAdmin();

        $this->makeQuiz($user, 'first-quiz-token');
        $this->makeQuiz($user, 'second-quiz-token');

        $response = $this->getJson('/api/v1/quizzes')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.unique_token', 'second-quiz-token')
            ->assertJsonPath('data.data.0.is_active', true)
            ->assertJsonPath('data.data.0.submissions_count', 0)
            ->assertJsonPath('data.data.0.approved_version.ai_output.operation.category', 'aqeedah')
            ->assertJsonPath('data.data.0.approved_version.ai_output.operation.operation_type', 'question_generation')
            ->assertJsonPath('data.data.0.approved_version.ai_output.operation.status', 'completed');
    }

    public function test_listing_quizzes_includes_submission_counts(): void
    {
        $user = $this->actingAsSubAdmin();

        $quiz = $this->makeQuiz($user, 'quiz-with-submissions');

        QuizSubmission::query()->create([
            'quiz_id' => $quiz->id,
            'name' => 'أحمد',
            'email' => 'ahmed@example.com',
            'score' => 1,
        ]);

        $this->getJson('/api/v1/quizzes')
            ->assertOk()
            ->assertJsonPath('data.data.0.submissions_count', 1);
    }
}
