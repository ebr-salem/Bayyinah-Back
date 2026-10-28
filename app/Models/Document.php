<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['uploader_id', 'source_type', 'extracted_text'])]
class Document extends Model
{
    public const SOURCE_TEXT = 'text';

    public const SOURCE_PDF = 'pdf';

    protected $hidden = ['approved_operations_count'];

    protected $appends = ['is_approved'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(Operation::class);
    }

    protected function isApproved(): Attribute
    {
        return Attribute::get(function () {
            if (array_key_exists('approved_operations_count', $this->attributes)) {
                return (int) $this->attributes['approved_operations_count'] > 0;
            }

            return $this->operations()->whereHas('aiOutput.approvedVersions')->exists();
        });
    }
}
