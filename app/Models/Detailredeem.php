<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailRedeem extends Model
{
    use HasFactory;

    protected $table = 'detail_redeem';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID_MERCHANDISE',
        'ID_REDEEM',
        'JUMLAH_MERCH'
    ];

    public function merchandise()
    {
        return $this->belongsTo(Merchandise::class, 'ID_MERCHANDISE', 'ID_MERCHANDISE');
    }

    public function redeemMerch()
    {
        return $this->belongsTo(RedeemMerch::class, 'ID_REDEEM', 'ID_REDEEM');
    }
}