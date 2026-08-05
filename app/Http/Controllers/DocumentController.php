<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Requests\UpdateDocumentRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Document;
use App\Services\PdfTextExtractor;
use Illuminate\Http\JsonResponse;
use Throwable;

class DocumentController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $documents = Document::query()
            ->with(['uploader:id,name,email', 'operations:id,document_id,category,operation_type,status'])
            ->latest()
            ->paginate(15);

        return $this->success($documents, 'تم جلب المستندات بنجاح.');
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $extractedText = $validated['text_content'] ?? null;

        if ($validated['source_type'] === Document::SOURCE_PDF) {
            try {
                $extractedText = app(PdfTextExtractor::class)->extract($request->file('file'));
            } catch (Throwable $e) {
                return $this->error('تعذّر استخراج النص من ملف PDF، يرجى التأكد من أن الملف يحتوي على نص قابل للقراءة.', 422);
            }
        }

        $document = Document::query()->create([
            'uploader_id' => $request->user()->id,
            'source_type' => $validated['source_type'],
            'extracted_text' => $this->sanitize($extractedText),
        ]);

        return $this->success($document, 'تم رفع المستند بنجاح.', 201);
    }

    public function show(Document $document): JsonResponse
    {
        $document->load(['uploader:id,name,email', 'operations']);

        return $this->success($document, 'تم جلب المستند بنجاح.');
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $validated = $request->validated();

        if (isset($validated['extracted_text'])) {
            $document->extracted_text = $this->sanitize($validated['extracted_text']);
        }

        if (isset($validated['source_type'])) {
            $document->source_type = $validated['source_type'];
        }

        $document->save();

        return $this->success($document, 'تم تحديث المستند بنجاح.');
    }

    public function destroy(Document $document): JsonResponse
    {
        $document->delete();

        return $this->success(null, 'تم حذف المستند بنجاح.');
    }

    private function sanitize(?string $text): string
    {
        return $text === null ? '' : strip_tags($text);
    }
}
