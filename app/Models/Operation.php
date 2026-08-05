<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['document_id', 'category', 'operation_type', 'status'])]
class Operation extends Model
{
    public const CATEGORY_AQEEDAH = 'aqeedah';

    public const CATEGORY_FIQH = 'fiqh';

    public const CATEGORY_SEERAH = 'seerah';

    public const CATEGORY_TAZKIYAH = 'tazkiyah';

    public const TYPE_SUMMARIZATION = 'summarization';

    public const TYPE_QUESTION_GENERATION = 'question_generation';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function aiOutput(): HasOne
    {
        return $this->hasOne(AiOutput::class);
    }
}
