<?php

namespace App\Http\Controllers;

use App\Models\Donasi;
use App\Models\Barang;
use App\Models\RequestDonasi;
use App\Models\Penitip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ReuseMartNotificationService; 


class DonasiController extends Controller
{

    protected $notificationService; // ✅ ADD THIS

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        $this->notificationService = $notificationService; // ✅ ADD THIS
    }

    public function index()
    {
        $data = Donasi::with(['barang', 'requestDonasi.organisasi'])
        ->whereHas('barang', function ($query) {
            $query->where('STATUS_BARANG', 'Didonasikan');
        })
        ->get();

        return response()->json(['data' => $data]);
    }

    public function show($id)
    {
        $donasi = Donasi::with(['barang', 'requestDonasi.organisasi'])->findOrFail($id);
        return response()->json(['data' => $donasi]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_barang' => 'required|exists:barang,ID_BARANG',
            'id_request' => 'required|exists:request_donasi,ID_REQUEST',
            'nama_penerima' => 'required|string|max:255',
            'jenis_barang' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $barang = Barang::findOrFail($request->id_barang);
        if ($barang->STATUS_BARANG !== 'Barang Untuk Donasi') {
            return response()->json(['message' => 'Barang tidak layak untuk donasi'], 422);
        }


        $existing = Donasi::where('ID_BARANG', $request->id_barang)->first();
        if ($existing) {
            return response()->json(['message' => 'Barang sudah didonasikan', 'data' => $existing], 422);
        }

        $donasi = Donasi::create([
            'ID_BARANG' => $request->id_barang,
            'ID_REQUEST' => $request->id_request,
            'TANGGAL_DONASI' => Carbon::now(),
            'NAMA_PENERIMA' => $request->nama_penerima,
            'JENIS_BARANG' => $request->jenis_barang,
        ]);

        $barang->STATUS_BARANG = 'Didonasikan';
        $barang->save();

        $requestDonasi = RequestDonasi::find($request->id_request);
        if ($requestDonasi) {
            $requestDonasi->STATUS_REQUEST = 'Menerima Donasi';
            $requestDonasi->save();
        }

        if ($barang->ID_PENITIP) {
            $penitip = Penitip::find($barang->ID_PENITIP);
            if ($penitip) {
                $poin = floor($barang->HARGA / 10000);
                $penitip->POIN_SOSIAL += $poin;
                $penitip->save();
            }
        }

         try {
            $this->notificationService->sendBarangDidonasikanNotification($donasi->ID_DONASI);
            \Log::info("✅ Barang didonasikan notification sent for donasi: {$donasi->ID_DONASI}");
        } catch (\Exception $e) {
            \Log::error("❌ Failed to send barang didonasikan notification: " . $e->getMessage());
        }
        
        return response()->json([
            'message' => 'Donasi berhasil dicatat',
            'data' => $donasi->load(['barang', 'requestDonasi.organisasi'])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'id_barang' => 'nullable|exists:barang,ID_BARANG',
            'id_request' => 'nullable|exists:request_donasi,ID_REQUEST',
            'nama_penerima' => 'nullable|string|max:255',
            'tanggal_donasi' => 'nullable|date',
            'status_barang' => 'nullable|string|in:pending,Didonasikan,Selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $donasi = Donasi::findOrFail($id);

        if ($request->filled('id_barang')) {
            $donasi->ID_BARANG = $request->id_barang;
        }
        if ($request->filled('id_request')) {
            $donasi->ID_REQUEST = $request->id_request;
        }
        if ($request->filled('nama_penerima')) {
            $donasi->NAMA_PENERIMA = $request->nama_penerima;
        }
        if ($request->filled('tanggal_donasi')) {
            $donasi->TANGGAL_DONASI = $request->tanggal_donasi;
        }

        $donasi->save();

        if ($request->filled('status_barang')) {
            $barang = $donasi->barang;
            if ($barang) {
                $barang->STATUS_BARANG = $request->status_barang;
                $barang->save();

                if ($barang->ID_PENITIP) {
                    $penitip = Penitip::find($barang->ID_PENITIP);
                    if ($penitip) {
                        Log::info("Penitip {$penitip->NAMA_PENITIP} diberi notifikasi status barang berubah ke {$barang->STATUS_BARANG}.");
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Data donasi berhasil diperbarui.',
            'data' => $donasi->load(['barang', 'requestDonasi.organisasi'])
        ]);
    }



    public function historyByNamaOrganisasi(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) {
            return response()->json(['message' => 'Parameter q diperlukan.'], 422);
        }

        $donasi = Donasi::with(['barang', 'requestDonasi.organisasi'])
            ->whereHas('requestDonasi.organisasi', function ($query) use ($keyword) {
                $query->where('NAMA_ORGANISASI', 'like', '%' . $keyword . '%');
            })
            ->orderBy('TANGGAL_DONASI', 'desc')
            ->get();

        return response()->json(['data' => $donasi]);
    }

    public function exportLaporanDonasiBarang()
    {
        $donasi = Donasi::with([
            'barang.penitip',
            'barang',
            'requestDonasi',
            'requestDonasi.organisasi',
        ])->get();


        $tanggalCetak = now();

        $pdf = Pdf::loadView('laporan.donasi_barang', compact('donasi', 'tanggalCetak'));
        return $pdf->download('laporan_donasi_barang.pdf');
    }


    public function search(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) return response()->json(['message' => 'Parameter q diperlukan.'], 422);

        $results = Donasi::where('NAMA_PENERIMA', 'like', "%{$keyword}%")
            ->orWhere('JENIS_BARANG', 'like', "%{$keyword}%")
            ->with(['barang', 'requestDonasi.organisasi'])
            ->paginate(10);

        return response()->json(['data' => $results]);
    }
}
