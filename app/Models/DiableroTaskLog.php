<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiableroTaskLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'diablero_task_id' => 'integer',
        'diablero_task_stop_id' => 'integer',
        'actor_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function task()
    {
        return $this->belongsTo(DiableroTask::class, 'diablero_task_id');
    }
}
