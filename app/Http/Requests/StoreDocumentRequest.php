<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source_type' => ['required', 'string', 'in:text,pdf'],
            'file' => ['required_if:source_type,pdf', 'file', 'mimes:pdf', 'max:20480'],
            'text_content' => ['required_if:source_type,text', 'string', 'max:200000'],
        ];
    }
}
