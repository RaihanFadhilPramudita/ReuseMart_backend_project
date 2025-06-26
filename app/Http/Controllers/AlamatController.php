<?php

namespace App\Http\Controllers;

use App\Models\Alamat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AlamatController extends Controller
{
    public function index()
{
    try {
        $pembeli = auth('pembeli')->user();
        
        if (!$pembeli) {
            return response()->json(['error' => 'Pembeli tidak terautentikasi'], 401);
        }
        
        $pembeliId = $pembeli->id ?? $pembeli->ID_PEMBELI;
        
        $alamat = Alamat::where('ID_PEMBELI', $pembeliId)->get();
        return response()->json(['data' => $alamat]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Terjadi kesalahan: ' . $e->getMessage()
        ], 500);
    }
}

    public function show($id)
    {
        $alamat = Alamat::findOrFail($id);
        return response()->json(['data' => $alamat]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_alamat' => 'required|string|max:255',
            'alamat_lengkap' => 'required|string',
            'kecamatan' => 'required|string|max:255',
            'kota' => 'required|string|max:255',
            'kode_pos' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            $pembeli = auth('sanctum')->user();
            
            if (!$pembeli) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            $pembeliId = $pembeli->ID_PEMBELI ?? $pembeli->id;
            
            if (!$pembeliId) {
                return response()->json(['error' => 'ID pembeli tidak ditemukan'], 400);
            }
            
            $alamat = new Alamat();
            $alamat->ID_PEMBELI = $pembeliId;
            $alamat->NAMA_ALAMAT = $request->nama_alamat;
            $alamat->ALAMAT_LENGKAP = $request->alamat_lengkap;
            $alamat->KECAMATAN = $request->kecamatan;
            $alamat->KOTA = $request->kota;
            $alamat->KODE_POS = $request->kode_pos;
            $alamat->save();

            return response()->json([
                'message' => 'Address added successfully',
                'data' => $alamat
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to add address: ' . $e->getMessage()
            ], 500);
        }
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) return response()->json(['message' => 'Parameter q diperlukan.'], 422);

        $results = Alamat::where('ALAMAT', 'like', "%{$keyword}%")
            ->orWhere('KOTA', 'like', "%{$keyword}%")
            ->orWhere('PROVINSI', 'like', "%{$keyword}%")
            ->paginate(10);

        return response()->json(['data' => $results]);
    }

    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_alamat' => 'required|string|max:255',
                'alamat_lengkap' => 'required|string',
                'kecamatan' => 'required|string|max:255',
                'kota' => 'required|string|max:255',
                'kode_pos' => 'required|string|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $pembeli = auth('sanctum')->user();

            if (!$pembeli) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $pembeliId = $pembeli->ID_PEMBELI ?? $pembeli->id;

            if (!$pembeliId) {
                return response()->json(['error' => 'ID pembeli tidak ditemukan'], 400);
            }

            $alamat = Alamat::where('ID_ALAMAT', $id)
                ->where('ID_PEMBELI', $pembeliId)
                ->first();

            if (!$alamat) {
                return response()->json([
                    'error' => 'Alamat tidak ditemukan atau Anda tidak memiliki akses'
                ], 404);
            }

            $alamat->NAMA_ALAMAT     = $request->nama_alamat;
            $alamat->ALAMAT_LENGKAP  = $request->alamat_lengkap;
            $alamat->KECAMATAN       = $request->kecamatan;
            $alamat->KOTA            = $request->kota;
            $alamat->KODE_POS        = $request->kode_pos;
            $alamat->save();

            return response()->json([
                'message' => 'Address updated successfully',
                'data' => $alamat
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update address: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        $alamat = Alamat::findOrFail($id);
        $alamat->delete();

        return response()->json(['message' => 'Address deleted successfully']);
    }
}