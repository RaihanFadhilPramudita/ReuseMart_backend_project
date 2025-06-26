<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Jabatan;
use App\Models\Komisi;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\Pengiriman;
use App\Models\Pengambilan;

class PegawaiController extends Controller
{
    
   public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Pegawai::where('username', $request->username)->first();
        
        if (!$user || !Hash::check($request->password, $user->PASSWORD)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('PegawaiToken')->plainTextToken;

        $user->load(['jabatan']);

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $pegawai = Auth::guard('pegawai')->user();
        
        if (!$pegawai) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->user('pegawai')->currentAccessToken()->delete();
        return response()->json(['message' => 'Pegawai logged out successfully']);
    }


    public function resetPasswordByBirthdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:pegawai,EMAIL',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pegawai = Pegawai::where('EMAIL', $request->email)->first();

        if (!$pegawai || !$pegawai->TANGGAL_LAHIR) {
            return response()->json(['message' => 'Employee or birthdate not found'], 404);
        }

        $newPasswordPlain = Carbon::parse($pegawai->TANGGAL_LAHIR)->format('Ymd');
        $pegawai->PASSWORD = Hash::make($newPasswordPlain);
        $pegawai->save();

        return response()->json([
            'message' => 'Password has been reset using birthdate.',
            'new_password' => $newPasswordPlain  
        ]);
    }

    public function listHunter()
    {
        $hunters = Pegawai::whereHas('jabatan', function ($query) {
            $query->where('NAMA_JABATAN', 'Hunter');
        })->select('ID_PEGAWAI', 'NAMA_PEGAWAI')->get();

        return response()->json([
            'data' => $hunters
        ]);
    }

 
    
    public function index()
    {
        $pegawai = Pegawai::with(['jabatan'])->get();
        return response()->json(['data' => $pegawai]);
    }
    
    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()->load(['jabatan'])]);
    }
    
    public function show($id)
    {
        $pegawai = Pegawai::with(['jabatan'])->findOrFail($id);
        return response()->json(['data' => $pegawai]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_jabatan' => 'required|exists:jabatan,ID_JABATAN',
            'email' => 'required|email|unique:pegawai,EMAIL',
            'password' => 'required|string|min:6',
            'nama_pegawai' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'tanggal_lahir' => 'required|date',
            'username' => 'required|string|max:255|unique:pegawai,USERNAME',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $pegawai = new Pegawai();
        $pegawai->ID_JABATAN = $request->id_jabatan;
        $pegawai->EMAIL = $request->email;
        $pegawai->PASSWORD = Hash::make($request->password); 
        $pegawai->NAMA_PEGAWAI = $request->nama_pegawai;
        $pegawai->NO_TELEPON = $request->no_telepon;
        $pegawai->TANGGAL_LAHIR = Carbon::parse($request->TANGGAL_LAHIR);
        $pegawai->TANGGAL_BERGABUNG = Carbon::now();
        $pegawai->USERNAME = $request->username;
        $pegawai->save();
        
        return response()->json([
            'message' => 'Employee created successfully',
            'data' => $pegawai->load(['jabatan'])
        ], 201);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter q diperlukan.'], 422);
        }

        $pegawai = Pegawai::with('jabatan')
            ->where('NAMA_PEGAWAI', 'like', "%{$keyword}%")
            ->orWhere('EMAIL', 'like', "%{$keyword}%")
            ->orWhere('NO_TELEPON', 'like', "%{$keyword}%")
            ->orWhere('USERNAME', 'like', "%{$keyword}%")
            ->orWhereHas('jabatan', function ($q) use ($keyword) {
                $q->where('NAMA_JABATAN', 'like', "%{$keyword}%");
            })
            ->paginate(10);

        return response()->json(['data' => $pegawai]);
    }

    
    public function update(Request $request, $id)
    {
        $pegawai = Pegawai::findOrFail($id);
        
        if($request->has('id_jabatan')) $pegawai->ID_JABATAN = $request->id_jabatan;
        if($request->has('email')) $pegawai->EMAIL = $request->email;
        if($request->has('nama_pegawai')) $pegawai->NAMA_PEGAWAI = $request->nama_pegawai;
        if($request->has('no_telepon')) $pegawai->NO_TELEPON = $request->no_telepon;
        if($request->has('tanggal_lahir')) $pegawai->TANGGAL_LAHIR = $request->tanggal_lahir;
        if($request->has('username')) $pegawai->USERNAME = $request->username;
        
        $pegawai->save();
        
        return response()->json([
            'message' => 'Employee updated successfully',
            'data' => $pegawai->load(['jabatan'])
        ]);
    }
    
    public function destroy($id)
    {
        $pegawai = Pegawai::findOrFail($id);
        
        $pegawai->delete();
        
        return response()->json(['message' => 'Employee deleted successfully']);
    }
    
    public function subordinates($id)
    {
        $subordinates = Pegawai::with(['jabatan'])
            ->where('PEG_ID_PEGAWAI', $id)
            ->get();
            
        return response()->json(['data' => $subordinates]);
    }
    
    public function commissions($id)
    {
        $komisi = Komisi::with(['barang'])
            ->where('ID_PEGAWAI', $id)
            ->orderBy('TANGGAL_KOMISI', 'desc')
            ->get();
            
        return response()->json(['data' => $komisi]);
    }
    
    public function positionStaff($jabatanId)
    {
        $staff = Pegawai::where('ID_JABATAN', $jabatanId)->get();
        return response()->json(['data' => $staff]);
    }
    
    public function changePassword(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PEGAWAI;
        }
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|different:current_password',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $pegawai = Pegawai::findOrFail($id);
        
        if (!Hash::check($request->current_password, $pegawai->PASWORD)) { // Note: DB field has typo "PASWORD"
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }
        
        $pegawai->PASWORD = Hash::make($request->new_password); // Note: DB field has typo "PASWORD"
        $pegawai->save();
        
        return response()->json(['message' => 'Password changed successfully']);
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

            $pegawai = $request->user('pegawai');
            
            if (!$pegawai) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $pegawai->fcm_token = $request->fcm_token;
            $pegawai->fcm_token_updated_at = now();
            $pegawai->save();

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

    public function transaksiAktif()
    {
        $pegawai = auth('pegawai')->user();
        if (!$pegawai || $pegawai->jabatan->NAMA_JABATAN !== 'Gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['detailTransaksi.barang', 'pembeli'])
            ->whereIn('STATUS_TRANSAKSI', ['Belum Dikirim', 'Siap Diambil', 'Dijadwalkan Kirim', 'Dijadwalkan Ambil'])
            ->get();

        return response()->json(['data' => $transaksi]);
    }

    public function jadwalPengiriman(Request $request)
    {
        $pegawai = auth('pegawai')->user();
        if (!$pegawai || $pegawai->jabatan->NAMA_JABATAN !== 'Gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
            'waktu_pengiriman' => 'required|date_format:Y-m-d H:i'
        ]);

        $transaksi = Transaksi::findOrFail($request->id_transaksi);
        $waktu = Carbon::parse($request->waktu_pengiriman);
        $batas = Carbon::today()->setTime(16, 0);

        // Cegah penjadwalan setelah pukul 16:00 pada hari yang sama
        if ($waktu->isToday() && $waktu->gt($batas)) {
            return response()->json([
                'message' => 'Tidak bisa dijadwalkan di hari yang sama jika lebih dari jam 16:00.'
            ], 422);
        }

        $transaksi->WAKTU_KIRIM = $waktu;
        $transaksi->STATUS_TRANSAKSI = 'Dijadwalkan Kirim';
        $transaksi->save();

        $cekPengiriman = Pengiriman::where('ID_TRANSAKSI', $transaksi->ID_TRANSAKSI)->first();
        if (!$cekPengiriman) {
            Pengiriman::create([
                'ID_PEGAWAI' => $pegawai->ID_PEGAWAI,
                'ID_TRANSAKSI' => $transaksi->ID_TRANSAKSI,
                'TANGGAL_KIRIM' => $waktu,
                'STATUS_PENGIRIMAN' => 'Dijadwalkan',
                'BIAYA_PENGIRIMAN' => 0,
            ]);
        }

        return response()->json([
            'message' => 'Pengiriman dijadwalkan.',
            'jadwal' => $waktu->format('Y-m-d H:i:s'),
            'id_transaksi' => $transaksi->ID_TRANSAKSI,
            'status' => $transaksi->STATUS_TRANSAKSI
        ]);
    }

    public function jadwalAmbil(Request $request)
    {
        $pegawai = auth('pegawai')->user();
        if (!$pegawai || $pegawai->jabatan->NAMA_JABATAN !== 'Gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
            'waktu_ambil' => 'required|date_format:Y-m-d H:i'
        ]);

        $transaksi = Transaksi::findOrFail($request->id_transaksi);
        $waktu = Carbon::parse($request->waktu_ambil);

        $transaksi->WAKTU_AMBIL = $waktu;
        $transaksi->STATUS_TRANSAKSI = 'Dijadwalkan Ambil';
        $transaksi->save();

        $cekPengambilan = Pengambilan::where('ID_TRANSAKSI', $transaksi->ID_TRANSAKSI)->first();
        if (!$cekPengambilan) {
            Pengambilan::create([
                'ID_PEGAWAI' => $pegawai->ID_PEGAWAI,
                'ID_TRANSAKSI' => $transaksi->ID_TRANSAKSI,
                'JADWAL_PENGAMBILAN' => $waktu,
                'STATUS_PENGEMBALIAN' => 'Dijadwalkan',
            ]);
        }


        return response()->json([
            'message' => 'Pengambilan dijadwalkan.',
            'jadwal' => $waktu->format('Y-m-d H:i:s'),
            'id_transaksi' => $transaksi->ID_TRANSAKSI,
            'status' => $transaksi->STATUS_TRANSAKSI
        ]);
    }

    public function konfirmasiTerima(Request $request)
    {
        $pegawai = auth('pegawai')->user();
        if (!$pegawai || $pegawai->jabatan->NAMA_JABATAN !== 'Gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI'
        ]);

        $transaksi = Transaksi::with('barang')->findOrFail($request->id_transaksi);
        $transaksi->STATUS_TRANSAKSI = 'Transaksi Selesai';
        $transaksi->TANGGAL_VERIFIKASI = now();
        $transaksi->save();

        if ($transaksi->barang) {
            $transaksi->barang->STATUS_BARANG = 'Sudah Diambil';
            $transaksi->barang->save();
        }

        return response()->json(['message' => 'Transaksi dikonfirmasi selesai']);
    }

    public function pesananDiproses()
    {
        $pegawai = auth('pegawai')->user();
        if (!$pegawai || $pegawai->jabatan->NAMA_JABATAN !== 'Gudang') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaksi = Transaksi::with(['detailTransaksi.barang', 'pembeli.alamat'])
            ->whereIn('STATUS_TRANSAKSI', ['diproses', 'Dijadwalkan Kirim', 'Dijadwalkan Ambil'])
            ->whereIn('JENIS_DELIVERY', ['Antar', 'Ambil'])
            ->get();

        return response()->json(['data' => $transaksi]);
    }

}