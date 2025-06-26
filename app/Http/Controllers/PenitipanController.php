<?php

namespace App\Http\Controllers;

use App\Models\Penitipan;
use App\Models\DetailPenitipan;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PenitipanController extends Controller
{
    public function index()
    {
        $penitipan = Penitipan::with(['hunter', 'penitip', 'detailPenitipan.barang'])->get();
        return response()->json(['data' => $penitipan]);
    }

    public function show($id)
    {
        $penitipan = Penitipan::with(['penitip', 'detailPenitipan.barang'])->findOrFail($id);
        return response()->json(['data' => $penitipan]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_penitip' => 'required|exists:penitip,ID_PENITIP',
            'barang_ids' => 'required|array',
            'barang_ids.*' => 'exists:barang,ID_BARANG',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $penitipan = new Penitipan();
        $penitipan->ID_PENITIP = $request->id_penitip;
        $penitipan->TANGGAL_MASUK = now();
        $penitipan->TANGGAL_KADALUARSA = now()->addDays(30);
        $penitipan->TANGGAL_BATAS_AMBIL = now()->addDays(37); // 7 days after expiration
        $penitipan->STATUS_PENITIPAN = true;
        $penitipan->save();

        foreach ($request->barang_ids as $barangId) {
            $detail = new DetailPenitipan();
            $detail->ID_PENITIPAN = $penitipan->ID_PENITIPAN;
            $detail->ID_BARANG = $barangId;
            $detail->JUMLAH_BARANG_TITIPAN = 1; // Since every barang is unique
            $detail->save();
        }

        return response()->json([
            'message' => 'Consignment created successfully',
            'data' => $penitipan->load(['penitip', 'detailPenitipan.barang'])
        ], 201);
    }

    public function extend($id)
    {
        $penitipan = Penitipan::findOrFail($id);
        
        $creationDate = Carbon::parse($penitipan->TANGGAL_MASUK);
        $expirationDate = Carbon::parse($penitipan->TANGGAL_KADALUARSA);
        
        if ($expirationDate->diffInDays($creationDate) > 30) {
            return response()->json(['message' => 'This consignment has already been extended'], 422);
        }
        
        $penitipan->TANGGAL_KADALUARSA = Carbon::parse($penitipan->TANGGAL_KADALUARSA)->addDays(30);
        $penitipan->TANGGAL_BATAS_AMBIL = Carbon::parse($penitipan->TANGGAL_BATAS_AMBIL)->addDays(30);
        $penitipan->save();

        return response()->json([
            'message' => 'Consignment period extended successfully',
            'data' => $penitipan
        ]);
    }

    public function cancel($id)
    {
        $penitipan = Penitipan::findOrFail($id);
        
        $soldItems = 0;
        foreach ($penitipan->detailPenitipan as $detail) {
            if ($detail->barang->STATUS_BARANG === 'Sold Out') {
                $soldItems++;
            }
        }
        
        if ($soldItems > 0) {
            return response()->json([
                'message' => 'Cannot cancel consignment with sold items',
                'sold_items' => $soldItems
            ], 422);
        }
        
        $penitipan->STATUS_PENITIPAN = false;
        $penitipan->save();

        return response()->json([
            'message' => 'Consignment cancelled successfully',
            'data' => $penitipan
        ]);
    }

    public function expiredConsignments()
    {
        $today = Carbon::today();
        
        $expiredPenitipan = Penitipan::with(['penitip', 'detailPenitipan.barang'])
            ->where('TANGGAL_KADALUARSA', '<', $today)
            ->where('STATUS_PENITIPAN', true)
            ->get();
            
        return response()->json(['data' => $expiredPenitipan]);
    }

    public function perpanjang($id){
        $penitipan = Penitipan::findOrFail($id);

        if ($penitipan->STATUS_PERPANJANGAN == 1) {
            return response()->json([
                'message' => 'Masa penitipan hanya bisa diperpanjang 1 kali saja.'
            ], 422);
        }

        $penitipan->TANGGAL_AKHIR = Carbon::parse($penitipan->TANGGAL_AKHIR)->addDays(30);
        $penitipan->STATUS_PERPANJANGAN = 1; // set sebagai sudah diperpanjang
        $penitipan->save();

        return response()->json([
            'message' => 'Masa penitipan berhasil diperpanjang 30 hari.',
            'data' => $penitipan
        ]);
    }

    public function pastDueConsignments()
    {
        $today = Carbon::today();
        
        $pastDuePenitipan = Penitipan::with(['penitip', 'detailPenitipan.barang'])
            ->where('TANGGAL_BATAS_AMBIL', '<', $today)
            ->where('STATUS_PENITIPAN', true)
            ->get();
            
        return response()->json(['data' => $pastDuePenitipan]);
    }

    public function update(Request $request, $id)
    {
        $penitipan = Penitipan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'barang_ids' => 'nullable|array',
            'barang_ids.*' => 'exists:barang,ID_BARANG',
            'status_penitipan' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->has('status_penitipan')) {
            $penitipan->STATUS_PENITIPAN = $request->status_penitipan;
        }

        if ($request->has('barang_ids')) {
            $penitipan->detailPenitipan()->delete();
            foreach ($request->barang_ids as $barangId) {
                $detail = new DetailPenitipan();
                $detail->ID_PENITIPAN = $penitipan->ID_PENITIPAN;
                $detail->ID_BARANG = $barangId;
                $detail->JUMLAH_BARANG_TITIPAN = 1;
                $detail->save();
            }
        }

        $penitipan->save();

        return response()->json([
            'message' => 'Penitipan updated successfully',
            'data' => $penitipan->load(['penitip', 'detailPenitipan.barang']),
        ]);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) {
            return response()->json(['message' => 'Keyword (q) is required.'], 422);
        }

        $results = Penitipan::with(['penitip', 'detailPenitipan.barang'])
            ->whereHas('penitip', function ($query) use ($keyword) {
                $query->where('NAMA_PENITIP', 'like', "%{$keyword}%");
            })
            ->orWhereHas('detailPenitipan.barang', function ($query) use ($keyword) {
                $query->where('NAMA_BARANG', 'like', "%{$keyword}%")
                    ->orWhere('DESKRIPSI', 'like', "%{$keyword}%");
            })
            ->orWhere('STATUS_PENITIPAN', 'like', "%{$keyword}%")
            ->paginate(10);

        return response()->json(['data' => $results]);
    }

    public function previewNota($id)
    {
        $penitipan = Penitipan::with(['penitip', 'detailPenitipan.barang'])->findOrFail($id);
        $pdf = Pdf::loadView('pdf.nota-penitipan', ['penitipan' => $penitipan])
            ->setPaper('A4', 'portrait');

        return $pdf->stream('nota-penitipan-' . $id . '.pdf');
    }

    public function cetakNota($id)
    {
        if (!$id || $id === 'null') {
            return response()->json(['message' => 'ID Penitipan tidak valid'], 400);
        }
        
        $penitipan = Penitipan::with(['penitip', 'detailPenitipan.barang.pegawai'])
            ->findOrFail($id);

        $pdf = Pdf::loadView('pdf.nota-penitipan', compact('penitipan'));
        return $pdf->download("nota-penitipan-{$penitipan->ID_PENITIPAN}.pdf");
    }
}