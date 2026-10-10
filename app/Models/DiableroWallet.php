<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiableroWallet extends Model
{
    protected $fillable = ['diablero_id'];

    protected $casts = [
        'diablero_id' => 'integer',
        'total_earning' => 'float',
        'total_withdrawn' => 'float',
        'pending_withdraw' => 'float',
    ];

    protected $appends = ['balance'];

    public function getBalanceAttribute(): float
    {
        return (float) round($this->total_earning - $this->total_withdrawn - $this->pending_withdraw, 2);
    }

    public function diablero()
    {
        return $this->belongsTo(Diablero::class);
    }
}
