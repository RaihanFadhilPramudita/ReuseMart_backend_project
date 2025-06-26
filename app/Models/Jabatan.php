<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Jabatan extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'jabatan';
    protected $primaryKey = 'ID_JABATAN';

    protected $fillable = [
        'NAMA_JABATAN',
        'GAJI'
    ];

     protected $casts = [
        'GAJI' => 'decimal:2',
    ];

    public function pegawai()
    {
        return $this->hasMany(\App\Models\Pegawai::class, 'ID_JABATAN', 'ID_JABATAN');
    }

}
