<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\GlobalSetting;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OperationTest extends TestCase
{
    use RefreshDatabase;

    private function setUpDocument(): array
    {
        $user = User::factory()->subAdmin()->create();
        $this->actingAs($user);

        $document = Document::query()->create([
            'uploader_id' => $user->id,
            'source_type' => 'text',
            'extracted_text' => 'محتوى الدرس للاختبار',
        ]);

        return [$user, $document];
    }

    public function test_operation_creates_ai_output_on_success(): void
    {
        [, $document] = $this->setUpDocument();

        Http::fake([
            'http://fastapi-service:8000/process' => Http::response([
                'type' => 'summarization',
                'summary' => 'ملخص النص',
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/operations', [
            'document_id' => $document->id,
            'category' => 'aqeedah',
            'operation_type' => 'summarization',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.raw_json.type', 'summarization');

        $this->assertDatabaseHas('operations', ['status' => 'completed']);
        $this->assertDatabaseCount('ai_outputs', 1);
    }

    public function test_operation_returns_500_and_marks_failed_on_connection_error(): void
    {
        [, $document] = $this->setUpDocument();

        Http::fake([
            'http://fastapi-service:8000/process' => fn ($request) => throw new ConnectionException($request),
        ]);

        $response = $this->postJson('/api/v1/operations', [
            'document_id' => $document->id,
            'category' => 'fiqh',
            'operation_type' => 'question_generation',
        ]);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'تعذّر الاتصال بخدمة الذكاء الاصطناعي. يرجى المحاولة مرة أخرى لاحقاً.');

        $this->assertDatabaseHas('operations', ['status' => 'failed']);
        $this->assertDatabaseCount('ai_outputs', 0);
    }

    public function test_operation_blocked_when_monthly_limit_reached(): void
    {
        [$user, $document] = $this->setUpDocument();

        GlobalSetting::query()->updateOrCreate(
            ['setting_key' => GlobalSetting::AI_MONTHLY_LIMIT_KEY],
            ['setting_value' => '1']
        );

        Operation::query()->create([
            'document_id' => $document->id,
            'category' => 'aqeedah',
            'operation_type' => 'summarization',
            'status' => 'completed',
        ]);

        $response = $this->postJson('/api/v1/operations', [
            'document_id' => $document->id,
            'category' => 'seerah',
            'operation_type' => 'summarization',
        ]);

        $response->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('operations', 1);
    }
}
