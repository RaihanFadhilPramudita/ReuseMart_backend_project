<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alamat extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'alamat';
    protected $primaryKey = 'ID_ALAMAT';

    protected $fillable = [
        'ID_PEMBELI',
        'NAMA_ALAMAT',
        'ALAMAT_LENGKAP',
        'KECAMATAN',
        'KOTA',
        'KODE_POS',
    ];

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'ID_PEMBELI', 'ID_PEMBELI');
    }
}
