<?php

namespace Tests\Feature;

use App\Models\AiOutput;
use App\Models\Document;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesPdf;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use CreatesPdf, RefreshDatabase;

    private function actingAsSubAdmin(): User
    {
        $user = User::factory()->subAdmin()->create();

        $this->actingAs($user);

        return $user;
    }

    public function test_store_text_document_requires_authentication(): void
    {
        $this->postJson('/api/v1/documents', [
            'source_type' => 'text',
            'text_content' => 'Some content',
        ])->assertUnauthorized();
    }

    public function test_admin_can_store_a_text_document(): void
    {
        $this->actingAsSubAdmin();

        $response = $this->postJson('/api/v1/documents', [
            'source_type' => 'text',
            'text_content' => 'محتوى نصي <script>alert(1)</script> للاختبار',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('documents', [
            'source_type' => 'text',
            'extracted_text' => 'محتوى نصي alert(1) للاختبار',
        ]);
    }

    public function test_admin_can_store_a_pdf_document(): void
    {
        $this->actingAsSubAdmin();

        $file = UploadedFile::fake()->createWithContent('lesson.pdf', $this->createPdf('Surah Al-Fatihah'));

        $response = $this->postJson('/api/v1/documents', [
            'source_type' => 'pdf',
            'file' => $file,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.extracted_text', 'Surah Al-Fatihah');
    }

    public function test_store_document_rejects_invalid_source_type(): void
    {
        $this->actingAsSubAdmin();

        $this->postJson('/api/v1/documents', [
            'source_type' => 'docx',
            'text_content' => 'foo',
        ])->assertStatus(422);
    }

    public function test_admin_can_list_documents(): void
    {
        $this->actingAsSubAdmin();

        $this->postJson('/api/v1/documents', [
            'source_type' => 'text',
            'text_content' => 'محتوى',
        ]);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_new_document_is_not_approved(): void
    {
        $this->actingAsSubAdmin();

        $this->postJson('/api/v1/documents', [
            'source_type' => 'text',
            'text_content' => 'محتوى',
        ])
            ->assertCreated()
            ->assertJsonPath('data.is_approved', false);
    }

    public function test_document_becomes_approved_when_an_ai_output_is_approved(): void
    {
        $user = $this->actingAsSubAdmin();

        $document = Document::query()->create([
            'uploader_id' => $user->id,
            'source_type' => 'text',
            'extracted_text' => 'محتوى',
        ]);

        $this->getJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.is_approved', false);

        $operation = Operation::query()->create([
            'document_id' => $document->id,
            'category' => 'aqeedah',
            'operation_type' => 'summarization',
            'status' => 'completed',
        ]);

        $aiOutput = AiOutput::query()->create([
            'operation_id' => $operation->id,
            'raw_json' => ['summary' => 'الملخص الأصلي'],
        ]);

        $this->postJson('/api/v1/approved-versions', [
            'ai_output_id' => $aiOutput->id,
            'edited_content' => ['summary' => 'الملخص المعتمد'],
        ])->assertCreated();

        $this->getJson("/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('data.is_approved', true);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $document->id)
            ->assertJsonPath('data.data.0.is_approved', true);
    }

    public function test_document_is_not_approved_when_only_operations_exist(): void
    {
        $user = $this->actingAsSubAdmin();

        $document = Document::query()->create([
            'uploader_id' => $user->id,
            'source_type' => 'text',
            'extracted_text' => 'محتوى',
        ]);

        Operation::query()->create([
            'document_id' => $document->id,
            'category' => 'aqeedah',
            'operation_type' => 'summarization',
            'status' => 'completed',
        ]);

        $this->getJson('/api/v1/documents')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $document->id)
            ->assertJsonPath('data.data.0.is_approved', false);
    }
}
