<?php

namespace App\Http\Controllers;

use App\Models\Diskusi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
class DiskusiController extends Controller
{

     public function index($barangId)
    {
        $diskusi = Diskusi::with(['barang', 'pembeli', 'pegawai'])
            ->where('ID_BARANG', $barangId)
            ->orderBy('ID_DISKUSI', 'desc')
            ->get();
            
        return response()->json(['data' => $diskusi]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'id_barang' => 'required|exists:barang,ID_BARANG',
            'isi_pesan' => 'required|string'
        ]);

        if (auth('pembeli')->check()) {
            $user = auth('pembeli')->user();
            $diskusi = $user->diskusi()->create([
                'ID_BARANG' => $request->id_barang,
                'ISI_PESAN' => $request->isi_pesan
            ]);
        } elseif (auth('pegawai')->check()) {
            $user = auth('pegawai')->user();
            $diskusi = $user->diskusi()->create([
                'ID_BARANG' => $request->id_barang,
                'ISI_PESAN' => $request->isi_pesan
            ]);
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(['message' => 'Komentar berhasil ditambahkan'], 201);
    }

    public function customerDiscussions()
    {
        $pembeli = Auth::guard('pembeli')->user();
        if (!$pembeli) return response()->json(['message' => 'Unauthorized'], 401);

        $diskusi = Diskusi::with(['barang', 'pegawai'])
            ->where('ID_PEMBELI', $pembeli->ID_PEMBELI)
            ->orderBy('ID_DISKUSI', 'desc')
            ->get();

        return response()->json(['data' => $diskusi]);
    }

    public function productDiscussions($barangId)
    {
        $diskusi = Diskusi::with(['pembeli', 'pegawai'])
            ->where('ID_BARANG', $barangId)
            ->orderBy('ID_DISKUSI', 'asc')
            ->get();

        return response()->json(['data' => $diskusi]);
    }

    public function csDiscussions()
    {
        $pegawai = Auth::guard('pegawai')->user();
        if (!$pegawai || strtolower($pegawai->jabatan->NAMA_JABATAN) != 'Customer Service') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $diskusi = Diskusi::with(['barang', 'pembeli'])
            ->where('ID_PEGAWAI', $pegawai->ID_PEGAWAI)
            ->orderBy('ID_DISKUSI', 'desc')
            ->get();

        return response()->json(['data' => $diskusi]);
    }

}