<?php

namespace App\Http\Controllers;

use App\Models\Merchandise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\DetailRedeem;
use App\Models\RedeemMerch;
use Illuminate\Support\Carbon;

class MerchandiseController extends Controller
{
    public function index()
    {
        $merchandise = Merchandise::all();
        return response()->json(['data' => $merchandise]);
    }

    public function show($id)
    {
        $merchandise = Merchandise::findOrFail($id);
        return response()->json(['data' => $merchandise]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_merchandise' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'poin_required' => 'required|integer|min:1',
            'stok' => 'required|integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $merchandise = new Merchandise();
        $merchandise->NAMA_MERCHANDISE = $request->nama_merchandise;
        $merchandise->DESKRIPSI = $request->deskripsi;
        $merchandise->POIN_REQUIRED = $request->poin_required;
        $merchandise->STOK = $request->stok;
        $merchandise->save();

        return response()->json([
            'message' => 'Merchandise added successfully',
            'data' => $merchandise
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_merchandise' => 'string|max:255',
            'deskripsi' => 'string',
            'poin_required' => 'integer|min:1',
            'stok' => 'integer|min:0',
            'foto' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $merchandise = Merchandise::findOrFail($id);
        
        if($request->has('nama_merchandise')) $merchandise->NAMA_MERCHANDISE = $request->nama_merchandise;
        if($request->has('deskripsi')) $merchandise->DESKRIPSI = $request->deskripsi;
        if($request->has('poin_required')) $merchandise->POIN_REQUIRED = $request->poin_required;
        if($request->has('stok')) $merchandise->STOK = $request->stok;
        
        $merchandise->save();

        return response()->json([
            'message' => 'Merchandise updated successfully',
            'data' => $merchandise
        ]);
    }

    public function destroy($id)
    {
        $merchandise = Merchandise::findOrFail($id);
        
        $hasRedeems = $merchandise->detailRedeem()->count() > 0;
        if ($hasRedeems) {
            return response()->json([
                'message' => 'Cannot delete merchandise that has been redeemed'
            ], 422);
        }
        
        $merchandise->delete();

        return response()->json(['message' => 'Merchandise deleted successfully']);
    }

    public function updateStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'stok' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $merchandise = Merchandise::findOrFail($id);
        $merchandise->STOK = $request->stok;
        $merchandise->save();

        return response()->json([
            'message' => 'Merchandise stock updated successfully',
            'data' => $merchandise
        ]);
    }
    public function search(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) return response()->json(['message' => 'Parameter q diperlukan.'], 422);

        $results = Merchandise::where('NAMA_MERCHANDISE', 'like', "%{$keyword}%")
            ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
            ->paginate(10);

        return response()->json(['data' => $results]);
    }

    public function klaimList()
    {
        $data = DetailRedeem::with(['redeemMerch.pembeli', 'merchandise'])
            ->get()
            ->sortByDesc(fn($item) => $item->redeemMerch->TANGGAL_REDEEM)
            ->values()
            ->map(function ($item) {
                return [
                    'ID_REDEEM' => $item->redeemMerch->ID_REDEEM,
                    'NAMA_PEMBELI' => $item->redeemMerch->pembeli->NAMA_PEMBELI ?? '-',
                    'NAMA_MERCHANDISE' => $item->merchandise->NAMA_MERCHANDISE ?? '-',
                    'TANGGAL_REDEEM' => $item->redeemMerch->TANGGAL_REDEEM ?? '-',
                    'TANGGAL_AMBIL' => $item->redeemMerch->TANGGAL_AMBIL,
                    'STATUS' => $item->redeemMerch->STATUS 
                                ?? ($item->redeemMerch->TANGGAL_AMBIL 
                                    ? 'Sudah Diambil pada ' . $item->redeemMerch->TANGGAL_AMBIL 
                                    : 'Belum Diambil'),
                ];
            });

        return response()->json(['data' => $data]);
    }

    public function isiTanggalAmbil(Request $request, $id_redeem)
    {
        $validator = Validator::make($request->all(), [
            'tanggal_ambil' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Ambil data Redeem_Merch berdasarkan ID_REDEEM
        $redeem = RedeemMerch::with(['pembeli', 'detailRedeem.merchandise'])->findOrFail($id_redeem);

        // Simpan tanggal ambil
        $redeem->TANGGAL_AMBIL = Carbon::parse($request->tanggal_ambil);
        $redeem->STATUS = 'Sudah Diambil';
        $redeem->save();

        // Ambil semua detail redeem terkait
        $detail = $redeem->detailRedeem->first(); // ambil satu contoh untuk tampilan, bisa juga looping kalau perlu

        return response()->json([
            'message' => 'Tanggal ambil berhasil diisi',
            'data' => [
                'ID_REDEEM' => $redeem->ID_REDEEM,
                'NAMA_PEMBELI' => $redeem->pembeli->NAMA_PEMBELI ?? '-',
                'NAMA_MERCHANDISE' => $detail?->merchandise->NAMA_MERCHANDISE ?? '-',
                'TANGGAL_REDEEM' => $redeem->TANGGAL_REDEEM ?? '-',
                'TANGGAL_AMBIL' => $redeem->TANGGAL_AMBIL,
                'STATUS' => $redeem->TANGGAL_AMBIL 
                    ? 'Sudah Diambil pada ' . $redeem->TANGGAL_AMBIL 
                    : 'Belum Diambil',
            ]
        ]);
    }


}