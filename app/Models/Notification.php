<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'ID_NOTIFICATION';

    protected $fillable = [
        'user_type',
        'user_id', 
        'type',
        'title',
        'message',
        'data',
        'is_sent',
        'is_read',
        'sent_at',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'is_sent' => 'boolean',
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'read_at' => 'datetime'
    ];

    // Relationships
    public function pembeli()
    {
        return $this->belongsTo(Pembeli::class, 'user_id', 'ID_PEMBELI')
                    ->where('user_type', 'pembeli');
    }

    public function penitip()
    {
        return $this->belongsTo(Penitip::class, 'user_id', 'ID_PENITIP')
                    ->where('user_type', 'penitip');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'user_id', 'ID_PEGAWAI')
                    ->where('user_type', 'pegawai');
    }
}