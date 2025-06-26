<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KategoriBarang extends Model
{
    use HasFactory;

    protected $table = 'kategori_barang';
    protected $primaryKey = 'ID_KATEGORI';
    public $timestamps = false;

    protected $fillable = [
        'NAMA_KATEGORI'
    ];

    public function barang()
    {
        return $this->hasMany(Barang::class, 'ID_KATEGORI', 'ID_KATEGORI');
    }
}