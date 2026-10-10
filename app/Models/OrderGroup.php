<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderGroup extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'is_guest' => 'boolean',
        'zone_id' => 'integer',
        'module_id' => 'integer',
        'loading_zone_id' => 'integer',
        'order_amount' => 'float',
        'delivery_charge' => 'float',
        'diablero_fee' => 'float',
    ];

    protected $hidden = ['otp'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function task()
    {
        return $this->hasOne(DiableroTask::class);
    }

    public function loadingZone()
    {
        return $this->belongsTo(LoadingZone::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
