<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiAuditLog extends Model
{
    use HasFactory;
    protected $guarded = [];

    protected $casts = [
        'raw_ai_output'    => 'array',
        'validated_output' => 'array',
        'resolution'       => 'array',
        'pricing'          => 'array',
        'warnings'         => 'array',
    ];
}
