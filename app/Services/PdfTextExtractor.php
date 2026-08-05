<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class PdfTextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $path = $file->getRealPath();

        $process = proc_open(
            ['pdftotext', $path, '-'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to extract text from PDF.');
        }

        $text = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException('Unable to extract text from PDF: '.trim($error));
        }

        $text = trim((string) $text, " \t\n\r\0\x0B\f");

        if ($text === '') {
            throw new RuntimeException('The PDF does not contain any extractable text.');
        }

        return $text;
    }
}
