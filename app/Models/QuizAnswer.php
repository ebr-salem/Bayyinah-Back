<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['submission_id', 'question_text', 'participant_answer', 'correct_answer', 'is_correct'])]
class QuizAnswer extends Model
{
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(QuizSubmission::class);
    }
}
