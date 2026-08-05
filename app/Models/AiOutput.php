<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['operation_id', 'raw_json'])]
class AiOutput extends Model
{
    protected function casts(): array
    {
        return [
            'raw_json' => 'array',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class);
    }

    public function approvedVersions(): HasMany
    {
        return $this->hasMany(ApprovedVersion::class);
    }
}
