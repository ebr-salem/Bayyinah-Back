<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PdfTextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        // 1. Try pdftotext first
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(['pdftotext', $path, '-'], $descriptors, $pipes);

        if (is_resource($process)) {
            $text = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            $text = trim((string) $text, " \t\n\r\0\x0B\f");
            if ($text !== '') {
                return $text;
            }
        }

        // 2. Scanned / image-based PDF -> AI OCR Fallback
        try {
            $fastApiUrl = rtrim((string) config('services.fastapi.url'), '/') . '/v1/extract-text';
            $apiKey = (string) config('services.fastapi.key');

            $fileContents = file_get_contents($path);
            $fileBase64 = base64_encode((string) $fileContents);

            $response = Http::timeout(180)
                ->withHeaders(['API_KEY' => $apiKey])
                ->post($fastApiUrl, [
                    'filename' => $file->getClientOriginalName() ?: 'scanned.pdf',
                    'file_b64' => $fileBase64,
                ]);

            if ($response->successful()) {
                $extracted = trim((string) ($response->json('extracted_text') ?? ''));
                if ($extracted !== '') {
                    return $extracted;
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        throw new RuntimeException('The PDF does not contain any extractable text.');
    }
}

