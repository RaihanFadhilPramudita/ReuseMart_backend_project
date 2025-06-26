<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DetailTransaksi;

class Transaksi extends Model
{
    use HasFactory;

    protected $table = 'transaksi';
    protected $primaryKey = 'ID_TRANSAKSI';
    public $timestamps = false;

    protected $fillable = [
        'ID_PEGAWAI',
        'ID_PEMBELI',
        'NO_NOTA',
        'WAKTU_PESAN',
        'WAKTU_BAYAR',
        'TOTAL_HARGA',
        'ONGKOS_KIRIM',
        'POTONGAN_POIN',
        'TOTAL_AKHIR',
        'POIN_DIDAPAT',
        'STATUS_TRANSAKSI',
        'BUKTI_TRANSFER',
        'STATUS_VALIDASI_PEMBAYARAN',
        'TANGGAL_VERIFIKASI',
        'RATING',
        'JENIS_DELIVERY'
    ];

    protected $casts = [
        'WAKTU_PESAN' => 'datetime',
        'WAKTU_BAYAR' => 'datetime',
        'TANGGAL_VERIFIKASI' => 'datetime',
        'TOTAL_HARGA' => 'decimal:2',
        'ONGKOS_KIRIM' => 'decimal:2',
        'POTONGAN_POIN' => 'decimal:2',
        'TOTAL_AKHIR' => 'decimal:2',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI', 'ID_PEGAWAI');
    }

    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'ID_PEMBELI', 'ID_PEMBELI');
    }

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'ID_TRANSAKSI', 'ID_TRANSAKSI');
    }

    public function pengiriman()
    {
        return $this->hasOne(Pengiriman::class, 'ID_TRANSAKSI', 'ID_TRANSAKSI');
    }

    public function pengambilan()
    {
        return $this->hasOne(Pengambilan::class, 'ID_TRANSAKSI', 'ID_TRANSAKSI');
    }

    public function alamat()
    {
        return $this->belongsTo(Alamat::class, 'ID_ALAMAT', 'ID_ALAMAT');
    }
    
}