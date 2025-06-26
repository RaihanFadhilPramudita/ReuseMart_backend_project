<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Password;
use App\Models\Penitip;
use App\Models\Barang;
use App\Models\Penitipan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Notifications\PembeliResetPasswordNotification;
use App\Models\Transaksi;
use App\Models\Komisi;
use Barryvdh\DomPDF\Facade\Pdf;

class PenitipController extends Controller
{
    
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = Penitip::where('EMAIL', $request->email)->first();
        
        if (!$user || !Hash::check($request->password, $user->PASSWORD)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $token = $user->createToken('PenitipToken')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        
        $penitip = Auth::guard('penitip')->user();
        if (!$penitip) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $request->user('penitip')->currentAccessToken()->delete();
        return response()->json(['message' => 'Penitip logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:penitip,EMAIL',
        ]);

        $user = Penitip::where('EMAIL', $request->email)->first();

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
        $user->notify(new PembeliResetPasswordNotification($token, 'penitip'));

        return response()->json(['message' => 'Reset link sent to your email.']);
    }
    
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        $status = Password::broker('penitip')->reset(
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

    public function search(Request $request)
    {
        $keyword = $request->input('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter q diperlukan.'], 422);
        }

        $penitip = Penitip::where(function ($query) use ($keyword) {
            $query->where('NAMA_PENITIP', 'like', "%{$keyword}%")
                ->orWhere('EMAIL', 'like', "%{$keyword}%")
                ->orWhere('NO_TELEPON', 'like', "%{$keyword}%")
                ->orWhere('NO_KTP', 'like', "%{$keyword}%");
        })->paginate(10);

