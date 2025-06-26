<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestDonasi extends Model
{
    use HasFactory;

    protected $table = 'request_donasi';
    protected $primaryKey = 'ID_REQUEST';
    public $timestamps = false;

    protected $fillable = [
        'ID_ORGANISASI',
        'TANGGAL_REQUEST',
        'STATUS_REQUEST',
        'NAMA_BARANG',
        'DESKRIPSI'
    ];

    protected $casts = [
        'TANGGAL_REQUEST' => 'date',
    ];

    public function organisasi()
    {
        return $this->belongsTo(Organisasi::class, 'ID_ORGANISASI', 'ID_ORGANISASI');
    }

    public function donasi()
    {
        return $this->hasOne(Donasi::class, 'ID_REQUEST', 'ID_REQUEST');
    }
}