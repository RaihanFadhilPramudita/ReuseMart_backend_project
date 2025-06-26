<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DetailTransaksi extends Model
{
    use HasFactory;

    protected $table = 'detail_transaksi';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ID_BARANG',
        'ID_TRANSAKSI',
        'JUMLAH'
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function transaksi()
    {
        return $this->belongsTo(Transaksi::class, 'ID_TRANSAKSI', 'ID_TRANSAKSI');
    }
}