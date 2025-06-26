<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengiriman extends Model
{
    use HasFactory;

    protected $table = 'pengiriman';
    protected $primaryKey = 'ID_PENGIRIMAN';
    public $timestamps = false;

    protected $fillable = [
        'ID_PEGAWAI',
        'ID_TRANSAKSI',
        'BIAYA_PENGIRIMAN',
        'STATUS_PENGIRIMAN',
        'TANGGAL_KIRIM',
        'TANGGAL_DITERIMA'
    ];

    protected $casts = [
        'BIAYA_PENGIRIMAN' => 'decimal:2',
        'TANGGAL_KIRIM' => 'datetime',
        'TANGGAL_DITERIMA' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI', 'ID_PEGAWAI');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'ID_TRANSAKSI', 'ID_TRANSAKSI');
    }
}