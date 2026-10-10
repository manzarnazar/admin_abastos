<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiableroTask extends Model
{
    public const PENDING = 'pending';
    public const AVAILABLE = 'available';
    public const ACCEPTED = 'accepted';
    public const ARRIVED = 'arrived';
    public const COLLECTED = 'collected';
    public const IN_TRANSIT = 'in_transit';
    public const HANDED_OVER = 'handed_over';
    public const FAILED = 'failed';
    public const CANCELED = 'canceled';

    public const OPEN_STATUSES = [self::PENDING, self::AVAILABLE, self::ACCEPTED, self::ARRIVED, self::COLLECTED, self::IN_TRANSIT];
    public const ONGOING_STATUSES = [self::ACCEPTED, self::ARRIVED, self::COLLECTED, self::IN_TRANSIT];
    public const CLOSED_STATUSES = [self::HANDED_OVER, self::FAILED, self::CANCELED];

    protected $guarded = ['id'];

    protected $hidden = ['handover_code'];

    protected $casts = [
        'order_group_id' => 'integer',
        'zone_id' => 'integer',
        'diablero_id' => 'integer',
        'loading_zone_id' => 'integer',
        'delivery_man_id' => 'integer',
        'diablero_fee' => 'float',
        'earning' => 'float',
        'available_at' => 'datetime',
        'accepted_at' => 'datetime',
        'arrived_at' => 'datetime',
        'collected_at' => 'datetime',
        'in_transit_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'failed_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function group()
    {
        return $this->belongsTo(OrderGroup::class, 'order_group_id');
    }

    public function diablero()
    {
        return $this->belongsTo(Diablero::class);
    }

    public function deliveryMan()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function loadingZone()
    {
        return $this->belongsTo(LoadingZone::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function stops()
    {
        return $this->hasMany(DiableroTaskStop::class)->orderBy('sequence');
    }

    public function logs()
    {
        return $this->hasMany(DiableroTaskLog::class)->latest('id');
    }

    public function rejections()
    {
        return $this->hasMany(DiableroTaskRejection::class);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }
}
