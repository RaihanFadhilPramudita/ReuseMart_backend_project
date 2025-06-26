<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Barang extends Model
{
    use HasFactory;

    protected $table = 'barang';
    protected $primaryKey = 'ID_BARANG';
    public $timestamps = false;

    protected $fillable = [
        'ID_PEGAWAI',
        'ID_PENITIP',
        'NAMA_BARANG',
        'DESKRIPSI',
        'HARGA',
        'TANGGAL_MASUK',
        'TANGGAL_AMBIL',
        'TANGGAL_KONFIRMASI_AMBIL',
        'TANGGAL_JUAL',
        'STATUS_BARANG',
        'STATUS_GARANSI',
        'TANGGAL_GARANSI',
        'ID_KATEGORI',
        'GAMBAR',
        'TANGGAL_AJUKAN_AMBIL',
        'TANGGAL_AMBIL'
    ];

    protected $casts = [
        'TANGGAL_MASUK' => 'date',
        'TANGGAL_JUAL' => 'date',
        'TANGGAL_AMBIL' => 'date',
        'TANGGAL_AJUKAN_AMBIL' => 'date',
        'TANGGAL_GARANSI' => 'date',
        'STATUS_GARANSI' => 'boolean',
        'HARGA' => 'decimal:2'
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI', 'ID_PEGAWAI');
    }

    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'ID_PENITIP', 'ID_PENITIP');
    }

    public function kategori()
    {
        return $this->belongsTo(KategoriBarang::class, 'ID_KATEGORI', 'ID_KATEGORI');
    }

    public function detailPenitipan()
    {
        return $this->hasOne(DetailPenitipan::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function detailTransaksi()
    {
        return $this->hasMany(DetailTransaksi::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function donasi()
    {
        return $this->hasOne(Donasi::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function diskusi()
    {
        return $this->hasMany(Diskusi::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function komisi()
    {
        return $this->hasOne(Komisi::class, 'ID_BARANG', 'ID_BARANG');
    }

    public function getSTATUSGARANSIAttribute($value)
    {
        if (!$this->TANGGAL_GARANSI) {
            return false;
        }

        return Carbon::now()->lessThan($this->TANGGAL_GARANSI);
    }

    public function penitipan()
    {
        return $this->hasOne(DetailPenitipan::class, 'ID_BARANG')->with('penitipan');
    }

}