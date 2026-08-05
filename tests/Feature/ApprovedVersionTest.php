<?php

namespace Tests\Feature;

use App\Models\AiOutput;
use App\Models\Document;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovedVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_approve_an_ai_output(): void
    {
        $user = User::factory()->subAdmin()->create();
        $this->actingAs($user);

        $operation = Operation::query()->create([
            'document_id' => $this->makeDocument($user),
            'category' => 'aqeedah',
            'operation_type' => 'summarization',
            'status' => 'completed',
        ]);

        $aiOutput = AiOutput::query()->create([
            'operation_id' => $operation->id,
            'raw_json' => ['summary' => 'النسخة الأصلية من الذكاء الاصطناعي'],
        ]);

        $edited = ['summary' => 'النسخة المعدّلة من قبل المشرف'];

        $response = $this->postJson('/api/v1/approved-versions', [
            'ai_output_id' => $aiOutput->id,
            'edited_content' => $edited,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.edited_content.summary', 'النسخة المعدّلة من قبل المشرف')
            ->assertJsonPath('data.approver_id', $user->id);

        $this->assertDatabaseCount('approved_versions', 1);

        $this->assertDatabaseHas('ai_outputs', [
            'id' => $aiOutput->id,
        ]);

        $this->assertSame('النسخة الأصلية من الذكاء الاصطناعي', $aiOutput->fresh()->raw_json['summary']);
    }

    private function makeDocument(User $user): int
    {
        return Document::query()->create([
            'uploader_id' => $user->id,
            'source_type' => 'text',
            'extracted_text' => 'محتوى',
        ])->id;
    }
}
