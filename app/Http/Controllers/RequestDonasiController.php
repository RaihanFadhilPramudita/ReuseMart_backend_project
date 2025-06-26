<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\RequestDonasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Exception;


class RequestDonasiController extends Controller
{
    public function requestDonasi(Request $request)
    {
        $user = auth('organisasi')->user();

        if (!$user || !isset($user->ID_ORGANISASI)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }


         $query = RequestDonasi::where('ID_ORGANISASI', $user->ID_ORGANISASI);

        if ($request->has('q')) {
            $keyword = $request->q;
            $query->where(function ($q) use ($keyword) {
                $q->where('NAMA_BARANG', 'like', "%{$keyword}%")
                ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
                ->orWhere('STATUS_REQUEST', 'like', "%{$keyword}%");
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('TANGGAL_REQUEST')->get()
        ]);
    }

    public function indexAll(Request $request){
        $query = RequestDonasi::with('organisasi'); 

        if ($request->has('q')) {
            $keyword = $request->q;
            $query->where(function ($q) use ($keyword) {
                $q->where('NAMA_BARANG', 'like', "%{$keyword}%")
                ->orWhere('STATUS_REQUEST', 'like', "%{$keyword}%")
                ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
                ->orWhereHas('organisasi', function ($q) use ($keyword) {
                    $q->where('NAMA_ORGANISASI', 'like', "%{$keyword}%");
                });
            });
        }

        return response()->json([
            'data' => $query->orderByDesc('TANGGAL_REQUEST')->get()
        ]);

    }

    public function show($id)
    {
        $requestDonasi = RequestDonasi::with(['organisasi', 'donasi'])->findOrFail($id);
        return response()->json(['data' => $requestDonasi]);
    }

    public function store(Request $request)
    {
        $user = Auth::guard('organisasi')->user();

        if (!$user || !isset($user->ID_ORGANISASI)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'nama_barang' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $requestDonasi = new RequestDonasi();
        $requestDonasi->ID_ORGANISASI = $user->ID_ORGANISASI;
        $requestDonasi->NAMA_BARANG = $request->nama_barang;
        $requestDonasi->DESKRIPSI = $request->deskripsi;
        $requestDonasi->TANGGAL_REQUEST = now();
        $requestDonasi->STATUS_REQUEST = 'Menunggu';
        $requestDonasi->save();


        return response()->json([
            'message' => 'Donation request submitted successfully',
            'data' => $requestDonasi->load('organisasi')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang' => 'string|max:255',
            'deskripsi' => 'nullable|string',
            'status_request' => 'in:Menunggu,Disetujui,Ditolak,Terpenuhi',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $requestDonasi = RequestDonasi::findOrFail($id);
        
        if ($requestDonasi->STATUS_REQUEST === 'Terpenuhi') {
            return response()->json([
                'message' => 'Cannot update a fulfilled donation request'
            ], 422);
        }
        
        if($request->has('nama_barang')) $requestDonasi->NAMA_BARANG = $request->nama_barang;
        if($request->has('deskripsi')) $requestDonasi->DESKRIPSI = $request->deskripsi;
        if($request->has('status_request')) $requestDonasi->STATUS_REQUEST = $request->status_request;
        
        $requestDonasi->save();

        return response()->json([
            'message' => 'Donation request updated successfully',
            'data' => $requestDonasi
        ]);
    }

    public function destroy($id)
    {
        $requestDonasi = RequestDonasi::findOrFail($id);
        
        $hasDonations = $requestDonasi->donasi()->count() > 0;
        if ($hasDonations) {
            return response()->json([
                'message' => 'Cannot delete request with associated donations'
            ], 422);
        }
        
        $requestDonasi->delete();

        return response()->json(['message' => 'Donation request deleted successfully']);
    }

    public function organizationRequests($organizationId)
    {
        $requests = RequestDonasi::where('ID_ORGANISASI', $organizationId)
            ->orderBy('TANGGAL_REQUEST', 'desc')
            ->get();
            
        return response()->json(['data' => $requests]);
    }

    public function pendingRequests()
    {
        $pendingRequests = RequestDonasi::with('organisasi')
            ->where('STATUS_REQUEST', 'Menunggu')
            ->orderBy('TANGGAL_REQUEST', 'asc')
            ->get();
            
        return response()->json(['data' => $pendingRequests]);
    }

    public function search(Request $request)
    {
        $user = auth('organisasi')->user();

        if (!$user || !isset($user->ID_ORGANISASI)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $q = $request->input('q');

        $result = RequestDonasi::where('ID_ORGANISASI', $user->ID_ORGANISASI)
            ->where(function ($query) use ($q) {
                $query->where('NAMA_BARANG', 'like', "%{$q}%")
                    ->orWhere('DESKRIPSI', 'like', "%{$q}%");
            })
            ->orderBy('TANGGAL_REQUEST', 'desc')
            ->get();

        return response()->json(['data' => $result]);
    }

    public function searchAll(Request $request)
    {
        $q = $request->input('q');

        $result = RequestDonasi::with('organisasi') // relasi ke organisasi
            ->where(function ($query) use ($q) {
                $query->where('NAMA_BARANG', 'like', "%{$q}%")
                    ->orWhere('DESKRIPSI', 'like', "%{$q}%")
                    ->orWhere('STATUS_REQUEST', 'like', "%{$q}%")
                    ->orWhereHas('organisasi', function ($org) use ($q) {
                        $org->where('NAMA_ORGANISASI', 'like', "%{$q}%");
                    });
            })
            ->orderBy('TANGGAL_REQUEST', 'desc')
            ->get();

        return response()->json(['data' => $result]);
    }


    public function exportLaporanDonasiBarang()
    {
        try {
            $donasi = \App\Models\Donasi::with([
                    'barang.penitipan.penitip',
                    'barang.pegawai'
                ])
                ->orderByDesc('TANGGAL_DONASI')
                ->get();

            if ($donasi->isEmpty()) {
                return response()->json(['message' => 'Tidak ada data donasi barang.'], 404);
            }

            $tanggalCetak = Carbon::now();

            return Pdf::loadView('laporan.donasi_barang', compact('donasi', 'tanggalCetak'))
                ->download('laporan_donasi_barang.pdf');

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Gagal generate laporan',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ], 500);
        }
        
    }

    public function exportLaporanRequestDonasi()
    {
        $requestDonasi = RequestDonasi::with('organisasi')
            ->where('STATUS_REQUEST', '!=', 'Menerima Donasi')
            ->get();

        $tanggalCetak = Carbon::now();

        $pdf = Pdf::loadView('laporan.request_donasi', compact('requestDonasi', 'tanggalCetak'));
        return $pdf->download('laporan_request_donasi.pdf');
    }

}