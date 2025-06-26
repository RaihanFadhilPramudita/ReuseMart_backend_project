<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchandise extends Model
{
    use HasFactory;

    protected $table = 'merchandise';
    protected $primaryKey = 'ID_MERCHANDISE';
    public $timestamps = false;

    protected $fillable = [
        'NAMA_MERCHANDISE',
        'DESKRIPSI',
        'POIN_REQUIRED',
        'STOK'
    ];

    public function detailRedeem()
    {
        return $this->hasMany(DetailRedeem::class, 'ID_MERCHANDISE', 'ID_MERCHANDISE');
    }
}