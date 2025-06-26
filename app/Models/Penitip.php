<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Penitip extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'penitip';
    protected $primaryKey = 'ID_PENITIP';
    public $timestamps = false;

    protected $fillable = [
        'EMAIL',
        'PASSWORD',
        'NAMA_PENITIP',
        'NO_TELEPON',
        'NO_KTP',
        'TANGGAL_LAHIR',
        'TANGGAL_REGISTRASI',
        'SALDO',
        'BADGE',
        'RATING',
        'FOTO_KTP',
        'FOTO_PROFILE',
        'POIN_SOSIAL',
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

    protected $appends = ['foto_profile_url', 'foto_ktp_url'];


    public function getFotoProfileUrlAttribute()
    {
        return $this->FOTO_PROFILE ? asset('storage/' . $this->FOTO_PROFILE) : null;
    }

    public function getFotoKtpUrlAttribute()
    {
        return $this->FOTO_KTP ? asset('storage/' . $this->FOTO_KTP) : null;
    }


    public function getEmailForPasswordReset()
    {
        return $this->EMAIL;
    }
    
    public function getAuthPassword()
    {
        return $this->PASSWORD;
    }

    public function penitipan()
    {
        return $this->hasMany(Penitipan::class, 'ID_PENITIP', 'ID_PENITIP');
    }
    
    public function barang()
    {
        return $this->hasMany(Barang::class, 'ID_PENITIP', 'ID_PENITIP');
    }
}