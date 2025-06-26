<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\Penitipan;
use App\Models\DetailPenitipan;
use App\Models\KategoriBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class BarangController extends Controller
{
  public function indexPenitip(Request $request)
    {
        $penitip = auth('penitip')->user();
        $query = $request->input('q', '');

        $barang = Barang::where('ID_PENITIP', $penitip->ID_PENITIP);

        if (!empty($query)) {
            $barang->where(function($q) use ($query) {
                $q->where('NAMA_BARANG', 'like', "%{$query}%")
                ->orWhere('DESKRIPSI', 'like', "%{$query}%");
            });
        }

        $results = $barang->with('detailPenitipan.penitipan')->get()->map(function($item) {
            $expiryDate = null;
            if ($item->detailPenitipan && $item->detailPenitipan->first() && $item->detailPenitipan->first()->penitipan) {
                $expiryDate = $item->detailPenitipan->first()->penitipan->TANGGAL_KADALUARSA;
            }

            $gambarArray = [];
            if ($item->GAMBAR) {
                $decoded = json_decode($item->GAMBAR, true);
                if (is_array($decoded)) {
                    $gambarArray = array_map(fn($g) => asset('storage/' . $g), $decoded);
                }
            }

            return [
                'ID_BARANG' => $item->ID_BARANG,
                'NAMA_BARANG' => $item->NAMA_BARANG,
                'DESKRIPSI' => $item->DESKRIPSI,
                'HARGA' => $item->HARGA,
                'STATUS' => $item->STATUS_BARANG ?? 'Aktif',
                'TANGGAL_AKHIR' => $expiryDate ?? 
                    ($item->TANGGAL_MASUK ? Carbon::parse($item->TANGGAL_MASUK)->addDays(30)->format('Y-m-d') : null),
                'FOTO_1' => $gambarArray[0] ?? null,
                'FOTO_2' => $gambarArray[1] ?? null,
                'GAMBAR_BARANG' => $gambarArray,
            ];
        });

        return response()->json(['data' => $results]);
    }


    public function index(Request $request)
        {
            $barangs = Barang::query();
            
            if ($request->has('kategori')) {
                $barangs->where('ID_KATEGORI', $request->kategori);
            }
            
            if ($request->has('status')) {
                $barangs->where('STATUS_BARANG', $request->status);
            }
            
            if ($request->has('min_price')) {
                $barangs->where('HARGA', '>=', $request->min_price);
            }
            
            if ($request->has('max_price')) {
                $barangs->where('HARGA', '<=', $request->max_price);
            }
            
            if ($request->has('search')) {
                $search = $request->search;
                $barangs->where(function ($query) use ($search) {
                    $query->where('NAMA_BARANG', 'like', "%{$search}%")
                        ->orWhere('DESKRIPSI', 'like', "%{$search}%")
                        ->orWhere('MERK', 'like', "%{$search}%")
                        ->orWhere('KONDISI', 'like', "%{$search}%")
                        ->orWhere('HARGA', 'like', "%{$search}%")
                        ->orWhereHas('kategori', function ($q) use ($search) {
                            $q->where('NAMA_KATEGORI', 'like', "%{$search}%");
                        })
                        ->orWhereHas('penitip', function ($q) use ($search) {
                            $q->where('NAMA_PENITIP', 'like', "%{$search}%");
                            $q->where('BADGE', 'like', "%{$search}%");
                        });
                });
            }
            
            $result = $barangs->with(['kategori', 'penitip', 'pegawai', 'detailPenitipan.penitipan.hunter'])->get();

            $result->transform(function ($barang) {
                $barang->STATUS_GARANSI = $barang->TANGGAL_GARANSI && now()->lessThan($barang->TANGGAL_GARANSI);
                $barang->GAMBAR_URL = $barang->GAMBAR ? asset('storage/' . $barang->GAMBAR) : null;
                $barang->ID_PENITIPAN = optional($barang->detailPenitipan?->penitipan)->ID_PENITIPAN ?? null;
                $barang->TANGGAL_KADALUARSA = optional($barang->detailPenitipan?->penitipan)->TANGGAL_KADALUARSA;

                return $barang;
            });
            return response()->json(['data' => $result]);
        }

        public function similar($id)
        {
            $barang = Barang::findOrFail($id);

            $similar = Barang::where('ID_KATEGORI', $barang->ID_KATEGORI)
                ->where('ID_BARANG', '!=', $barang->ID_BARANG)
                ->take(4)
                ->get();

            $similar->transform(function ($b) {
                $gambarArray = json_decode($b->GAMBAR, true) ?? [];
                $b->GAMBAR_URLS = array_map(fn($path) => asset('storage/' . $path), $gambarArray);

                return $b;
            });

            return response()->json(['data' => $similar]);
        }


    public function barangLayakDonasi()
    {
        $barang = Barang::where('STATUS_BARANG', 'like', '%Barang Untuk Donasi%')->get();

        return response()->json(['data' => $barang]);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');

        if (!$keyword) {
            return response()->json(['message' => 'Parameter pencarian (q) wajib diisi.'], 422);
        }

        $barangs = Barang::with(['kategori', 'penitip', 'pegawai', 'detailPenitipan.penitipan.hunter'])
            ->where(function ($query) use ($keyword) {
                $query->where('NAMA_BARANG', 'like', "%{$keyword}%")
                    ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
                    ->orWhere('STATUS_BARANG', 'like', "%{$keyword}%")
                    ->orWhere('TANGGAL_MASUK', 'like', "%{$keyword}%")
                    ->orWhere('HARGA', 'like', "%{$keyword}%")
                    ->orWhereHas('kategori', function ($q) use ($keyword) {
                            $q->where('NAMA_KATEGORI', 'like', "%{$keyword}%");
                        })
                    ->orWhereHas('penitip', function ($q) use ($keyword) {
                        $q->where('NAMA_PENITIP', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('pegawai', function ($q) use ($keyword) {
                        $q->where('NAMA_PEGAWAI', 'like', "%{$keyword}%");
                    })
                    ->orWhere(function ($q) use ($keyword) {
                        if (str_contains(strtolower($keyword), 'garansi')) {
                            $q->whereDate('TANGGAL_GARANSI', '>=', now());
                        }
                    });
            })
            ->orWhereHas('kategori', function ($query) use ($keyword) {
                $query->where('NAMA_KATEGORI', 'like', "%{$keyword}%");
            })
            ->orWhereHas('penitip', function ($query) use ($keyword) {
                $query->where('NAMA_PENITIP', 'like', "%{$keyword}%");
            })
            ->get();

            $barangs->transform(function ($barang) {
            $barang->GAMBAR_URL = $barang->GAMBAR ? asset('storage/' . $barang->GAMBAR) : null;
            return $barang;
        });

        return response()->json(['data' => $barangs]);
    }



    public function show($id)
    {
        $barang = Barang::with(['penitip', 'kategori', 'detailPenitipan.penitipan'])->where('ID_BARANG', $id)->first();
        
        if (!$barang) {
            return response()->json(['message' => 'Barang not found'], 404);
        }
        
        $expiryDate = null;
        if ($barang->detailPenitipan && $barang->detailPenitipan->first() && $barang->detailPenitipan->first()->penitipan) {
            $expiryDate = $barang->detailPenitipan->first()->penitipan->TANGGAL_KADALUARSA;
        }

        $gambarArray = [];
        if ($barang->GAMBAR) {
            $decoded = json_decode($barang->GAMBAR, true);
            if (is_array($decoded)) {
                $gambarArray = array_map(fn($g) => asset('storage/' . $g), $decoded);
            }
        }
        
        $result = [
            'ID_BARANG' => $barang->ID_BARANG,
            'NAMA_BARANG' => $barang->NAMA_BARANG,
            'DESKRIPSI' => $barang->DESKRIPSI,
            'HARGA' => $barang->HARGA,
            'STATUS' => $barang->STATUS_BARANG ?? 'Aktif',
            'TANGGAL_TITIP' => $barang->TANGGAL_MASUK,
            'TANGGAL_AKHIR' => $expiryDate ?? 
                ($barang->TANGGAL_MASUK ? Carbon::parse($barang->TANGGAL_MASUK)->addDays(30)->format('Y-m-d') : null),
            'FOTO_1'         => $gambarArray[0] ?? null,
            'FOTO_2'         => $gambarArray[1] ?? null,
            'GAMBAR_BARANG'  => $gambarArray,
            'KATEGORI'        => $barang->kategori->NAMA_KATEGORI ?? '-',
            'PENITIP'        => [
                'NAMA_PENITIP'   => $barang->penitip->NAMA_PENITIP ?? '-',
                'RATING' => $barang->penitip->RATING ?? 0,
                'BADGE' => $barang->penitip->BADGE ?? '-',
            ],
            'STATUS_GARANSI' => $barang->STATUS_GARANSI ?? '-',
            'TANGGAL_GARANSI' => $barang->TANGGAL_GARANSI,
        ];
        
        return response()->json(['data' => $result]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_penitip' => 'required|exists:penitip,ID_PENITIP',
            'nama_barang' => 'required|string|max:255',
            'deskripsi' => 'required|string',
            'harga' => 'required|numeric|min:0',
            'tanggal_garansi' => 'nullable|date',
            'id_kategori' => 'required|exists:kategori_barang,ID_KATEGORI',
            'gambar' => 'required|array|min:2',
            'gambar.*' => 'image|mimes:jpeg,png,jpg,webp,bmp,gif',
            'pegawai_hunter' => 'nullable|exists:pegawai,ID_PEGAWAI'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pegawai = auth('sanctum')->user();
        if (!$pegawai || !isset($pegawai->ID_PEGAWAI)) {
            return response()->json(['error' => 'Unauthorized or invalid user type'], 401);
        }

        $tanggalMasuk = now();

        $gambarPaths = [];
        foreach ($request->file('gambar') as $file) {
            $gambarPaths[] = $file->store('barang', 'public');
        }

        $barang = Barang::create([
            'ID_PEGAWAI' => $pegawai->ID_PEGAWAI,
            'ID_PENITIP' => $request->id_penitip,
            'NAMA_BARANG' => $request->nama_barang,
            'DESKRIPSI' => $request->deskripsi,
            'HARGA' => $request->harga,
            'TANGGAL_MASUK' => $tanggalMasuk,
            'STATUS_BARANG' => 'Tersedia',
            'TANGGAL_GARANSI' => $request->tanggal_garansi,
            'STATUS_GARANSI' => $request->tanggal_garansi ? now()->lessThan($request->tanggal_garansi) : false,
            'ID_KATEGORI' => $request->id_kategori,
            'GAMBAR' => json_encode($gambarPaths),
        ]);

        $penitipan = Penitipan::create([
            'ID_PENITIP' => $barang->ID_PENITIP,
            'TANGGAL_MASUK' => $tanggalMasuk,
            'TANGGAL_KADALUARSA' => $tanggalMasuk->copy()->addDays(30),
            'TANGGAL_BATAS_AMBIL' => $tanggalMasuk->copy()->addDays(37),
            'STATUS_PENITIPAN' => true,
            'PERPANJANGAN' => false,
            'PEGAWAI_HUNTER' => $request->pegawai_hunter ?? null,
        ]);

        DetailPenitipan::create([
            'ID_PENITIPAN' => $penitipan->ID_PENITIPAN,
            'ID_BARANG' => $barang->ID_BARANG,
            'JUMLAH_BARANG_TITIPAN' => 1
        ]);

        return response()->json([
            'message' => 'Item added successfully',
            'data' => $barang
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_barang' => 'string|max:255',
            'deskripsi' => 'string',
            'harga' => 'numeric|min:0',
            'status_barang' => 'in:Tersedia,Sold Out, Barang untuk Donasi',
            'tanggal_garansi' => 'nullable|date',
            'id_kategori' => 'exists:kategori_barang,ID_KATEGORI',
            'id_penitip' => 'exists:penitip,ID_PENITIP',
            'gambar' => 'nullable|array|min:1',
            'gambar.*' => 'image|mimes:jpeg,png,jpg,webp,bmp,gif',
            'pegawai_hunter' => 'nullable|exists:pegawai,ID_PEGAWAI'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $barang = Barang::with('detailPenitipan.penitipan.hunter')->findOrFail($id);
        $oldPenitipId = $barang->ID_PENITIP;

        if ($request->has('nama_barang')) $barang->NAMA_BARANG = $request->nama_barang;
        if ($request->has('deskripsi')) $barang->DESKRIPSI = $request->deskripsi;
        if ($request->has('harga')) $barang->HARGA = $request->harga;
        if ($request->has('status_barang')) $barang->STATUS_BARANG = $request->status_barang;
        if ($request->has('tanggal_garansi')) {
            $barang->TANGGAL_GARANSI = $request->tanggal_garansi;
            $barang->STATUS_GARANSI = now()->lessThan($request->tanggal_garansi);
        }
        if ($request->has('id_kategori')) $barang->ID_KATEGORI = $request->id_kategori;
        if ($request->has('id_penitip')) $barang->ID_PENITIP = $request->id_penitip;

        if ($request->hasFile('gambar')) {
            $oldGambar = json_decode($barang->GAMBAR, true) ?? [];
            foreach ($oldGambar as $filePath) {
                if (Storage::disk('public')->exists($filePath)) {
                    Storage::disk('public')->delete($filePath);
                }
            }

            $gambarPaths = [];
            foreach ($request->file('gambar') as $file) {
                $gambarPaths[] = $file->store('barang', 'public');
            }
            $barang->GAMBAR = json_encode($gambarPaths);
        }

        $penitipBerubah = $request->has('id_penitip') && $request->id_penitip != $oldPenitipId;
        $penitipanLama = optional($barang->detailPenitipan)->penitipan ?? null;

        if ($penitipBerubah || !$penitipanLama) {
            DetailPenitipan::where('ID_BARANG', $barang->ID_BARANG)->delete();
            if ($penitipanLama) $penitipanLama->delete();

            $tanggalMasuk = $barang->TANGGAL_MASUK ?? now();
            $barang->TANGGAL_MASUK = $tanggalMasuk; 

            $barang->save();

            $penitipanBaru = Penitipan::create([
                'ID_PENITIP' => $barang->ID_PENITIP,
                'TANGGAL_MASUK' => $tanggalMasuk,
                'TANGGAL_KADALUARSA' => Carbon::parse($tanggalMasuk)->copy()->addDays(30),
                'TANGGAL_BATAS_AMBIL' => Carbon::parse($tanggalMasuk)->copy()->addDays(37),
                'STATUS_PENITIPAN' => true,
                'PERPANJANGAN' => false,
                'PEGAWAI_HUNTER' => $request->pegawai_hunter
            ]);

            DetailPenitipan::create([
                'ID_PENITIPAN' => $penitipanBaru->ID_PENITIPAN,
                'ID_BARANG' => $barang->ID_BARANG,
                'JUMLAH_BARANG_TITIPAN' => 1
            ]);
        } else {

            if ($request->filled('pegawai_hunter') && $penitipanLama) {
                $penitipanLama->PEGAWAI_HUNTER = $request->pegawai_hunter;
                $penitipanLama->save();
            }
            $barang->save();  
        }

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $barang
        ]);
    }

    public function soldOut($id)
    {
        $barang = Barang::findOrFail($id);
        $barang->STATUS_BARANG = 'Sold Out';
        $barang->TANGGAL_JUAL = now();
        $barang->save();

        return response()->json([
            'message' => 'Item marked as sold',
            'data' => $barang
        ]);
    }

    public function searchGudang(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) return response()->json(['message' => 'Parameter q diperlukan.'], 422);

        $barangs = Barang::with(['kategori', 'penitip'])
            ->where('NAMA_BARANG', 'like', "%{$keyword}%")
            ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
            ->orWhere('STATUS_BARANG', 'like', "%{$keyword}%")
            ->orWhereHas('penitip', function ($q) use ($keyword) {
                $q->where('NAMA_PENITIP', 'like', "%{$keyword}%");
            })
            ->get();

        return response()->json(['data' => $barangs]);
    }

    public function searchPenitip(Request $request)
    {
        $keyword = $request->input('q');
        $penitipId = $request->user()->ID_PENITIP ?? null;

        if (!$keyword || !$penitipId) return response()->json(['message' => 'Parameter tidak lengkap.'], 422);

        $barangs = Barang::with(['kategori'])
            ->where('ID_PENITIP', $penitipId)
            ->where(function($q) use ($keyword) {
                $q->where('NAMA_BARANG', 'like', "%{$keyword}%")
                ->orWhere('DESKRIPSI', 'like', "%{$keyword}%")
                ->orWhere('STATUS_BARANG', 'like', "%{$keyword}%");
            })
            ->paginate(10);

        return response()->json(['data' => $barangs]);
    }

    public function konfirmasiAmbil($id)
    {
        $barang = Barang::findOrFail($id);

        if ($barang->ID_PENITIP != auth('penitip')->id()) {
            return response()->json(['message' => 'Tidak diizinkan.'], 403);
        }

        $barang->STATUS_BARANG = 'Siap Diambil';
        $barang->TANGGAL_KONFIRMASI_AMBIL = now();
        $barang->save();

        return response()->json([
            'message' => 'Konfirmasi berhasil dicatat',
            'data' => $barang
        ]);
    }


    public function ambilKembali($id)
    {
        $barang = Barang::findOrFail($id);



        if ($barang->STATUS_BARANG !== 'Siap Diambil') {
            return response()->json(['message' => 'Barang belum dikonfirmasi oleh penitip'], 400);
        }

        $penitipan = $barang->detailPenitipan->penitipan ?? null;

        if (!$penitipan || $penitipan->TANGGAL_KADALUARSA >= now()) {
            return response()->json(['message' => 'Barang belum melewati masa penitipan.'], 400);
        }

        $barang->STATUS_BARANG = 'Diambil Kembali';
        $barang->TANGGAL_KELUAR = now();
        $barang->save();

        return response()->json(['message' => 'Barang berhasil diambil kembali']);
    }

    public function extendConsignment($id)
    {
        try {
            $barang = Barang::findOrFail($id);
            $detailPenitipan = DetailPenitipan::where('ID_BARANG', $id)->first();

            if (!$detailPenitipan) {
                return response()->json(['message' => 'Tidak ditemukan data penitipan untuk barang ini'], 404);
            }

            $penitipan = Penitipan::findOrFail($detailPenitipan->ID_PENITIPAN);

            // ⛑ Gunakan now() jika tanggal kadaluarsa sudah lewat
            $baseDate = now()->greaterThan($penitipan->TANGGAL_KADALUARSA)
                ? now()
                : new \DateTime($penitipan->TANGGAL_KADALUARSA);

            $newExpiryDate = clone $baseDate;
            $newExpiryDate->add(new \DateInterval('P30D'));

            $newPickupDate = clone $newExpiryDate;
            $newPickupDate->add(new \DateInterval('P7D'));

            // Update
            $penitipan->TANGGAL_KADALUARSA = $newExpiryDate;
            $penitipan->TANGGAL_BATAS_AMBIL = $newPickupDate;
            $penitipan->STATUS_PERPANJANGAN = true;
            $penitipan->save();

            return response()->json([
                'message' => 'Masa penitipan berhasil diperpanjang',
                'data' => $penitipan
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperpanjang masa penitipan: ' . $e->getMessage()
            ], 500);
        }
    }


    public function exportLaporanStokGudang()
    {
        $barang = Barang::with([
            'detailPenitipan.penitipan.hunter.jabatan',
            'pegawai.jabatan',
            'penitip'
        ])
        ->where('STATUS_BARANG', 'Tersedia') 
        ->whereHas('detailPenitipan.penitipan', function ($query) {
            $query->whereDate('TANGGAL_KADALUARSA', '>=', now()); 
        })
        ->get();

        $tanggalCetak = now();

        $pdf = Pdf::loadView('laporan.stok_gudang', compact('barang', 'tanggalCetak'));
        return $pdf->download('laporan_stok_gudang.pdf');
    }


    public function exportLaporanBarangKadaluarsa()
    {
        $barangKadaluarsa = Barang::with([
            'penitip',
            'detailPenitipan.penitipan'
        ])
        ->where('STATUS_BARANG', 'Kadaluarsa') 
        ->whereHas('detailPenitipan.penitipan', function ($query) {
            $query->whereDate('TANGGAL_KADALUARSA', '<', now());
        })
        ->get();

        $tanggalCetak = now();

        $pdf = Pdf::loadView('laporan.barang_kadaluarsa', compact('barangKadaluarsa', 'tanggalCetak'));
        return $pdf->download('laporan_barang_kadaluarsa.pdf');
    }
}