<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Komisi;
use App\Models\Penitip;
use App\Models\Pegawai;
use App\Models\Barang;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Exception;

class KomisiController extends Controller
{


  public function index(Request $request)
    {
        $komisi = Komisi::query();

        if ($request->filled('penitip_id')) {
            $komisi->where('ID_PENITIP', $request->penitip_id);
        }

        if ($request->filled('pegawai_id')) {
            $komisi->where('ID_PEGAWAI', $request->pegawai_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $komisi->whereBetween('TANGGAL_KOMISI', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        // ✅ Tambahan Filter Bulan dan Tahun
        if ($request->filled('bulan')) {
            $komisi->whereMonth('TANGGAL_KOMISI', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $komisi->whereYear('TANGGAL_KOMISI', $request->tahun);
        }

        $result = $komisi->with([
            'penitip',
            'pegawai',
            'barang'
        ])
        ->orderByDesc('TANGGAL_KOMISI')
        ->get();

        return response()->json(['data' => $result]);
    }


    public function show($id)
    {
        $komisi = Komisi::with(['penitip', 'pegawai', 'barang'])->findOrFail($id);
        return response()->json(['data' => $komisi]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required|exists:barang,ID_BARANG',
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $existing = Komisi::where('ID_BARANG', $request->id_barang)->first();
        if ($existing) {
            return response()->json(['message' => 'Komisi sudah tercatat untuk barang ini.'], 422);
        }

        $calc = $this->calculateCommission($request)->getData();

        $barang = Barang::with('penitip')->findOrFail($request->id_barang);

        $komisi = new Komisi();
        $komisi->ID_PENITIP = $barang->ID_PENITIP;
        $komisi->ID_BARANG = $barang->ID_BARANG;
        $komisi->ID_PEGAWAI = $calc->is_hunted ? $barang->ID_PEGAWAI : null;
        $komisi->JUMLAH_KOMISI_REUSE_MART = $calc->reuse_mart_commission;
        $komisi->JUMLAH_KOMISI_HUNTER = $calc->hunter_commission ?? 0;
        $komisi->BONUS_PENITIP = $calc->bonus ?? 0;
        $komisi->TANGGAL_KOMISI = now();
        $komisi->save();

        $totalKomisi = $calc->total_commission;
        $penerimaan = $calc->price - $totalKomisi + $calc->bonus;

        $penitip = $barang->penitip;
        if ($penitip) {
            $penitip->SALDO += $penerimaan;
            $penitip->save();
        }

        return response()->json([
            'message' => 'Komisi otomatis tercatat',
            'data' => $komisi->load(['penitip', 'pegawai', 'barang'])
        ]);
    }


    public function calculateCommission(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required|exists:barang,ID_BARANG',
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $barang = Barang::with('penitip')->findOrFail($request->id_barang);
        $transaksi = Transaksi::findOrFail($request->id_transaksi);
        
        $isSold = false;
        foreach ($transaksi->detailTransaksi as $detail) {
            if ($detail->ID_BARANG == $barang->ID_BARANG) {
                $isSold = true;
                break;
            }
        }
        
        if (!$isSold) {
            return response()->json([
                'message' => 'Item is not included in this transaction'
            ], 422);
        }
        
        $salesDate = Carbon::parse($transaksi->WAKTU_PESAN);
        $entryDate = Carbon::parse($barang->TANGGAL_MASUK);
        $soldQuickly = $entryDate->diffInDays($salesDate) < 7;
        
        $isHunted = $barang->pegawai && $barang->pegawai->ID_JABATAN == 6; 
        
        $price = $barang->HARGA;
        $commissionRate = 0.20; 
        
        $penitipan = $barang->detailPenitipan()->first();
        if ($penitipan && $penitipan->penitipan) {
            $expirationDate = Carbon::parse($penitipan->penitipan->TANGGAL_KADALUARSA);
            $creationDate = Carbon::parse($penitipan->penitipan->TANGGAL_MASUK);
            
            if ($expirationDate->diffInDays($creationDate) > 30) {
                $commissionRate = 0.30; 
            }
        }
        
        $totalCommission = $price * $commissionRate;
        $hunterCommission = $isHunted ? $totalCommission * 0.25 : 0; 
        $reuseMartCommission = $totalCommission - $hunterCommission;
        
        $bonus = $soldQuickly ? $totalCommission * 0.10 : 0; 
        
        $isTopSeller = $barang->penitip && $barang->penitip->BADGE === 'Top Seller';
        if ($isTopSeller) {
            $bonus += $totalCommission * 0.01; 
        }
        
        return response()->json([
            'price' => $price,
            'commission_rate' => $commissionRate,
            'total_commission' => $totalCommission,
            'reuse_mart_commission' => $reuseMartCommission,
            'hunter_commission' => $hunterCommission,
            'bonus' => $bonus,
            'is_hunted' => $isHunted,
            'sold_quickly' => $soldQuickly,
            'is_top_seller' => $isTopSeller
        ]);
    }

    public function staffCommissionHistory($staffId)
    {
        $komisi = Komisi::with(['barang'])
            ->where('ID_PEGAWAI', $staffId)
            ->orderBy('TANGGAL_KOMISI', 'desc')
            ->get();
            
        return response()->json(['data' => $komisi]);
    }

    public function depositorCommissionHistory(Request $request)
    {
        $penitip = $request->user();

        if (!$penitip || !$penitip->ID_PENITIP) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $komisi = Komisi::with(['barang'])
            ->where('ID_PENITIP', $penitip->ID_PENITIP)
            ->orderBy('TANGGAL_KOMISI', 'desc')
            ->get();

        return response()->json(['data' => $komisi]);
    }

    public function exportLaporanKomisiBulanan(Request $request)
    {
        $query = Komisi::with(['barang.detailPenitipan.penitipan', 'barang', 'penitip', 'pegawai']);

        if ($request->filled('bulan')) {
            $query->whereMonth('TANGGAL_KOMISI', $request->bulan);
        }

        if ($request->filled('tahun')) {
            $query->whereYear('TANGGAL_KOMISI', $request->tahun);
        }

        $komisi = $query->get();

        $tanggalCetak = now();
        $bulan = $request->bulan;
        $tahun = $request->tahun;

        return Pdf::loadView('laporan.komisi_bulanan', compact('komisi', 'tanggalCetak', 'bulan', 'tahun'))
            ->download('laporan_komisi_bulanan.pdf');
    }

   public function hunterCommissionHistory(Request $request)
    {
        $hunter = auth('pegawai')->user(); // login via guard 'pegawai'
        
        $komisi = Komisi::with(['barang', 'penitip'])
            ->where('ID_PEGAWAI', $hunter->ID_PEGAWAI)
            ->orderByDesc('TANGGAL_KOMISI')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $komisi,
        ]);
    }

    /**
     * Get commission detail for hunter
     */
    public function hunterCommissionDetail(Request $request, $id)
    {
        $hunter = $request->user();
        
        if (!$hunter || !$hunter->ID_PEGAWAI) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Verify that the user is indeed a hunter
        if (!$hunter->jabatan || strtolower($hunter->jabatan->NAMA_JABATAN) !== 'hunter') {
            return response()->json(['message' => 'Access denied. Hunter role required.'], 403);
        }

        $komisi = Komisi::with(['barang.penitip', 'barang.kategori', 'penitip'])
            ->where('ID_PEGAWAI', $hunter->ID_PEGAWAI) // Only hunter's commissions
            ->where('ID_KOMISI', $id)
            ->first();

        if (!$komisi) {
            return response()->json(['message' => 'Commission not found'], 404);
        }

        return response()->json(['data' => $komisi]);
    }

    /**
     * Get commission summary for hunter
     */
    public function hunterCommissionSummary(Request $request)
    {
        $hunter = $request->user();
        
        if (!$hunter || !$hunter->ID_PEGAWAI) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Verify that the user is indeed a hunter
        if (!$hunter->jabatan || strtolower($hunter->jabatan->NAMA_JABATAN) !== 'hunter') {
            return response()->json(['message' => 'Access denied. Hunter role required.'], 403);
        }

        $query = Komisi::where('ID_PEGAWAI', $hunter->ID_PEGAWAI);

        // Apply date filters if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('TANGGAL_KOMISI', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        $commissions = $query->get();

        $summary = [
            'total_komisi_hunter' => $commissions->sum('JUMLAH_KOMISI_HUNTER'),
            'total_bonus_penitip' => $commissions->sum('BONUS_PENITIP'),
            'total_komisi_reuse_mart' => $commissions->sum('JUMLAH_KOMISI_REUSE_MART'),
            'total_transaksi' => $commissions->count(),
            'total_keseluruhan' => $commissions->sum('JUMLAH_KOMISI_HUNTER') + $commissions->sum('BONUS_PENITIP'),
        ];

        return response()->json(['data' => $summary]);
    }

    /**
     * Get monthly commission data for hunter
     */
    public function hunterMonthlyCommission(Request $request)
    {
        $hunter = $request->user();
        
        if (!$hunter || !$hunter->ID_PEGAWAI) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Verify that the user is indeed a hunter
        if (!$hunter->jabatan || strtolower($hunter->jabatan->NAMA_JABATAN) !== 'hunter') {
            return response()->json(['message' => 'Access denied. Hunter role required.'], 403);
        }

        $year = $request->get('year', now()->year);
        $month = $request->get('month', now()->month);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $commissions = Komisi::with(['barang'])
            ->where('ID_PEGAWAI', $hunter->ID_PEGAWAI)
            ->whereBetween('TANGGAL_KOMISI', [$startDate, $endDate])
            ->get();

        $totalEarnings = $commissions->sum(function($komisi) {
            return $komisi->JUMLAH_KOMISI_HUNTER + $komisi->BONUS_PENITIP;
        });

        $totalItems = $commissions->count();
        
        // Group by day for daily count
        $dailyCount = $commissions->groupBy(function($komisi) {
            return Carbon::parse($komisi->TANGGAL_KOMISI)->day;
        })->map(function($group) {
            return $group->count();
        });

        return response()->json([
            'data' => [
                'total_earnings' => $totalEarnings,
                'total_items' => $totalItems,
                'daily_count' => $dailyCount,
                'commissions' => $commissions,
                'month' => $month,
                'year' => $year,
            ]
        ]);
    }

    
}