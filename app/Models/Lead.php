<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'lead_type',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}