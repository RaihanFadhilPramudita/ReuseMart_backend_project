<?php

namespace App\Http\Controllers;

use App\Models\DetailTransaksi;
use App\Models\Transaksi;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DetailTransaksiController extends Controller
{
    public function index($transaksiId)
    {
        $detailTransaksi = DetailTransaksi::with(['barang'])
            ->where('ID_TRANSAKSI', $transaksiId)
            ->get();
            
        return response()->json(['data' => $detailTransaksi]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_transaksi' => 'required|exists:transaksi,ID_TRANSAKSI',
            'id_barang' => 'required|exists:barang,ID_BARANG',
            'jumlah' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if transaction is still editable
        $transaksi = Transaksi::find($request->id_transaksi);
        if (!$transaksi || $transaksi->STATUS_TRANSAKSI !== 'Belum dibayar') {
            return response()->json([
                'message' => 'Transaction is no longer editable'
            ], 422);
        }
        
        // Check if item is available
        $barang = Barang::find($request->id_barang);
        if (!$barang || $barang->STATUS_BARANG !== 'Tersedia') {
            return response()->json([
                'message' => 'Item is not available for purchase'
            ], 422);
        }
        
        // Check if item is already in this transaction
        $existingDetail = DetailTransaksi::where('ID_TRANSAKSI', $request->id_transaksi)
            ->where('ID_BARANG', $request->id_barang)
            ->first();
            
        if ($existingDetail) {
            // Update quantity instead of creating new
            $existingDetail->JUMLAH = $existingDetail->JUMLAH + $request->jumlah;
            $existingDetail->save();
            
            // Recalculate transaction total
            $this->recalculateTransactionTotal($request->id_transaksi);
            
            return response()->json([
                'message' => 'Item quantity updated in transaction',
                'data' => $existingDetail->load('barang')
            ]);
        }
        
        // Create new detail
        $detailTransaksi = new DetailTransaksi();
        $detailTransaksi->ID_TRANSAKSI = $request->id_transaksi;
        $detailTransaksi->ID_BARANG = $request->id_barang;
        $detailTransaksi->JUMLAH = $request->jumlah;
        $detailTransaksi->save();
        
        // Recalculate transaction total
        $this->recalculateTransactionTotal($request->id_transaksi);
        
        return response()->json([
            'message' => 'Item added to transaction successfully',
            'data' => $detailTransaksi->load('barang')
        ], 201);
    }
    
    public function show($transaksiId, $barangId)
    {
        $detailTransaksi = DetailTransaksi::with(['barang', 'transaksi'])
            ->where('ID_TRANSAKSI', $transaksiId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        return response()->json(['data' => $detailTransaksi]);
    }
    
    public function update(Request $request, $transaksiId, $barangId)
    {
        $validator = Validator::make($request->all(), [
            'jumlah' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check if transaction is still editable
        $transaksi = Transaksi::find($transaksiId);
        if (!$transaksi || $transaksi->STATUS_TRANSAKSI !== 'Belum dibayar') {
            return response()->json([
                'message' => 'Transaction is no longer editable'
            ], 422);
        }
        
        $detailTransaksi = DetailTransaksi::where('ID_TRANSAKSI', $transaksiId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        $detailTransaksi->JUMLAH = $request->jumlah;
        $detailTransaksi->save();
        
        // Recalculate transaction total
        $this->recalculateTransactionTotal($transaksiId);
        
        return response()->json([
            'message' => 'Transaction detail updated successfully',
            'data' => $detailTransaksi
        ]);
    }
    
    public function destroy($transaksiId, $barangId)
    {
        // Check if transaction is still editable
        $transaksi = Transaksi::find($transaksiId);
        if (!$transaksi || $transaksi->STATUS_TRANSAKSI !== 'Belum dibayar') {
            return response()->json([
                'message' => 'Transaction is no longer editable'
            ], 422);
        }
        
        $detailTransaksi = DetailTransaksi::where('ID_TRANSAKSI', $transaksiId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        // Check if the transaction has other items
        $otherItems = DetailTransaksi::where('ID_TRANSAKSI', $transaksiId)
            ->where('ID_BARANG', '!=', $barangId)
            ->count();
            
        $detailTransaksi->delete();
        
        // Recalculate transaction total
        $this->recalculateTransactionTotal($transaksiId);
        
        // If no other items, notify that the transaction is empty
        $message = 'Item removed from transaction successfully';
        if ($otherItems === 0) {
            $message .= '. Warning: Transaction is now empty';
        }
        
        return response()->json(['message' => $message]);
    }
    
    /**
     * Recalculate transaction total based on items
     */
    private function recalculateTransactionTotal($transaksiId)
    {
        $transaksi = Transaksi::find($transaksiId);
        if (!$transaksi) return;
        
        $totalHarga = 0;
        $details = DetailTransaksi::where('ID_TRANSAKSI', $transaksiId)->get();
        
        foreach ($details as $detail) {
            $barang = Barang::find($detail->ID_BARANG);
            if ($barang) {
                $totalHarga += $barang->HARGA * $detail->JUMLAH;
            }
        }
        
        // Recalculate shipping cost if applicable
        if ($transaksi->JENIS_DELIVERY === 'Antar') {
            $ongkosKirim = ($totalHarga >= 1500000) ? 0 : 100000;
            $transaksi->ONGKOS_KIRIM = $ongkosKirim;
        }
        
        $transaksi->TOTAL_HARGA = $totalHarga;
        $transaksi->TOTAL_AKHIR = $totalHarga + $transaksi->ONGKOS_KIRIM - $transaksi->POTONGAN_POIN;
        
        // Recalculate points earned
        $pointsEarned = floor($totalHarga / 10000);
        if ($totalHarga > 500000) {
            $pointsEarned += floor($pointsEarned * 0.2); // 20% bonus points for transactions over 500k
        }
        $transaksi->POIN_DIDAPAT = $pointsEarned;
        
        $transaksi->save();
    }
}