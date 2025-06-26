<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Komisi extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'komisi';
    protected $primaryKey = 'ID_KOMISI';

    protected $fillable = [
        'JUMLAH_KOMISI_REUSE_MART',
        'JUMLAH_KOMISI_HUNTER',
        'BONUS_PENITIP',
        'TANGGAL_KOMISI',
        'ID_PENITIP',
        'ID_BARANG',
        'ID_PEGAWAI'
    ];

    protected $casts = [
        'JUMLAH_KOMISI_REUSE_MART' => 'decimal:2',
        'JUMLAH_KOMISI_HUNTER' => 'decimal:2',
        'BONUS_PENITIP' => 'decimal:2',
        'TANGGAL_KOMISI' => 'date',
    ];

    public function pegawai(){
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI', 'ID_PEGAWAI');
    }
    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function penitip(){
        return $this->belongsTo(Penitip::class, 'ID_PENITIP', 'ID_PENITIP');
    }
}
