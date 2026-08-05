<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperationRequest extends FormRequest
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
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'category' => ['required', 'string', 'in:aqeedah,fiqh,seerah,tazkiyah'],
            'operation_type' => ['required', 'string', 'in:summarization,question_generation'],
        ];
    }
}
