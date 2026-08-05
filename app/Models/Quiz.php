<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['approved_version_id', 'unique_token', 'is_active'])]
class Quiz extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function approvedVersion(): BelongsTo
    {
        return $this->belongsTo(ApprovedVersion::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(QuizSubmission::class);
    }
}