        return response()->json(['data' => $penitip]);
    }

    public function index()
    {
        $penitips = Penitip::all()->map(function ($penitip) {
            $penitip->foto_ktp_url = $penitip->foto_ktp
                ? asset('storage/' . $penitip->foto_ktp)
                : null;
            return $penitip;
        });

        return response()->json(['data' => $penitips]);
    }

    public function profile(Request $request)
    {
        return response()->json(['data' => $request->user()]);
    }

    public function show($id)
    {
        $penitip = Penitip::findOrFail($id);
        return response()->json(['data' => $penitip]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:penitip,EMAIL',
            'password' => 'required|min:6',
            'nama_penitip' => 'required|string|max:255',
            'no_telepon' => 'required|string|max:20',
            'no_ktp' => 'required|string|max:20|unique:penitip,NO_KTP',
            'tanggal_lahir' => 'required|date',
            'foto_ktp' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $path = $request->file('foto_ktp')->store('foto_ktp', 'public');

        $penitip = new Penitip();
        $penitip->EMAIL = $request->email;
        $penitip->PASSWORD = Hash::make($request->password);
        $penitip->NAMA_PENITIP = $request->nama_penitip;
        $penitip->NO_TELEPON = $request->no_telepon;
        $penitip->NO_KTP = $request->no_ktp;
        $penitip->foto_ktp = $path;
        $penitip->TANGGAL_LAHIR = $request->tanggal_lahir;
        $penitip->TANGGAL_REGISTRASI = now();
        $penitip->SALDO = 0;
        $penitip->BADGE = 'Seller';
        $penitip->RATING = 0;
        $penitip->POIN_SOSIAL = 0;
        $penitip->save();

        return response()->json([
            'message' => 'Depositor registered successfully',
            'data' => $penitip
        ], 201);
    }

    public function update(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PENITIP;
        }

        if (!$id) {
            return response()->json(['message' => 'ID penitip tidak ditemukan.'], 400);
        }

        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:penitip,EMAIL,' . $id . ',ID_PENITIP',
            'nama_penitip' => 'string|max:255',
            'no_telepon' => 'string|max:20',
            'no_ktp' => 'string|max:20|unique:penitip,NO_KTP,' . $id . ',ID_PENITIP',
            'tanggal_lahir' => 'date',
            'badge' => 'nullable|string',
            'foto_profile' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $penitip = Penitip::findOrFail($id);

        if ($request->hasFile('foto_ktp')) {
            if ($penitip->FOTO_KTP && Storage::disk('public')->exists($penitip->FOTO_KTP)) {
                Storage::disk('public')->delete($penitip->FOTO_KTP);
            }
            $penitip->FOTO_KTP = $request->file('foto_ktp')->store('ktp', 'public');
        }

        if ($request->hasFile('foto_profile')) {
            if ($penitip->FOTO_PROFILE && Storage::disk('public')->exists($penitip->FOTO_PROFILE)) {
                Storage::disk('public')->delete($penitip->FOTO_PROFILE);
            }
            $pathProfile = $request->file('foto_profile')->store('foto_profile/penitip', 'public');

            $penitip->FOTO_PROFILE = $pathProfile;
        }

        if ($request->has('email')) $penitip->EMAIL = $request->email;
        if ($request->has('nama_penitip')) $penitip->NAMA_PENITIP = $request->nama_penitip;
        if ($request->has('no_telepon')) $penitip->NO_TELEPON = $request->no_telepon;
        if ($request->has('no_ktp')) $penitip->NO_KTP = $request->no_ktp;
        if ($request->has('tanggal_lahir')) $penitip->TANGGAL_LAHIR = $request->tanggal_lahir;
        if ($request->has('badge')) $penitip->BADGE = $request->badge;

        $penitip->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => $penitip
        ]);
    }

    public function destroy($id)
    {
        $penitip = Penitip::find($id);

        if (!$penitip) {
            return response()->json(['message' => 'Penitip not found'], 404);
        }

        if ($penitip->FOTO_KTP && Storage::disk('public')->exists($penitip->FOTO_KTP)) {
            Storage::disk('public')->delete($penitip->FOTO_KTP);
        }

        $penitip->delete();

        return response()->json(['message' => 'Penitip deleted successfully'], 200);
    }

    public function itemHistory(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PENITIP;
        }
        
        $barangs = Barang::where('ID_PENITIP', $id)->get();
        return response()->json(['data' => $barangs]);
    }

    public function depositHistory(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PENITIP;
        }
        
        $penitipan = Penitipan::with('detailPenitipan.barang')
            ->where('ID_PENITIP', $id)
            ->orderBy('TANGGAL_MASUK', 'desc')
            ->get();
            
        return response()->json(['data' => $penitipan]);
    }

    public function showBalance(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PENITIP;
        }
        
        $penitip = Penitip::findOrFail($id);
        return response()->json(['saldo' => $penitip->SALDO]);
    }

    public function changePassword(Request $request, $id = null)
    {
        if (!$id && $request->user()) {
            $id = $request->user()->ID_PENITIP;
        }
        
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => 'required|min:6|different:current_password',
            'confirm_password' => 'required|same:new_password',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $penitip = Penitip::findOrFail($id);
        
        if (!Hash::check($request->current_password, $penitip->PASSWORD)) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $penitip->PASSWORD = Hash::make($request->new_password);
        $penitip->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function transaksi(Request $request)
    {
        $penitip = $request->user(); // asumsi login sebagai penitip

        $transaksi = Transaksi::with(['detailTransaksi.barang', 'pembeli'])
            ->whereHas('detailTransaksi.barang', function ($query) use ($penitip) {
                $query->where('ID_PENITIP', $penitip->ID_PENITIP);
            })
            ->orderBy('WAKTU_PESAN', 'desc')
            ->get();

        return response()->json(['data' => $transaksi]);
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

            $penitip = $request->user('penitip');
            
            if (!$penitip) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $penitip->fcm_token = $request->fcm_token;
            $penitip->fcm_token_updated_at = now();
            $penitip->save();

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

    /**
     * Generate kode produk berdasarkan nama barang dan ID
     * Format: [Huruf pertama nama barang][ID_BARANG]
     */
    private function generateKodeProduk($namaBarang, $idBarang)
    {
        // Ambil huruf pertama dari nama barang (uppercase)
        $firstLetter = strtoupper(substr(trim($namaBarang), 0, 1));
        
        // Gabungkan dengan ID barang
        return $firstLetter . $idBarang;
    }

    public function getlaporanTransaksiPenitip(Request $request)
    {
        $id_penitip = $request->input('id_penitip');
        $bulan = $request->input('bulan');
        $tahun = $request->input('tahun');

        if (!$id_penitip || !$bulan || !$tahun) {
            return response()->json(['message' => 'Parameter tidak lengkap'], 400);
        }

        try {
            $komisiList = Komisi::with(['barang.detailPenitipan.penitipan'])
                ->where('ID_PENITIP', $id_penitip)
                ->whereHas('barang', function ($q) use ($bulan, $tahun) {
                    $q->whereYear('TANGGAL_JUAL', $tahun)
                        ->whereMonth('TANGGAL_JUAL', $bulan);
                })
                ->get();

            $data = [];

            foreach ($komisiList as $komisi) {
                $barang = $komisi->barang;
                $penitipan = $barang?->detailPenitipan?->penitipan;

                if (!$barang) continue;

                $hargaBarang = $barang->HARGA ?? 0;
                $totalKomisi = ($komisi->JUMLAH_KOMISI_REUSE_MART ?? 0) + ($komisi->JUMLAH_KOMISI_HUNTER ?? 0);
                $bonus = $komisi->BONUS_PENITIP ?? 0;
                $bersih = $hargaBarang - $totalKomisi;
                $pendapatan = $bersih + $bonus;

                // 🔥 FIX: Generate kode produk yang benar
                $kodeProduk = $this->generateKodeProduk($barang->NAMA_BARANG, $barang->ID_BARANG);

                $data[] = [
                    'kode_produk' => $kodeProduk, // 🔥 Menggunakan kode produk yang sudah di-generate
                    'nama_produk' => $barang->NAMA_BARANG ?? '-',
                    'tanggal_masuk' => optional($penitipan)->TANGGAL_MASUK ? Carbon::parse($penitipan->TANGGAL_MASUK)->format('d/m/Y') : '-',
                    'tanggal_laku' => $barang->TANGGAL_JUAL ? Carbon::parse($barang->TANGGAL_JUAL)->format('d/m/Y') : '-',
                    'harga_jual_bersih' => $bersih,
                    'bonus_terjual_cepat' => $bonus,
                    'pendapatan' => $pendapatan,
                ];
            }

            return response()->json(['data' => $data]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal mengambil data laporan',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function exportLaporanTransaksiPenitip(Request $request)
    {
        $id_penitip = $request->id_penitip;
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        if (!$id_penitip || !$bulan || !$tahun) {
            return response()->json(['message' => 'Parameter tidak lengkap'], 400);
        }

        try {
            $penitip = Penitip::findOrFail($id_penitip);

            $komisiList = Komisi::with(['barang.detailPenitipan.penitipan'])
                ->where('ID_PENITIP', $id_penitip)
                ->whereHas('barang', function ($q) use ($bulan, $tahun) {
                    $q->whereYear('TANGGAL_JUAL', $tahun)
                        ->whereMonth('TANGGAL_JUAL', $bulan);
                })
                ->get();

            $data = [];

            foreach ($komisiList as $komisi) {
                $barang = $komisi->barang;
                $penitipan = $barang?->detailPenitipan?->penitipan;

                if (!$barang) continue;

                $hargaBarang = $barang->HARGA ?? 0;
                $totalKomisi = ($komisi->JUMLAH_KOMISI_REUSE_MART ?? 0) + ($komisi->JUMLAH_KOMISI_HUNTER ?? 0);
                $bonus = $komisi->BONUS_PENITIP ?? 0;
                $bersih = $hargaBarang - $totalKomisi;
                $pendapatan = $bersih + $bonus;

                // 🔥 FIX: Generate kode produk yang benar untuk PDF
                $kodeProduk = $this->generateKodeProduk($barang->NAMA_BARANG, $barang->ID_BARANG);

                $data[] = [
                    'KODE_PRODUK' => $kodeProduk, // 🔥 Menggunakan kode produk yang sudah di-generate
                    'NAMA_BARANG' => $barang->NAMA_BARANG ?? '-',
                    'TANGGAL_MASUK' => optional($penitipan)->TANGGAL_MASUK,
                    'TANGGAL_LAKU' => $barang->TANGGAL_JUAL,
                    'HARGA_JUAL_BERSIH' => $bersih,
                    'BONUS_TERJUAL_CEPAT' => $bonus,
                    'PENDAPATAN' => $pendapatan,
                ];
            }

            $bulanNama = Carbon::createFromDate(null, $bulan, 1)->translatedFormat('F');

            $pdf = Pdf::loadView('laporan.transaksi_penitip', [
                'penitip' => $penitip,
                'transaksi' => $data,
                'bulan' => $bulanNama,
                'tahun' => $tahun,
                'tanggalCetak' => now()->translatedFormat('d F Y'),
            ]);

            return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "attachment; filename=laporan-penitip-{$penitip->ID_PENITIP}-{$bulan}-{$tahun}.pdf");
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal generate PDF',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function hitungTopSeller()
    {
        try {
            $bulanLalu = Carbon::now()->subMonth();
            $bulan = $bulanLalu->month;
            $tahun = $bulanLalu->year;
            $namaBulan = $bulanLalu->translatedFormat('F');

            $penjualan = DB::table('transaksi')
                ->join('detail_transaksi', 'transaksi.ID_TRANSAKSI', '=', 'detail_transaksi.ID_TRANSAKSI')
                ->join('barang', 'detail_transaksi.ID_BARANG', '=', 'barang.ID_BARANG')
                ->select('barang.ID_PENITIP', DB::raw('SUM(barang.HARGA) as total_penjualan'))
                ->where('transaksi.STATUS_TRANSAKSI', ['Selesai', 'Hangus'])
                ->whereMonth('transaksi.WAKTU_PESAN', $bulan)
                ->whereYear('transaksi.WAKTU_PESAN', $tahun)
                ->groupBy('barang.ID_PENITIP')
                ->orderByDesc('total_penjualan')
                ->get();

            if ($penjualan->isEmpty()) {
                return response()->json(['message' => 'Tidak ada transaksi selesai bulan lalu.'], 200);
            }

            $topValue = $penjualan->first()->total_penjualan;
            $topPenitip = $penjualan->where('total_penjualan', $topValue)->sortBy('ID_PENITIP')->first();

            Penitip::where('BADGE', 'like', 'Top Seller%')
                ->where('ID_PENITIP', '!=', $topPenitip->ID_PENITIP)
                ->update(['BADGE' => 'Seller']);

            $penitip = Penitip::find($topPenitip->ID_PENITIP);
            $bonus = $topValue * 0.01;
            $badge = "Top Seller {$namaBulan}";
            $penitip->BADGE = $badge;
            $penitip->SALDO += $bonus;
            $penitip->save();

            Komisi::create([
                'JUMLAH_KOMISI_REUSE_MART' => 0,
                'JUMLAH_KOMISI_HUNTER' => 0,
                'BONUS_PENITIP' => $bonus,
                'TANGGAL_KOMISI' => now()->toDateString(),
                'ID_PENITIP' => $penitip->ID_PENITIP,
                'ID_BARANG' => null,
                'ID_PEGAWAI' => null,
            ]);

            foreach ($penjualan as $row) {
                $totalKomisiPenitip = 0;

                $transaksiSelesai = DB::table('transaksi')
                    ->join('detail_transaksi', 'transaksi.ID_TRANSAKSI', '=', 'detail_transaksi.ID_TRANSAKSI')
                    ->join('barang', 'detail_transaksi.ID_BARANG', '=', 'barang.ID_BARANG')
                    ->join('detail_penitipan', 'barang.ID_BARANG', '=', 'detail_penitipan.ID_BARANG')
                    ->join('penitipan', 'detail_penitipan.ID_PENITIPAN', '=', 'penitipan.ID_PENITIPAN')
                    ->leftJoin('pegawai', 'penitipan.PEGAWAI_HUNTER', '=', 'pegawai.ID_PEGAWAI')
                    ->leftJoin('jabatan', 'pegawai.ID_JABATAN', '=', 'jabatan.ID_JABATAN')
                    ->where('transaksi.STATUS_TRANSAKSI', ['Selesai', 'Hangus'])
                    ->where('barang.ID_PENITIP', $row->ID_PENITIP)
                    ->whereMonth('transaksi.WAKTU_PESAN', $bulan)
                    ->whereYear('transaksi.WAKTU_PESAN', $tahun)
                    ->select(
                        'barang.ID_BARANG',
                        'barang.HARGA',
                        'penitipan.PERPANJANGAN',
                        'penitipan.TANGGAL_MASUK',
                        'pegawai.ID_PEGAWAI',
                        'jabatan.NAMA_JABATAN'
                    )
                    ->get();

                foreach ($transaksiSelesai as $trx) {
                    $harga = $trx->HARGA;
                    $isPerpanjangan = $trx->PERPANJANGAN == 1;
                    $isHunter = strtolower($trx->NAMA_JABATAN ?? '') === 'hunter';

                    // Hitung komisi
                    $komisiRM = $isPerpanjangan
                        ? ($isHunter ? $harga * 0.25 : $harga * 0.30)
                        : ($isHunter ? $harga * 0.15 : $harga * 0.20);
                    $komisiHunter = $isHunter ? $harga * 0.05 : 0;

                    // Bonus jika laku < 7 hari
                    $bonusPenitip = 0;
                    if (Carbon::parse($trx->TANGGAL_MASUK)->diffInDays(Carbon::now()) < 7) {
                        $bonusPenitip = $komisiRM * 0.10;
                        $komisiRM -= $bonusPenitip;
                    }

                    $totalKomisiPenitip += $harga - ($komisiRM + $komisiHunter) + $bonusPenitip;

                    // Cek apakah komisi sudah ada
                    $komisi = Komisi::where('ID_BARANG', $trx->ID_BARANG)
                        ->where('ID_PENITIP', $row->ID_PENITIP)
                        ->first();

                    if ($komisi) {
                        $komisi->BONUS_PENITIP += $bonusPenitip;
                        $komisi->save();
                    } else {
                        Komisi::create([
                            'JUMLAH_KOMISI_REUSE_MART' => $komisiRM,
                            'JUMLAH_KOMISI_HUNTER' => $komisiHunter,
                            'BONUS_PENITIP' => $bonusPenitip,
                            'TANGGAL_KOMISI' => now()->toDateString(),
                            'ID_PENITIP' => $row->ID_PENITIP,
                            'ID_BARANG' => $trx->ID_BARANG,
                            'ID_PEGAWAI' => $isHunter ? $trx->ID_PEGAWAI : null,
                        ]);
                    }
                }

                // Validasi saldo penitip
                $penitipFix = Penitip::find($row->ID_PENITIP);
                if ($penitipFix && round($penitipFix->SALDO, 2) != round($totalKomisiPenitip, 2)) {
                    $penitipFix->SALDO = $totalKomisiPenitip;
                    $penitipFix->save();
                }
            }

            return response()->json([
                'message' => 'Top Seller berhasil diperbarui dan saldo telah divalidasi',
                'top_seller' => $penitip->NAMA_PENITIP,
                'total_penjualan' => $topValue,
                'bonus' => $bonus,
                'badge' => $badge
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal hitung top seller', 'error' => $e->getMessage()], 500);
        }
    }

    public function penjualan()
    {
        $penjualan = DB::table('transaksi')
            ->join('detail_transaksi', 'transaksi.ID_TRANSAKSI', '=', 'detail_transaksi.ID_TRANSAKSI')
            ->join('barang', 'detail_transaksi.ID_BARANG', '=', 'barang.ID_BARANG')
            ->join('penitip', 'barang.ID_PENITIP', '=', 'penitip.ID_PENITIP')
            ->where('transaksi.STATUS_TRANSAKSI', 'Selesai')
            ->select(
                'barang.ID_PENITIP',
                DB::raw('SUM(barang.HARGA) as total_penjualan'),
                'penitip.NAMA_PENITIP',
                'penitip.BADGE'
            )
            ->groupBy('barang.ID_PENITIP', 'penitip.NAMA_PENITIP', 'penitip.BADGE')
            ->get();

        return response()->json(['data' => $penjualan]);
    }

    public function listPenitip()
    {
        $data = Penitip::select('ID_PENITIP', 'NAMA_PENITIP')->orderBy('NAMA_PENITIP')->get();
        return response()->json(['data' => $data]);
    }
}