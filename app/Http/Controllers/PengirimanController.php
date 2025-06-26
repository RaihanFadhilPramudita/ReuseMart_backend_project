<?php

namespace App\Http\Controllers;

use App\Models\Pengiriman;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ReuseMartNotificationService; // ✅ ADD THIS

class PengirimanController extends Controller
{
    protected $notificationService; // ✅ ADD THIS

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        $this->notificationService = $notificationService; // ✅ ADD THIS
    }

    public function index(Request $request)
    {
        $pengiriman = Pengiriman::query();
        
        // Filter by status
        if ($request->has('status')) {
            $pengiriman->where('STATUS_PENGIRIMAN', $request->status);
        }
        

        if ($request->has('date')) {
            $pengiriman->whereDate('TANGGAL_KIRIM', $request->date);
        }
        
        if ($request->has('pegawai_id')) {
            $pengiriman->where('ID_PEGAWAI', $request->pegawai_id);
        }
        
        $result = $pengiriman->with(['pegawai', 'transaksi.pembeli', 'transaksi.detailTransaksi.barang'])
            ->orderBy('TANGGAL_KIRIM', 'desc')
            ->paginate(10);
            
        return response()->json(['data' => $result]);
    }

    public function show($id)
    {
        $pengiriman = Pengiriman::with(['pegawai', 'transaksi.pembeli', 'transaksi.detailTransaksi.barang'])
            ->findOrFail($id);
            
        return response()->json(['data' => $pengiriman]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'required|exists:pegawai,ID_PEGAWAI',
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
            'biaya_pengiriman' => 'required|numeric|min:0',
            'tanggal_kirim' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaksi = Transaksi::findOrFail($request->id_transaksi);
        
        if ($transaksi->JENIS_DELIVERY !== 'Antar') {
            return response()->json([
                'message' => 'This transaction is set for pickup, not delivery'
            ], 422);
        }
        
        if ($transaksi->STATUS_TRANSAKSI !== 'Diproses') {
            return response()->json([
                'message' => 'Transaction must be in "Diproses" status for shipping'
            ], 422);
        }
        
        $existingShipping = Pengiriman::where('ID_TRANSAKSI', $request->id_transaksi)->first();
        if ($existingShipping) {
            return response()->json([
                'message' => 'Shipping already exists for this transaction',
                'data' => $existingShipping
            ], 422);
        }
        
        $pengiriman = new Pengiriman();
        $pengiriman->ID_PEGAWAI = $request->id_pegawai;
        $pengiriman->ID_TRANSAKSI = $request->id_transaksi;
        $pengiriman->BIAYA_PENGIRIMAN = $request->biaya_pengiriman;
        $pengiriman->STATUS_PENGIRIMAN = 'Sedang Disiapkan';
        $pengiriman->TANGGAL_KIRIM = $request->tanggal_kirim ?? null;
        $pengiriman->save();
        
        $transaksi->STATUS_TRANSAKSI = 'Sedang dikirim';
        $transaksi->save();

        if ($request->tanggal_kirim) {
            try {
                $jadwalFormatted = Carbon::parse($request->tanggal_kirim)->format('d M Y');
                $this->notificationService->sendJadwalNotification(
                    $request->id_transaksi, 
                    'Antar', 
                    $jadwalFormatted
                );
                \Log::info("✅ Jadwal pengiriman notification sent for transaksi: {$request->id_transaksi}");
            } catch (\Exception $e) {
                \Log::error("❌ Failed to send jadwal pengiriman notification: " . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Shipping created successfully',
            'data' => $pengiriman->load(['pegawai', 'transaksi'])
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Dijadwalkan,Sedang Disiapkan,Dalam Perjalanan,Selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pengiriman = Pengiriman::findOrFail($id);
        
        // ✅ SIMPLE: Gak usah validasi ribet, langsung update aja
        $pengiriman->STATUS_PENGIRIMAN = $request->status;
        
        if ($request->status === 'Selesai') {
            $pengiriman->TANGGAL_DITERIMA = Carbon::now();
        }
        
        $pengiriman->save();

        // ✅ SIMPLE: Update status transaksi = status pengiriman (SAMA PERSIS)
        $transaksi = Transaksi::find($pengiriman->ID_TRANSAKSI);
        if ($transaksi) {
            $transaksi->STATUS_TRANSAKSI = $request->status; // SAMA PERSIS!
            $transaksi->save();
        }

        

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $pengiriman
        ]);
    }

    public function cetakNotaKurir($id)
    {
        if (!$id || $id === 'null') {
            return response()->json(['message' => 'ID tidak valid'], 400);
        }

        $transaksi = Transaksi::with([
            'pembeli',
            'alamat',
            'detailTransaksi.barang',
            'pegawai'
        ])->findOrFail($id);

        $total = $transaksi->TOTAL;
        $potongan = $transaksi->POTONGAN_POIN ?? 0;
        $totalAkhir = $total - $potongan;
        $poinDasar = floor($totalAkhir / 10000);
        $bonus = $total > 500000 ? floor($poinDasar * 0.2) : 0;
        $poinTransaksi = $poinDasar + $bonus;

        $pdf = Pdf::loadView('pdf.nota-penjualan-kurir', [
            'transaksi' => $transaksi,
            'poin' => $poinTransaksi,
            'totalAkhir' => $totalAkhir,
        ]);

        return $pdf->download("nota_kurir_{$transaksi->ID_TRANSAKSI}.pdf");
    }


    public function cetakNotaPembeli($id)
    {
        if (!$id || $id === 'null') {
            return response()->json(['message' => 'ID tidak valid'], 400);
        }

        $transaksi = Transaksi::with([
            'pembeli',
            'alamat',
            'detailTransaksi.barang',
            'pegawai'
        ])->findOrFail($id);

        $total = $transaksi->TOTAL_HARGA ?? 0;
        $potongan = $transaksi->POTONGAN_POIN ?? 0;
        $totalAkhir = $transaksi->TOTAL_AKHIR ?? ($total - $potongan);
        $poinDasar = floor($totalAkhir / 10000);
        $bonus = $total > 500000 ? floor($poinDasar * 0.2) : 0;
        $poinTransaksi = $poinDasar + $bonus;

        $pdf = Pdf::loadView('pdf.nota-penjualan-pembeli', [
            'transaksi' => $transaksi,
            'poin' => $poinTransaksi,
            'totalAkhir' => $totalAkhir,
        ]);

        return $pdf->download("nota_pembeli_{$transaksi->ID_TRANSAKSI}.pdf");
    }
}