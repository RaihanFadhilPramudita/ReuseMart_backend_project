<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pengambilan extends Model
{
    use HasFactory;

    protected $table = 'pengambilan';
    protected $primaryKey = 'ID_PENGAMBILAN';
    public $timestamps = false;

    protected $fillable = [
        'ID_PEGAWAI',
        'JADWAL_PENGAMBILAN',
        'STATUS_PENGEMBALIAN',
        'TANGGAL_DIAMBIL',
        'ID_TRANSAKSI'
    ];

    protected $casts = [
        'JADWAL_PENGAMBILAN' => 'datetime',
        'TANGGAL_DIAMBIL' => 'datetime',
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