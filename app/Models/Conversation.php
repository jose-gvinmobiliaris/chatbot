<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Conversation extends Model
{
    protected $fillable = [
        'lead_id',
        'session_token',
        'status',
        'flow_state',
        'last_interaction_at',
        'closed_at',
        'flow_data',
    ];

    protected function casts(): array
    {
        return [
            'flow_data' => 'array',
            'last_interaction_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
