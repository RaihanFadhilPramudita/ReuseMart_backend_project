<?php

namespace App\Http\Controllers;

use App\Models\Pembeli;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use App\Models\Penitip;

class PembeliController extends Controller
{
    
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:pembeli,EMAIL',
            'password' => 'required|min:6',
            'nama_pembeli' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'tanggal_lahir' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pembeli = Pembeli::create([
            'EMAIL' => $request->email,
            'PASSWORD' => Hash::make($request->password),
            'NAMA_PEMBELI' => $request->nama_pembeli,
            'NO_TELEPON' => $request->no_telepon,
            'TANGGAL_LAHIR' => $request->tanggal_lahir,
            'TANGGAL_REGISTRASI' => Carbon::now(),
            'POIN' => "0"
        ]);

        return response()->json([
            'message' => 'Registration successful',
            'user' => $pembeli
        ], 201);
    }

    public function login(Request $request){

        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Pembeli::where('EMAIL', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->PASSWORD)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('PembeliToken')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }
    public function logout(Request $request)
    {
        $pembeli = Auth::guard('pembeli')->user();
        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->user('pembeli')->currentAccessToken()->delete();
        return response()->json(['message' => 'Pembeli logged out successfully']);
    }


    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:pembeli,EMAIL',
        ]);

        $user = Pembeli::where('EMAIL', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $status = Password::broker('pembelis')->sendResetLink([
            'email' => $user->EMAIL,
        ]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email.'])
            : response()->json(['message' => 'Failed to send reset link.'], 500);
    }
    

    public function index()
    {
        $pembelis = Pembeli::all();
        return response()->json(['data' => $pembelis]);
    }

    public function profile(Request $request)
    {
        $pembeli = $request->user('pembeli');

        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'data' => $pembeli,
            'poin_reward' => $pembeli->POIN,
        ]);
    }

    public function show($id)
    {
        $pembeli = Pembeli::findOrFail($id);
        return response()->json(['data' => $pembeli]);
    }

    public function update(Request $request, $id = null)
    {
        $user = $request->user('pembeli'); // ambil pembeli yang sedang login

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:pembeli,EMAIL,' . $user->ID_PEMBELI . ',ID_PEMBELI',
            'nama_pembeli' => 'string|max:255',
            'no_telepon' => 'string|max:20',
            'tanggal_lahir' => 'date',
            'foto_profile' => 'nullable|image',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('email')) $user->EMAIL = $request->email;
        if ($request->has('nama_pembeli')) $user->NAMA_PEMBELI = $request->nama_pembeli;
        if ($request->has('no_telepon')) $user->NO_TELEPON = $request->no_telepon;
        if ($request->has('tanggal_lahir')) $user->TANGGAL_LAHIR = $request->tanggal_lahir;

       if ($request->hasFile('foto_profile')) {
            if ($user->FOTO_PROFILE && Storage::disk('public')->exists($user->FOTO_PROFILE)) {
                Storage::disk('public')->delete($user->FOTO_PROFILE);
            }

            $path = $request->file('foto_profile')->store('foto_profile/pembeli', 'public');
            $user->FOTO_PROFILE = $path;
        }

        $user->save(); 

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $user
        ]);
    }

    public function orderHistory(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PEMBELI;
        }
        
        $transaksi = Transaksi::with(['detailTransaksi.barang', 'pengiriman', 'pengambilan'])
            ->where('ID_PEMBELI', $id)
            ->orderBy('WAKTU_PESAN', 'desc')
            ->get();
            
        return response()->json(['data' => $transaksi]);
    }

    public function changePassword(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PEMBELI;
        }
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6|different:current_password',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pembeli = Pembeli::findOrFail($id);
        
        if (!Hash::check($request->current_password, $pembeli->PASSWORD)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $pembeli->PASSWORD = Hash::make($request->new_password);
        $pembeli->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|confirmed|min:6',
        ]);

        $status = Password::broker('pembelis')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->PASSWORD = Hash::make($password);
                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successful.'])
            : response()->json(['message' => 'Password reset failed.'], 500);
    }

    public function showProfile()
    {
        $pembeli = auth('pembeli')->user();

        if (!$pembeli) {
            return redirect('/login')->withErrors('Tidak terautentikasi sebagai pembeli');
        }

        $transaksi = Transaksi::with('detailTransaksi.barang')
            ->where('ID_PEMBELI', $pembeli->ID_PEMBELI)
            ->orderBy('WAKTU_PESAN', 'desc')
            ->get();

        return view('pages.profile', compact('pembeli', 'transaksi'));
    }

    public function submitRating(Request $request)
        {
            $validator = Validator::make($request->all(), [
                'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
                'rating' => 'required|integer|min:1|max:5',
                'id_penitip' => 'required|exists:penitip,ID_PENITIP'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $pembeli = $request->user('pembeli');
            $transaksi = Transaksi::where('ID_PEMBELI', $pembeli->ID_PEMBELI)
                                ->where('ID_TRANSAKSI', $request->id_transaksi)
                                ->first();

            if (!$transaksi) {
                return response()->json(['message' => 'Transaksi tidak ditemukan atau bukan milik Anda'], 404);
            }

            if ($transaksi->STATUS_TRANSAKSI !== 'Selesai') {
                return response()->json(['message' => 'Hanya transaksi dengan status Selesai yang dapat diberi rating'], 422);
            }

            // Simpan rating ke transaksi
            $transaksi->RATING = $request->rating;
            $transaksi->save();

            // Update rating rata-rata penitip
            $penitip = Penitip::find($request->id_penitip);
            if ($penitip) {
                // Cari semua transaksi yang berkaitan dengan barang-barang penitip ini
                $avgRating = Transaksi::join('detail_transaksi', 'transaksi.ID_TRANSAKSI', '=', 'detail_transaksi.ID_TRANSAKSI')
                    ->join('barang', 'detail_transaksi.ID_BARANG', '=', 'barang.ID_BARANG')
                    ->where('barang.ID_PENITIP', $penitip->ID_PENITIP)
                    ->whereNotNull('transaksi.RATING')
                    ->avg('transaksi.RATING');

                $penitip->RATING = $avgRating ?: 0; // Jika null, defaultnya 0
                $penitip->save();
            }

            return response()->json([
                'message' => 'Rating berhasil dikirim',
                'data' => $transaksi
            ]);
        }

        public function updateFcmToken(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'fcm_token' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $pembeli = $request->user('pembeli');
            
            if (!$pembeli) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $pembeli->fcm_token = $request->fcm_token;
            $pembeli->fcm_token_updated_at = now();
            $pembeli->save();

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating FCM token: ' . $e->getMessage()
            ], 500);
        }
    }

}