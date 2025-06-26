<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailPenitipan extends Model
{
    use HasFactory;

    protected $table = 'detail_penitipan';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID_PENITIPAN',
        'ID_BARANG',
        'JUMLAH_BARANG_TITIPAN'
    ];

    public function penitipan()
    {
        return $this->belongsTo(Penitipan::class, 'ID_PENITIPAN', 'ID_PENITIPAN');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG', 'ID_BARANG');
    }
}