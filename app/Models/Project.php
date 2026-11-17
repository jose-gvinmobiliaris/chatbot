<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'location',
        'average_price',
        'construction_status',
        'url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'average_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            Conversation::class,
            'conversation_project'
        )->withTimestamps();
    }
}