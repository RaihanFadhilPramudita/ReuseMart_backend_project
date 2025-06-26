<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Donasi extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'donasi';
    protected $primaryKey = 'ID_DONASI';

    protected $fillable = [
        'ID_BARANG',
        'TANGGAL_DONASI',
        'NAMA_PENERIMA',
        'JENIS_BARANG',
        'ID_REQUEST'
    ];

    protected $casts = [
        'TANGGAL_DONASI' => 'date',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG', 'ID_BARANG');
    }


    public function requestDonasi()
    {
        return $this->belongsTo(RequestDonasi::class, 'ID_REQUEST');
    }

}
