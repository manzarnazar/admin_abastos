<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Diablero extends Authenticatable
{
    use Notifiable;

    protected $guarded = ['id'];

    protected $casts = [
        'zone_id' => 'integer',
        'status' => 'boolean',
        'active' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'identity_image' => 'array',
        'location_updated_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'auth_token',
    ];

    protected $appends = ['image_full_url'];

    public function getFullNameAttribute(): string
    {
        return trim($this->f_name . ' ' . $this->l_name);
    }

    public function getImageFullUrlAttribute()
    {
        return Helpers::get_full_url('diablero', $this->image, 'public');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function wallet()
    {
        return $this->hasOne(DiableroWallet::class);
    }

    public function tasks()
    {
        return $this->hasMany(DiableroTask::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(DiableroWalletTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->where('application_status', 'approved');
    }

    public function scopeOnline($query)
    {
        return $query->where('active', 1);
    }
}
