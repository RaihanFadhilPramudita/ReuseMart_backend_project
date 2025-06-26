<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Penitipan extends Model
{
    use HasFactory;

    protected $table = 'penitipan';
    protected $primaryKey = 'ID_PENITIPAN';
    public $timestamps = false;

    protected $fillable = [
        'ID_PENITIP',
        'TANGGAL_MASUK',
        'TANGGAL_KADALUARSA',
        'TANGGAL_BATAS_AMBIL',
        'STATUS_PENITIPAN',
        'PERPANJANGAN',
        'PEGAWAI_HUNTER'
    ];

    protected $casts = [
        'TANGGAL_MASUK' => 'date',
        'TANGGAL_KADALUARSA' => 'date',
        'TANGGAL_BATAS_AMBIL' => 'date',
        'STATUS_PENITIPAN' => 'boolean',
        'PERPANJANGAN' => 'boolean',
    ];

    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'ID_PENITIP', 'ID_PENITIP');
    }

    public function detailPenitipan()
    {
        return $this->hasMany(DetailPenitipan::class, 'ID_PENITIPAN', 'ID_PENITIPAN');
    }

    public function hunter()
    {
        return $this->belongsTo(Pegawai::class, 'PEGAWAI_HUNTER', 'ID_PEGAWAI');
    }


}