<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['ai_output_id', 'edited_content', 'approver_id'])]
class ApprovedVersion extends Model
{
    protected function casts(): array
    {
        return [
            'edited_content' => 'array',
        ];
    }

    public function aiOutput(): BelongsTo
    {
        return $this->belongsTo(AiOutput::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }
}
