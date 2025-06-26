<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'cart'; // nama tabel di database

    protected $primaryKey = 'ID_CART'; // jika tidak pakai id

    public $timestamps = false;

    protected $fillable = [
        'ID_PEMBELI',
        'ID_BARANG',
        'JUMLAH',
    ];

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'ID_PEMBELI');
    }

    public function barang()
    {
        return $this->belongsTo(Barang::class, 'ID_BARANG');
    }
}
