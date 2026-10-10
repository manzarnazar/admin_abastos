<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoadingZone extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'zone_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public static function defaultForZone(?int $zoneId): ?self
    {
        if (!$zoneId) {
            return null;
        }
        return static::active()->where('zone_id', $zoneId)
            ->orderByDesc('is_default')->orderBy('id')->first();
    }
}
