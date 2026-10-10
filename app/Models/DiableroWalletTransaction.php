<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiableroWalletTransaction extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'diablero_id' => 'integer',
        'diablero_task_id' => 'integer',
        'order_id' => 'integer',
        'credit' => 'float',
        'debit' => 'float',
        'balance' => 'float',
    ];

    public function diablero()
    {
        return $this->belongsTo(Diablero::class);
    }

    public function task()
    {
        return $this->belongsTo(DiableroTask::class, 'diablero_task_id');
    }
}
