<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Organisasi extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'organisasi';
    protected $primaryKey = 'ID_ORGANISASI';
    public $timestamps = false;

    protected $fillable = [
        'NAMA_ORGANISASI',
        'ALAMAT',
        'EMAIL',
        'ID_PEGAWAI',
        'USERNAME',
        'PASSWORD',
        'NO_TELEPON'
    ];

    protected $hidden = [
        'PASSWORD',
    ];

    public function getEmailForPasswordReset()
    {
        return $this->EMAIL;
    }

    public function getAuthPassword()
    {
        return $this->PASSWORD;
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'ID_PEGAWAI', 'ID_PEGAWAI');
    }
    
    public function requestDonasi()
    {
        return $this->hasMany(RequestDonasi::class, 'ID_ORGANISASI', 'ID_ORGANISASI');
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new PembeliResetPasswordNotification($token));
    }

    public function routeNotificationForMail()
    {
        return $this->EMAIL;
    }

    public function getAuthIdentifierName()
    {
        return 'ID_ORGANISASI';
    }

}