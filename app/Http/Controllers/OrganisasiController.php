<?php

namespace App\Http\Controllers;

use App\Models\Organisasi;
use App\Models\RequestDonasi;
use App\Models\Donasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Notifications\PembeliResetPasswordNotification;

class OrganisasiController extends Controller
{
    
   public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'required|string|max:255|unique:organisasi,NAMA_ORGANISASI',
            'alamat' => 'required|string',
            'email' => 'required|email|unique:organisasi,EMAIL',
            'username' => 'required|string|max:255|unique:organisasi,USERNAME',
            'password' => 'required|string|min:6',
            'no_telepon' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organisasi = new Organisasi();
        $organisasi->NAMA_ORGANISASI = $request->nama_organisasi;
        $organisasi->ALAMAT = $request->alamat;
        $organisasi->EMAIL = $request->email;
        $organisasi->USERNAME = $request->username;
        $organisasi->PASSWORD = Hash::make($request->password);
        $organisasi->NO_TELEPON = $request->no_telepon;
        $organisasi->save();

        return response()->json([
            'message' => 'Organization registered successfully',
            'data' => $organisasi
        ], 201);
    }
    
   public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Organisasi::where('USERNAME', $request->username)->first();
        
        if (!$user || !Hash::check($request->password, $user->PASSWORD)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('OrganisasiToken')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $organisasi = Auth::guard('organisasi')->user();
        if (!$organisasi) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->user('organisasi')->currentAccessToken()->delete();
        return response()->json(['message' => 'Organisasi logged out successfully']);
    }


   public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:organisasi,EMAIL',
        ]);

        $user = Organisasi::where('EMAIL', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
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

        // Send email notification
        $user->notify(new PembeliResetPasswordNotification($token, 'organisasi'));

        return response()->json(['message' => 'Reset link sent to your email.']);
    }
    
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::broker('organisasi')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->PASSWORD = Hash::make($password);
                $user->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset'])
            : response()->json(['message' => 'Failed to reset password'], 500);
    }
    public function index()
    {
        $organisasi = Organisasi::with(['pegawai'])->get();
        return response()->json(['data' => $organisasi]);
    }
    
    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }
    
    public function show($id)
    {
        $organisasi = Organisasi::with(['pegawai'])->findOrFail($id);
        return response()->json(['data' => $organisasi]);
    }
    
    public function update(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_ORGANISASI;
        }
        
        $validator = Validator::make($request->all(), [
            'nama_organisasi' => 'string|max:255',
            'alamat' => 'string',
            'email' => 'email|unique:organisasi,EMAIL,'.$id.',ID_ORGANISASI',
            'username' => 'string|max:255|unique:organisasi,USERNAME,'.$id.',ID_ORGANISASI',
            'no_telepon' => 'string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $organisasi = Organisasi::findOrFail($id);
        
        if($request->has('nama_organisasi')) $organisasi->NAMA_ORGANISASI = $request->nama_organisasi;
        if($request->has('alamat')) $organisasi->ALAMAT = $request->alamat;
        if($request->has('email')) $organisasi->EMAIL = $request->email;
        if($request->has('username')) $organisasi->USERNAME = $request->username;
        if($request->has('no_telepon')) $organisasi->NO_TELEPON = $request->no_telepon;
        
        $organisasi->save();
        
        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => $organisasi
        ]);
    }
    
    public function changePassword(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_ORGANISASI;
        }
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|different:current_password',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $organisasi = Organisasi::findOrFail($id);
        
        if (!Hash::check($request->current_password, $organisasi->PASSWORD)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }
        
        $organisasi->PASSWORD = Hash::make($request->new_password);
        $organisasi->save();
        
        return response()->json(['message' => 'Password changed successfully']);
    }
    
    public function destroy($id)
    {
        $organisasi = Organisasi::findOrFail($id);
        
        $organisasi->delete();
        
        return response()->json(['message' => 'Organization deleted successfully']);
    }
    
    public function donationRequests(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_ORGANISASI;
        }
        
        $requests = RequestDonasi::where('ID_ORGANISASI', $id)
            ->orderBy('TANGGAL_REQUEST', 'desc')
            ->get();
            
        return response()->json(['data' => $requests]);
    }
    
    public function search(Request $request)
    {
        $keyword = $request->input('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter q diperlukan.'], 422);
        }

        $organisasi = Organisasi::where('NAMA_ORGANISASI', 'like', "%{$keyword}%")
            ->orWhere('EMAIL', 'like', "%{$keyword}%")
            ->orWhere('NO_TELEPON', 'like', "%{$keyword}%")
            ->orWhere('USERNAME', 'like', "%{$keyword}%")
            ->paginate(10);

        return response()->json(['data' => $organisasi]);
    }


    public function donationsReceived(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_ORGANISASI;
        }
        
        $donations = Donasi::with(['barang'])
            ->whereHas('requestDonasi', function($query) use ($id) {
                $query->where('ID_ORGANISASI', $id);
            })
            ->orderBy('TANGGAL_DONASI', 'desc')
            ->get();
            
        return response()->json(['data' => $donations]);
    }
}