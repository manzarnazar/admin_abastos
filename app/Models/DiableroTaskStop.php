<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiableroTaskStop extends Model
{
    public const PENDING = 'pending';
    public const COLLECTED = 'collected';
    public const FAILED = 'failed';
    public const CANCELED = 'canceled';

    protected $guarded = ['id'];

    protected $casts = [
        'diablero_task_id' => 'integer',
        'order_id' => 'integer',
        'store_id' => 'integer',
        'sequence' => 'integer',
        'collected_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(DiableroTask::class, 'diablero_task_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
