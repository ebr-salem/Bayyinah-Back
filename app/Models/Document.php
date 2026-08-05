<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uploader_id', 'source_type', 'extracted_text'])]
class Document extends Model
{
    public const SOURCE_TEXT = 'text';

    public const SOURCE_PDF = 'pdf';

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }
}
