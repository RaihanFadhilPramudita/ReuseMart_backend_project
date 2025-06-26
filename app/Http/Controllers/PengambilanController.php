<?php

namespace App\Http\Controllers;

use App\Models\Pengambilan;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ReuseMartNotificationService;

class PengambilanController extends Controller
{
    protected $notificationService; // ✅ ADD THIS

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        $this->notificationService = $notificationService; // ✅ ADD THIS
    }

    public function index(Request $request)
    {
        $pengambilan = Pengambilan::query();
        
        if ($request->has('status')) {
            $pengambilan->where('STATUS_PENGEMBALIAN', $request->status);
        }
        
        if ($request->has('date')) {
            $pengambilan->whereDate('JADWAL_PENGAMBILAN', $request->date);
        }
        
        if ($request->has('pegawai_id')) {
            $pengambilan->where('ID_PEGAWAI', $request->pegawai_id);
        }
        
        $result = $pengambilan->with(['pegawai', 'transaksi.pembeli', 'transaksi.detailTransaksi.barang'])
            ->orderBy('JADWAL_PENGAMBILAN', 'desc')
            ->paginate(10);
            
        return response()->json(['data' => $result]);
    }

    public function show($id)
    {
        $pengambilan = Pengambilan::with(['pegawai', 'transaksi.pembeli', 'transaksi.detailTransaksi.barang'])
            ->findOrFail($id);
            
        return response()->json(['data' => $pengambilan]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'required|exists:pegawai,ID_PEGAWAI',
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
            'jadwal_pengambilan' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaksi = Transaksi::findOrFail($request->id_transaksi);
        
        if ($transaksi->JENIS_DELIVERY !== 'Ambil') {
            return response()->json([
                'message' => 'This transaction is set for delivery, not pickup'
            ], 422);
        }
        
        if ($transaksi->STATUS_TRANSAKSI !== 'Diproses') {
            return response()->json([
                'message' => 'Transaction must be in "Diproses" status for pickup scheduling'
            ], 422);
        }
        
        $existingPickup = Pengambilan::where('ID_TRANSAKSI', $request->id_transaksi)->first();
        if ($existingPickup) {
            return response()->json([
                'message' => 'Pickup already exists for this transaction',
                'data' => $existingPickup
            ], 422);
        }
        
        $pengambilan = new Pengambilan();
        $pengambilan->ID_PEGAWAI = $request->id_pegawai;
        $pengambilan->ID_TRANSAKSI = $request->id_transaksi;
        $pengambilan->JADWAL_PENGAMBILAN = $request->jadwal_pengambilan;
        $pengambilan->STATUS_PENGEMBALIAN = 'Siap';
        $pengambilan->save();
        
        $transaksi->STATUS_TRANSAKSI = 'Siap diambil';
        $transaksi->save();

           try {
            $jadwalFormatted = Carbon::parse($request->jadwal_pengambilan)->format('d M Y H:i');
            $this->notificationService->sendJadwalNotification(
                $request->id_transaksi, 
                'Ambil', 
                $jadwalFormatted
            );
            \Log::info("✅ Jadwal pengambilan notification sent for transaksi: {$request->id_transaksi}");
        } catch (\Exception $e) {
            \Log::error("❌ Failed to send jadwal pengambilan notification: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Pickup scheduled successfully',
            'data' => $pengambilan->load(['pegawai', 'transaksi'])
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Siap,Menunggu Konfirmasi,Selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pengambilan = Pengambilan::findOrFail($id);
        
        // Validate status transition
        $validTransition = false;
        switch ($pengambilan->STATUS_PENGEMBALIAN) {
            case 'Siap':
                $validTransition = in_array($request->status, ['Menunggu Konfirmasi', 'Selesai']);
                break;
            case 'Menunggu Konfirmasi':
                $validTransition = $request->status === 'Selesai';
                break;
            case 'Selesai':
                $validTransition = false; 
                break;
        }
        
        if (!$validTransition) {
            return response()->json([
                'message' => 'Invalid status transition from ' . $pengambilan->STATUS_PENGEMBALIAN . ' to ' . $request->status
            ], 422);
        }
        
        $pengambilan->STATUS_PENGEMBALIAN = $request->status;
        
        if ($request->status === 'Selesai') {
            $pengambilan->TANGGAL_DIAMBIL = Carbon::now();
            
            $transaksi = Transaksi::find($pengambilan->ID_TRANSAKSI);
            if ($transaksi) {
                $transaksi->STATUS_TRANSAKSI = 'Sudah diterima';
                $transaksi->save();
            }
        }
        
        $pengambilan->save();

        return response()->json([
            'message' => 'Pickup status updated successfully',
            'data' => $pengambilan
        ]);
    }

    public function confirmCancellation($id)
    {
        $pengambilan = Pengambilan::findOrFail($id);
        
        $jadwalPengambilan = Carbon::parse($pengambilan->JADWAL_PENGAMBILAN);
        $twoDaysLater = $jadwalPengambilan->copy()->addDays(2);
        
        if (Carbon::now()->lt($twoDaysLater)) {
            return response()->json([
                'message' => 'Pickup cannot be cancelled yet. Wait until 48 hours after scheduled pickup time.'
            ], 422);
        }
        
        if ($pengambilan->STATUS_PENGEMBALIAN === 'Selesai') {
            return response()->json([
                'message' => 'Cannot cancel completed pickup'
            ], 422);
        }
        
        $pengambilan->STATUS_PENGEMBALIAN = 'Batal';
        $pengambilan->save();
        
        $transaksi = Transaksi::find($pengambilan->ID_TRANSAKSI);
        if ($transaksi) {
            $transaksi->STATUS_TRANSAKSI = 'Batal';
            $transaksi->save();
        }

        return response()->json([
            'message' => 'Pickup cancelled successfully',
            'data' => $pengambilan
        ]);
    }
}