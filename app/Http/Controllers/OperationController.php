<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationRequest;
use App\Http\Traits\ApiResponse;
use App\Models\AiOutput;
use App\Models\Document;
use App\Models\GlobalSetting;
use App\Models\Operation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

class OperationController extends Controller
{
    use ApiResponse;

    public function store(StoreOperationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $limit = (int) (GlobalSetting::get(GlobalSetting::AI_MONTHLY_LIMIT_KEY) ?? '0');
        $used = Operation::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($limit > 0 && $used >= $limit) {
            return $this->error('لقد تم تجاوز الحدّ الشهري المسموح به من عمليات الذكاء الاصطناعي.', 429);
        }

        $document = Document::query()->findOrFail($validated['document_id']);

        $operation = Operation::query()->create([
            'document_id' => $document->id,
            'category' => $validated['category'],
            'operation_type' => $validated['operation_type'],
            'status' => Operation::STATUS_PENDING,
        ]);

        $payload = [
            'operation_id' => $operation->id,
            'document_id' => $document->id,
            'category' => $operation->category,
            'operation_type' => $operation->operation_type,
            'text' => $document->extracted_text,
        ];

        $fastApiUrl = rtrim((string) env('FASTAPI_INTERNAL_URL'), '/').'/process';

        try {
            $response = Http::timeout(60)->post($fastApiUrl, $payload);

            $response->throw();
        } catch (Throwable $e) {
            $operation->update(['status' => Operation::STATUS_FAILED]);

            return $this->error(
                'تعذّر الاتصال بخدمة الذكاء الاصطناعي. يرجى المحاولة مرة أخرى لاحقاً.',
                500
            );
        }

        $rawJson = $response->json();

        $aiOutput = AiOutput::query()->create([
            'operation_id' => $operation->id,
            'raw_json' => $rawJson,
        ]);

        $operation->update(['status' => Operation::STATUS_COMPLETED]);

        return $this->success($aiOutput, 'تم تنفيذ العملية بنجاح.', 201);
    }
}
