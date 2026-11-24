<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QcInspectionActivity extends Model
{
    protected $fillable = [
        'qc_inspection_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'reason',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(
            QcInspection::class,
            'qc_inspection_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}