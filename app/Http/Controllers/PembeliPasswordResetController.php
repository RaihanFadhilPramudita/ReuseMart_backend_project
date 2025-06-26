<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Pembeli;
use App\Models\Penitip;
use App\Models\Organisasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Notifications\PembeliResetPasswordNotification;

class PembeliPasswordResetController extends Controller
{
    /**
     * Display the password reset link request view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:pembeli,penitip,organisasi'],
        ]);

        $user = $this->findUserByEmail($request->email, $request->role);
        
        if (!$user) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Alamat email tidak ditemukan.')]);
        }
        
        // Generate token
        $token = Str::random(64);
        
        // Store token in database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'email' => $request->email,
                'token' => Hash::make($token),
                'created_at' => now()
            ]
        );
        
        // Send notification
        $user->notify(new PembeliResetPasswordNotification($token, $request->role));
        
        return back()->with('status', __('Link reset password telah dikirim ke alamat email Anda!'));
    }

    /**
     * Display the password reset view.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function resetForm(Request $request)
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Reset the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'type' => ['required', 'in:pembeli,penitip,organisasi'],
        ]);

        // Find token in database
        $tokenRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();
                        
        if (!$tokenRecord) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Token tidak valid.')]);
        }
        
        // Check if token is valid and not expired
        if (!Hash::check($request->token, $tokenRecord->token)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Token tidak valid.')]);
        }
        
        if (Carbon::parse($tokenRecord->created_at)->addMinutes(60)->isPast()) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Token telah kadaluarsa.')]);
        }
        
        // Find the user
        $user = $this->findUserByEmail($request->email, $request->type);
        
        if (!$user) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('Pengguna tidak ditemukan.')]);
        }
        
        // Update password
        $user->PASSWORD = Hash::make($request->password);
        $user->save();
        
        // Delete token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();
        
        return redirect()->route('login')->with('status', __('Password berhasil direset!'));
    }

    /**
     * Find user by email and type.
     *
     * @param  string  $email
     * @param  string  $userType
     * @return \Illuminate\Database\Eloquent\Model|null
     */
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