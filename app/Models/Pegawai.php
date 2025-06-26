<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pegawai extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'pegawai';
    protected $primaryKey = 'ID_PEGAWAI';
    public $timestamps = false;

    protected $fillable = [
        'ID_JABATAN',
        'EMAIL',
        'PASSWORD',  
        'NAMA_PEGAWAI',
        'NO_TELEPON',
        'TANGGAL_LAHIR',
        'TANGGAL_BERGABUNG',
        'USERNAME',
        'fcm_token', 
        'fcm_token_updated_at'
    ];

    protected $hidden = [
        'PASSWORD',
        'fcm_token' 
    ];

      protected $casts = [
        'fcm_token_updated_at' => 'datetime' // Cast timestamp
    ];

    public function getAuthPassword()
    {
        return $this->PASWORD;
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class, 'ID_JABATAN', 'ID_JABATAN');
    }
    
    public function diskusi()
    {
        return $this->hasMany(Diskusi::class, 'ID_PEGAWAI');
    }
    

}