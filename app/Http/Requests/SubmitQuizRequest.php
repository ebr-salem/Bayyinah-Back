<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'array'],
            'answers.*.question_text' => ['required', 'string', 'max:1000'],
            'answers.*.participant_answer' => ['required', 'string', 'max:1000'],
            'answers.*.correct_answer' => ['required', 'string', 'max:1000'],
        ];
    }
}
