<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'name', 'company_name', 'currency', 'rate_multiplier',
        'is_active', 'billing_notes',
    ];

    protected $casts = [
        'rate_multiplier' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
