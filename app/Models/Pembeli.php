<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pembeli extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'pembeli';
    protected $primaryKey = 'ID_PEMBELI';
    public $timestamps = false;

    protected $fillable = [
        'EMAIL',
        'PASSWORD',
        'NAMA_PEMBELI',
        'NO_TELEPON',
        'TANGGAL_LAHIR',
        'TANGGAL_REGISTRASI',
        'POIN',
        'fcm_token', 
        'fcm_token_updated_at' 
    ];

    protected $hidden = [
        'PASSWORD',
        'fcm_token'
    ];

    protected $casts = [
        'POIN' => 'decimal:2',
        'fcm_token_updated_at' => 'datetime'
    ];
    

    public function getEmailForPasswordReset()
    {
        return $this->EMAIL;
    }

    public function getAuthPassword()
    {
        return $this->PASSWORD;
    }

    public function alamat()
    {
        return $this->hasMany(Alamat::class, 'ID_PEMBELI', 'ID_PEMBELI');
    }
    
    public function transaksi()
    {
        return $this->hasMany(Transaksi::class, 'ID_PEMBELI', 'ID_PEMBELI');
    }

    public function diskusi()
    {
        return $this->hasMany(Diskusi::class, 'ID_PEMBELI');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new PembeliResetPasswordNotification($token));
    }

    public function routeNotificationForMail()
    {
        return $this->EMAIL;
    }
    
    
    public function getNameAttribute()
    {
        return $this->NAMA_PEMBELI;
    }
    
    // public function getEmailAttribute()
    // {
    //     return $this->EMAIL;
    // }
    
    public function getAuthIdentifierName()
    {
        return 'ID_PEMBELI';
    }
    



}