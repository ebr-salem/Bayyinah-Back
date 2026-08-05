<?php

namespace Tests\Feature;

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
}
