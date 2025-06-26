<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The reset URL.
     *
     * @var string
     */
    public $url;
    
    /**
     * The user type.
     *
     * @var string
     */
    public $userType;
    
    /**
     * The user model.
     *
     * @var mixed
     */
    public $user;

    /**
     * Create a new message instance.
     *
     * @param  string  $url
     * @param  string  $userType
     * @param  mixed  $user
     * @return void
     */
    public function __construct($url, $userType, $user)
    {
        $this->url = $url;
        $this->userType = $userType;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('ReuseMart - Reset Password')
                    ->view('emails.reset-password', [
                        'url' => $this->url,
                        'userType' => $this->getUserTypeLabel(),
                        'expireTime' => 60,
                        'userName' => $this->getUserName(),
                    ]);
    }
    
    /**
     * Get user-friendly type name.
     *
     * @return string
     */
    protected function getUserTypeLabel()
    {
        $labels = [
            'pembeli' => 'Pembeli',
            'penitip' => 'Penitip',
            'organisasi' => 'Organisasi',
        ];

        return $labels[$this->userType] ?? 'Pengguna';
    }

    /**
     * Get appropriate user name based on model
     *
     * @return string
     */
    protected function getUserName()
    {
        switch ($this->userType) {
            case 'pembeli':
                return $this->user->NAMA_PEMBELI ?? 'Pembeli';
            case 'penitip':
                return $this->user->NAMA_PENITIP ?? 'Penitip';
            case 'organisasi':
                return $this->user->NAMA_ORGANISASI ?? 'Organisasi';
            default:
                return 'Pengguna';
        }
    }
}