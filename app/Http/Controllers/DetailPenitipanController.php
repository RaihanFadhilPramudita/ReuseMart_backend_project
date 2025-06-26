<?php

namespace App\Http\Controllers;

use App\Models\DetailPenitipan;
use App\Models\Penitipan;
use App\Models\Barang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DetailPenitipanController extends Controller
{
   public function index($penitipanId)
{
    $user = auth('sanctum')->user();
    $penitipan = Penitipan::findOrFail($penitipanId);
    
    if (isset($user->ID_PENITIP) && $penitipan->ID_PENITIP != $user->ID_PENITIP) {
        return response()->json(['error' => 'Unauthorized access to this consignment'], 403);
    }
    
    if (isset($user->ID_PEGAWAI) && isset($user->ID_JABATAN)) {
        $allowedRoles = [1, 3, 4]; // Owner, Admin, Gudang
        if (!in_array($user->ID_JABATAN, $allowedRoles)) {
            return response()->json(['error' => 'Unauthorized access to consignment data'], 403);
        }
    }
    
    $detailPenitipan = DetailPenitipan::with(['barang'])
        ->where('ID_PENITIPAN', $penitipanId)
        ->get();
        
    return response()->json(['data' => $detailPenitipan]);
}

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'id_penitipan' => 'required|exists:penitipan,ID_PENITIPAN',
        'id_barang' => 'required|exists:barang,ID_BARANG',
        'jumlah_barang_titipan' => 'required|integer|min:1',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }
    
    $user = auth('sanctum')->user();
    $penitipan = Penitipan::findOrFail($request->id_penitipan);
    
    if (isset($user->ID_PEGAWAI) && isset($user->ID_JABATAN)) {
        $allowedRoles = [1, 3, 4]; // Owner, Admin, Gudang
        if (!in_array($user->ID_JABATAN, $allowedRoles)) {
            return response()->json(['error' => 'Unauthorized to add consignment details'], 403);
        }
    } else {
        return response()->json(['error' => 'Only staff can add consignment details'], 403);
    }
    
    $existingDetail = DetailPenitipan::where('ID_BARANG', $request->id_barang)->first();
    if ($existingDetail) {
        return response()->json([
            'message' => 'This item is already in another consignment'
        ], 422);
    }
    
    $barang = Barang::find($request->id_barang);
    if (!$barang || $barang->STATUS_BARANG !== 'Tersedia') {
        return response()->json([
            'message' => 'Item is not available for consignment'
        ], 422);
    }
    
    $detailPenitipan = new DetailPenitipan();
    $detailPenitipan->ID_PENITIPAN = $request->id_penitipan;
    $detailPenitipan->ID_BARANG = $request->id_barang;
    $detailPenitipan->JUMLAH_BARANG_TITIPAN = $request->jumlah_barang_titipan;
    $detailPenitipan->save();
    
    return response()->json([
        'message' => 'Item added to consignment successfully',
        'data' => $detailPenitipan->load('barang')
    ], 201);
}
    
    public function show($penitipanId, $barangId)
    {
        $detailPenitipan = DetailPenitipan::with(['barang', 'penitipan'])
            ->where('ID_PENITIPAN', $penitipanId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        return response()->json(['data' => $detailPenitipan]);
    }
    
    public function update(Request $request, $penitipanId, $barangId)
    {
        $validator = Validator::make($request->all(), [
            'jumlah_barang_titipan' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $detailPenitipan = DetailPenitipan::where('ID_PENITIPAN', $penitipanId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        $detailPenitipan->JUMLAH_BARANG_TITIPAN = $request->jumlah_barang_titipan;
        $detailPenitipan->save();
        
        return response()->json([
            'message' => 'Consignment detail updated successfully',
            'data' => $detailPenitipan
        ]);
    }
    
    public function destroy($penitipanId, $barangId)
    {
        $detailPenitipan = DetailPenitipan::where('ID_PENITIPAN', $penitipanId)
            ->where('ID_BARANG', $barangId)
            ->firstOrFail();
            
        $otherItems = DetailPenitipan::where('ID_PENITIPAN', $penitipanId)
            ->where('ID_BARANG', '!=', $barangId)
            ->count();
            
        $detailPenitipan->delete();
        
        $message = 'Item removed from consignment successfully';
        if ($otherItems === 0) {
            $message .= '. Warning: Consignment is now empty';
        }
        
        return response()->json(['message' => $message]);
    }
    
    public function barangPenitipan($barangId)
    {
        $detailPenitipan = DetailPenitipan::with(['penitipan.penitip'])
            ->where('ID_BARANG', $barangId)
            ->first();
            
        if (!$detailPenitipan) {
            return response()->json(['message' => 'Item is not in any consignment'], 404);
        }
        
        return response()->json(['data' => $detailPenitipan]);
    }
}