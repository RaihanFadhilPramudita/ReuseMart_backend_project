<?php
// RedeemMerch.php model - Fixed to match actual database structure

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RedeemMerch extends Model
{
    use HasFactory;

    protected $table = 'redeem_merch';
    protected $primaryKey = 'ID_REDEEM';
    public $timestamps = false;

    // 🔥 FIX: Only include columns that actually exist in the database
    protected $fillable = [
        'ID_PEMBELI',
        'TANGGAL_REDEEM',
        'TANGGAL_AMBIL',
        'STATUS'
    ];

    protected $casts = [
        'TANGGAL_REDEEM' => 'date',
        'TANGGAL_AMBIL' => 'date',
    ];

    // Relationships
    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'ID_PEMBELI', 'ID_PEMBELI');
    }

    public function detailRedeem()
    {
        return $this->hasMany(DetailRedeem::class, 'ID_REDEEM', 'ID_REDEEM');
    }

    // 🔥 ADD: Helper methods for status management
    public function isPending()
    {
        return $this->STATUS === 'Pending';
    }

    public function isCompleted()
    {
        return $this->STATUS === 'Completed';
    }

    public function isCancelled()
    {
        return $this->STATUS === 'Cancelled';
    }

    // 🔥 ADD: Status constants
    const STATUS_PENDING = 'Pending';
    const STATUS_COMPLETED = 'Completed';
    const STATUS_CANCELLED = 'Cancelled';
    const STATUS_PROCESSING = 'Processing';

    public static function getValidStatuses()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_PROCESSING,
        ];
    }

    // 🔥 ADD: Calculate total points from detail records (since TOTAL_POIN column doesn't exist)
    public function getTotalPointsAttribute()
    {
        return $this->detailRedeem->sum(function ($detail) {
            return $detail->merchandise->POIN_REQUIRED * $detail->JUMLAH_MERCH;
        });
    }

    // 🔥 ADD: Get formatted total points
    public function getFormattedTotalPointsAttribute()
    {
        return number_format($this->total_points) . ' poin';
    }
}