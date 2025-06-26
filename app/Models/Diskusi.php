<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Diskusi extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'diskusi';
    protected $primaryKey = 'ID_DISKUSI';

    protected $fillable = [
        'ID_BARANG',
        'ID_PEMBELI',
        'ISI_PESAN',
        'ID_PEGAWAI'
    ];

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'ID_PEMBELI');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG');
    }
}
