<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The password reset token.
     *
     * @var string
     */
    public $token;

    /**
     * The user type (pembeli, penitip, organisasi).
     *
     * @var string
     */
    protected $userType;

    /**
     * Create a notification instance.
     *
     * @param  string  $token
     * @param  string  $userType
     * @return void
     */
    public function __construct($token, $userType = 'pembeli')
    {
        $this->token = $token;
        $this->userType = $userType;
    }

    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $email = $notifiable->getEmailForPasswordReset();
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
            'type' => $this->userType,
        ], false));

        return (new MailMessage)
            ->subject('ReuseMart - Reset Password')
            ->view('emails.reset-password', [
                'url' => $url,
                'userType' => $this->getUserTypeLabel(),
                'expireTime' => config('auth.passwords.'.$this->userType.'.expire', 60),
                'userName' => $this->getUserName($notifiable),
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
     * @param mixed $notifiable
     * @return string
     */
    protected function getUserName($notifiable)
    {
        switch ($this->userType) {
            case 'pembeli':
                return $notifiable->NAMA_PEMBELI ?? 'Pembeli';
            case 'penitip':
                return $notifiable->NAMA_PENITIP ?? 'Penitip';
            case 'organisasi':
                return $notifiable->NAMA_ORGANISASI ?? 'Organisasi';
            default:
                return 'Pengguna';
        }
    }

    private function findUserByEmail($email, $userType)
    {
        switch ($userType) {
            case 'pembeli':
                return Pembeli::where('EMAIL', $email)->first();
            case 'penitip':
                return Penitip::where('EMAIL', $email)->first();
            case 'organisasi':
                return Organisasi::where('EMAIL', $email)->first();
            default:
                return null;
        }
    }
}