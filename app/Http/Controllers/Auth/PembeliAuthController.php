<?php

namespace App\Http\Controllers\Auth;

use App\Models\Pembeli;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Auth\Events\Registered;
use Carbon\Carbon;

class PembeliAuthController extends BaseAuthController
{
    protected $guardName = 'pembeli';
    protected $userModel = \App\Models\Pembeli::class;
    protected $userType = 'pembeli';
    protected $redirectTo = '/pembeli/dashboard';
    protected $loginView = 'auth.pembeli.login';
    protected $registerView = 'auth.pembeli.register';
    
    public function register(Request $request)
    {
        $request->validate([
            'nama_pembeli' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:pembeli,EMAIL',
            'password' => 'required|string|confirmed|min:8',
            'no_telepon' => 'required|string|max:20',
            'tanggal_lahir' => 'required|date',
        ]);

        $pembeli = Pembeli::create([
            'NAMA_PEMBELI' => $request->nama_pembeli,
            'EMAIL' => $request->email,
            'PASSWORD' => Hash::make($request->password),
            'NO_TELEPON' => $request->no_telepon,
            'TANGGAL_LAHIR' => $request->tanggal_lahir,
            'TANGGAL_REGISTRASI' => Carbon::now(),
            'POIN' => 0,
        ]);

        event(new Registered($pembeli));

        Auth::guard('pembeli')->login($pembeli);

        return redirect($this->redirectTo);
    }
    
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);
        
        $credentials = [
            'EMAIL' => $request->email,
            'password' => $request->password,
        ];

        if (Auth::guard('pembeli')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended($this->redirectTo);
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email', 'remember'));
    }
    
    public function logout(Request $request)
    {
        Auth::guard('pembeli')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}