<?php

namespace App\Http\Controllers;

use App\Models\Komisi;
use App\Models\Pegawai;
use App\Models\Transaksi;
use App\Models\DetailTransaksi;
use App\Models\DetailPenitipan;
use App\Models\Penitipan;
use App\Models\Barang;
use App\Models\Pembeli;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ReuseMartNotificationService; 
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;


class TransaksiController extends Controller
{
    protected $notificationService; // ✅ ADD THIS

    public function __construct(ReuseMartNotificationService $notificationService)
    {
        $this->notificationService = $notificationService; // ✅ ADD THIS
    }
    public function index(Request $request)
    {
        try {
            $statusParam = ucfirst(strtolower($request->get('status', 'Diproses')));

            $query = Transaksi::query();

            if ($statusParam === 'Selesai') {
                $query->where('STATUS_TRANSAKSI', 'Selesai');
            } else {
                $query->whereIn('STATUS_TRANSAKSI', ['Dijadwalkan Kirim', 'Dijadwalkan Ambil'])
                    ->where(function ($q) {
                        $q->whereHas('pengiriman', function ($q) {
                            $q->whereNotNull('WAKTU_KIRIM');
                        })->orWhereHas('pengambilan', function ($q) {
                            $q->whereNotNull('WAKTU_AMBIL');
                        });
                    });
            }

            // Pencarian
            if ($request->has('search')) {
                $search = $request->search;
                $query->whereHas('pembeli', function ($q) use ($search) {
                    $q->where('NAMA_PEMBELI', 'like', "%$search%");
                });
            }

            $this->cancelExpiredTransactions();

            // Fetch data
            $result = $query->with([
                'pembeli',
                'pegawai',
                'detailTransaksi.barang',
                'pengiriman',
                'pengambilan'
            ])->orderBy('WAKTU_PESAN', 'desc')->get();

            return response()->json([
                'message' => 'Pesanan berhasil dimuat',
                'data' => $result
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memuat pesanan',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function show($id)
    {
        // Auto-cancel expired transactions
        $this->cancelExpiredTransactions();
        
        $transaksi = Transaksi::with(['pembeli', 'pegawai', 'detailTransaksi.barang', 'pengiriman', 'pengambilan'])
            ->findOrFail($id);

        return response()->json(['data' => $transaksi]);
    }

   public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_pegawai' => 'required|exists:pegawai,ID_PEGAWAI',
            'id_pembeli' => 'required|exists:pembeli,ID_PEMBELI',
            'items' => 'required|array',
            'items.*.id_barang' => 'required|exists:barang,ID_BARANG',
            'items.*.jumlah' => 'required|integer|min:1',
            'jenis_delivery' => 'required|in:Antar,Ambil',
            'use_points' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pembeli = Pembeli::findOrFail($request->id_pembeli);
        $totalHarga = 0;
        $ongkosKirim = 0;
        $potonganPoin = 0;

        $userCartItems = Cart::where('ID_PEMBELI', $request->id_pembeli)
                            ->pluck('ID_BARANG')
                            ->toArray();

        foreach ($request->items as $item) {
            $barang = Barang::findOrFail($item['id_barang']);
            
            if ($barang->STATUS_BARANG !== 'Tersedia' && !in_array($barang->ID_BARANG, $userCartItems)) {
                return response()->json([
                    'message' => "Item {$barang->NAMA_BARANG} is not available"
                ], 422);
            }
            
            $totalHarga += $barang->HARGA * $item['jumlah'];
            
            if ($barang->STATUS_BARANG !== 'Sold Out') {
                $barang->STATUS_BARANG = 'Sold Out';
                $barang->save();
            }
        }

        if ($request->jenis_delivery === 'Antar') {
            $ongkosKirim = ($totalHarga >= 1500000) ? 0 : 100000;
        }

        if ($request->use_points && $request->points_used > 0) {
            if ($request->points_used % 100 !== 0) {
                return response()->json([
                    'message' => 'Points must be in multiples of 100'
                ], 422);
            }
            
            if ($request->points_used > $pembeli->POIN) {
                return response()->json([
                    'message' => 'Not enough points available'
                ], 422);
            }
            
            $poinDipakai = $request->points_used;
            $potonganPoin = ($poinDipakai / 100) * 10000;
            
            $potonganPoin = min($potonganPoin, $totalHarga + $ongkosKirim);
            
            $pembeli->POIN -= $poinDipakai;
            $pembeli->save();
        }

        $totalAkhir = $totalHarga + $ongkosKirim - $potonganPoin;

        $pointsEarned = floor($totalHarga / 10000);
        if ($totalHarga > 500000) {
            $pointsEarned += floor($pointsEarned * 0.2);
        }

        $month = Carbon::now()->format('m');
        $year = Carbon::now()->format('y');
        $lastTransaction = Transaksi::orderBy('ID_TRANSAKSI', 'desc')->first();
        $lastNumber = $lastTransaction ? substr($lastTransaction->NO_NOTA, -3) : 0;
        $newNumber = str_pad((int)$lastNumber + 1, 3, '0', STR_PAD_LEFT);
        $noNota = $year . '.' . $month . '.' . $newNumber;

        $transaksi = new Transaksi();
        $transaksi->ID_PEGAWAI = $request->id_pegawai;
        $transaksi->ID_PEMBELI = $request->id_pembeli;
        $transaksi->NO_NOTA = $noNota;
        $transaksi->WAKTU_PESAN = Carbon::now();
        $transaksi->TOTAL_HARGA = $totalHarga;
        $transaksi->ONGKOS_KIRIM = $ongkosKirim;
        $transaksi->POTONGAN_POIN = $potonganPoin;
        $transaksi->TOTAL_AKHIR = $totalAkhir;
        $transaksi->POIN_DIDAPAT = $pointsEarned;
        $transaksi->STATUS_TRANSAKSI = 'Belum dibayar';
        $transaksi->JENIS_DELIVERY = $request->jenis_delivery;
        $transaksi->save();

        foreach ($request->items as $item) {
            $detail = new DetailTransaksi();
            $detail->ID_TRANSAKSI = $transaksi->ID_TRANSAKSI;
            $detail->ID_BARANG = $item['id_barang'];
            $detail->JUMLAH = $item['jumlah'];
            $detail->save();
            
            Cart::where('ID_PEMBELI', $request->id_pembeli)
                ->where('ID_BARANG', $item['id_barang'])
                ->delete();
        }

        $pegawai = Pegawai::with('jabatan')->find($request->id_pegawai);

        // ✅ FIXED: Process commissions for each item
        foreach ($request->items as $item) {
            $barang = Barang::find($item['id_barang']);
            
            $detailPenitipan = DetailPenitipan::where('ID_BARANG', $barang->ID_BARANG)->first();
            
            if (!$detailPenitipan) {
                continue;
            }
            
            $penitipan = Penitipan::find($detailPenitipan->ID_PENITIPAN);
            
            if (!$penitipan) {
                continue;
            }

            $hargaBarang = $barang->HARGA;
            $komisiReusemart = 0;
            $komisiHunter = 0;
            $bonusPenitip = 0;

            // ✅ FIXED: Check if this is a hunted item
            $isHunted = $penitipan->PEGAWAI_HUNTER !== null;
            $hunterPegawaiId = $penitipan->PEGAWAI_HUNTER; // Hunter ID from penitipan table
            
            $isPerpanjangan = $penitipan->PERPANJANGAN === 1; 

            if ($isPerpanjangan) {
                $komisiReusemart = $isHunted ? $hargaBarang * 0.25 : $hargaBarang * 0.30;
                $komisiHunter = $isHunted ? $hargaBarang * 0.05 : 0;
            } else {
                $komisiReusemart = $isHunted ? $hargaBarang * 0.15 : $hargaBarang * 0.20;
                $komisiHunter = $isHunted ? $hargaBarang * 0.05 : 0;
            }

            $tanggalMasuk = Carbon::parse($penitipan->TANGGAL_MASUK);
            $tanggalJual = Carbon::now();
            $daysDifference = $tanggalJual->diffInDays($tanggalMasuk);

            if ($daysDifference < 7) {
                $bonusPenitip = $komisiReusemart * 0.10;
                $komisiReusemart -= $bonusPenitip;
            }

            $penitip = $penitipan->penitip; 

            // ✅ FIXED: Create commission record with proper hunter ID
            Komisi::create([
                'JUMLAH_KOMISI_REUSE_MART' => $komisiReusemart,
                'JUMLAH_KOMISI_HUNTER' => $komisiHunter,
                'BONUS_PENITIP' => $bonusPenitip,
                'TANGGAL_KOMISI' => Carbon::now()->toDateString(),
                'ID_PENITIP' => $penitipan->ID_PENITIP,
                'ID_BARANG' => $barang->ID_BARANG,
                'ID_PEGAWAI' => $hunterPegawaiId  // ✅ This will be hunter ID or null
            ]);
            
            $totalKomisi = $komisiReusemart + $komisiHunter;
            $saldoPenitip = $hargaBarang - $totalKomisi + $bonusPenitip;
            $penitip->SALDO += $saldoPenitip;
            $penitip->save();

            // ✅ MARK barang as sold
            $barang->TANGGAL_JUAL = Carbon::now();
            $barang->save();
        }

        return response()->json([
            'message' => 'Transaction created successfully',
            'data' => Transaksi::with('detailTransaksi.barang')->find($transaksi->ID_TRANSAKSI)
        ], 201);
    }

    public function uploadPaymentProof(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'bukti_transfer' => 'required|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaksi = Transaksi::findOrFail($id);

        if ($transaksi->STATUS_TRANSAKSI !== 'Belum dibayar') {
            return response()->json(['message' => 'Transaction is not in unpaid status'], 422);
        }

        // Check if transaction has expired (1 minute)
        $orderTime = Carbon::parse($transaksi->WAKTU_PESAN);
        $now = Carbon::now();
        if ($now->diffInSeconds($orderTime) > 60) {
            $this->cancelTransaction($transaksi);
            return response()->json(['message' => 'Transaction has expired and been cancelled'], 422);
        }

        if ($request->hasFile('bukti_transfer')) {
            $file = $request->file('bukti_transfer');
            $fileName = 'payment_'.$transaksi->NO_NOTA.'_'.time().'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/payment_proofs', $fileName);

            $transaksi->BUKTI_TRANSFER = $fileName;
            $transaksi->STATUS_TRANSAKSI = 'Menunggu Verifikasi';
            $transaksi->save();
        }

        return response()->json([
            'message' => 'Payment proof uploaded successfully',
            'data' => $transaksi
        ]);
    }

    public function verifyPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_valid' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaksi = Transaksi::findOrFail($id);

        if ($transaksi->STATUS_TRANSAKSI !== 'Menunggu Verifikasi') {
            return response()->json(['message' => 'Transaction is not in verification status'], 422);
        }

        if ($request->is_valid) {
            $transaksi->STATUS_VALIDASI_PEMBAYARAN = 'valid';
            $transaksi->STATUS_TRANSAKSI = 'Diproses';
              foreach ($transaksi->detailTransaksi as $detail) {
                app(ReuseMartNotificationService::class)
                    ->sendBarangTerjualNotification($detail->ID_BARANG);
            }
            $transaksi->WAKTU_BAYAR = Carbon::now();
            $transaksi->TANGGAL_VERIFIKASI = Carbon::now();
            
            // Ubah status barang menjadi 'disiapkan'
            foreach ($transaksi->detailTransaksi as $detail) {
                $barang = Barang::find($detail->ID_BARANG);
                if ($barang) {
                    $barang->STATUS_BARANG = 'Sold Out';
                    $barang->save();

                    //notif
                      try {
                        $this->notificationService->sendBarangTerjualNotification($detail->barang->ID_BARANG);
                        \Log::info("✅ Barang terjual notification sent for barang: {$detail->barang->ID_BARANG}");
                    } catch (\Exception $e) {
                        \Log::error("❌ Failed to send barang terjual notification: " . $e->getMessage());
                    }
                }
            }
        } else {
            $transaksi->STATUS_VALIDASI_PEMBAYARAN = 'pembayaran ditolak';
            $transaksi->STATUS_TRANSAKSI = 'pembayaran ditolak';
            
            // Reset items status to Available
            foreach ($transaksi->detailTransaksi as $detail) {
                $barang = Barang::find($detail->ID_BARANG);
                if ($barang) {
                    $barang->STATUS_BARANG = 'Tersedia';
                    $barang->save();
                }
            }
        }

        $transaksi->save();

        return response()->json([
            'message' => $request->is_valid ? 'Payment verified and processed' : 'Payment rejected',
            'data' => $transaksi
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:Diproses,Siap diambil,Sedang dikirim,Sudah diterima,Selesai',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $transaksi = Transaksi::findOrFail($id);

        $validTransition = false;

        // ✅ Perbolehkan dari status Dijadwalkan Kirim / Ambil ke Selesai
        switch ($transaksi->STATUS_TRANSAKSI) {
            case 'Sudah dibayar':
                $validTransition = $request->status === 'Diproses';
                break;
            case 'Diproses':
                $validTransition = in_array($request->status, ['Siap diambil', 'Sedang dikirim']);
                break;
            case 'Siap diambil':
            case 'Sedang dikirim':
            case 'Dijadwalkan Kirim':
            case 'Dijadwalkan Ambil':
                $validTransition = $request->status === 'Selesai';
                break;
            case 'Sudah diterima':
                $validTransition = $request->status === 'Selesai';
                break;
        }

        if (!$validTransition) {
            return response()->json([
                'message' => 'Invalid status transition from ' . $transaksi->STATUS_TRANSAKSI . ' to ' . $request->status
            ], 422);
        }

        $transaksi->STATUS_TRANSAKSI = $request->status;

        if ($request->status === 'Selesai') {
            $pembeli = $transaksi->pembeli;
            $pembeli->POIN += $transaksi->POIN_DIDAPAT;
            $pembeli->save();
        }

        $transaksi->save();

        return response()->json([
            'message' => 'Transaction status updated successfully',
            'data' => $transaksi
        ]);
    }

    public function cancel($id)
    {
        $transaksi = Transaksi::findOrFail($id);

        if (!in_array($transaksi->STATUS_TRANSAKSI, ['Belum dibayar', 'Menunggu Verifikasi'])) {
            return response()->json([
                'message' => 'Only unpaid or unverified transactions can be cancelled'
            ], 422);
        }

        $this->cancelTransaction($transaksi);

        return response()->json([
            'message' => 'Transaction cancelled successfully',
            'data' => $transaksi
        ]);
    }

    public function cancelTransaction($transaksi)
    {
        // First load the transaction with its details if they aren't already loaded
        if (!$transaksi->relationLoaded('detailTransaksi')) {
            $transaksi->load('detailTransaksi');
        }

        $transaksi->STATUS_TRANSAKSI = 'Dibatalkan';
        $transaksi->save();

        // Reset items status to Available
        foreach ($transaksi->detailTransaksi as $detail) {
            $barang = Barang::find($detail->ID_BARANG);

            if ($barang) {
                $barang->STATUS_BARANG = 'Tersedia';
                $barang->save();

                $komisi = Komisi::where('ID_BARANG', $barang->ID_BARANG)->first();

                if ($komisi) {
                    $penitipan = DetailPenitipan::where('ID_BARANG', $barang->ID_BARANG)->first();
                    if ($penitipan) {
                        $penitip = Penitipan::find($penitipan->ID_PENITIPAN)?->penitip;
                        if ($penitip) {
                            $total = $komisi->JUMLAH_KOMISI_REUSE_MART + $komisi->JUMLAH_KOMISI_HUNTER - $komisi->BONUS_PENITIP;
                            $saldoDikurangi = $barang->HARGA - $total;

                            if ($penitip->SALDO >= $saldoDikurangi) {
                                $penitip->SALDO -= $saldoDikurangi;
                                $penitip->save();
                            }
                        }
                    }

                    $komisi->delete();
                }
            }
        }
    }

    public function cancelExpiredTransactions()
    {
        // Find transactions that are in unpaid status and older than 1 minute
        $expiredTransactions = Transaksi::where('STATUS_TRANSAKSI', 'Belum dibayar')
            ->where('WAKTU_PESAN', '<', Carbon::now()->subMinute())
            ->with('detailTransaksi') // Preload the relationship
            ->get();

        foreach ($expiredTransactions as $transaction) {
            $this->cancelTransaction($transaction);
        }
    }

    public function search(Request $request)
    {
        $keyword = $request->input('q');
        if (!$keyword) return response()->json(['message' => 'Parameter q diperlukan.'], 422);

        $results = Transaksi::with(['pembeli'])
            ->where('NO_NOTA', 'like', "%{$keyword}%")
            ->orWhere('STATUS_TRANSAKSI', 'like', "%{$keyword}%")
            ->orWhereHas('pembeli', function($q) use ($keyword) {
                $q->where('NAMA_PEMBELI', 'like', "%{$keyword}%");
            })
            ->paginate(10);

        return response()->json(['data' => $results]);
    }

    public function ongoingForCS()
    {
        $results = Transaksi::with(['pembeli'])
            ->where('STATUS_TRANSAKSI', 'Menunggu Verifikasi')
            ->select('ID_TRANSAKSI', 'ID_PEMBELI', 'BUKTI_TRANSFER')
            ->get()
            ->map(function ($item) {
                return [
                    'id_transaksi' => $item->ID_TRANSAKSI,
                    'nama_pembeli' => $item->pembeli->NAMA_PEMBELI ?? null,
                    'bukti_transfer' => asset('storage/payment_proofs/' . $item->BUKTI_TRANSFER),
                ];
            });

        return response()->json(['data' => $results]);
    }

    public function pendingVerifications()
    {
        $transactions = Transaksi::where('STATUS_TRANSAKSI', 'Menunggu Verifikasi')
            ->whereNotNull('BUKTI_TRANSFER')
            ->with(['pembeli', 'detailTransaksi.barang'])
            ->get();
        
        return response()->json(['data' => $transactions]);
    }

    public function laporanPenjualanBulananPDF()
    {
        try {
            $tahun = now()->year;

            $data = DB::table('transaksi')
                ->select(
                    DB::raw('MONTH(WAKTU_PESAN) as bulan'),
                    DB::raw('COUNT(ID_TRANSAKSI) as jumlah_terjual'),
                    DB::raw('SUM(TOTAL_HARGA) as total_penjualan')
                )
                ->whereYear('WAKTU_PESAN', $tahun)
                ->whereIn('STATUS_TRANSAKSI', ['Selesai', 'Hangus'])
                ->groupBy(DB::raw('MONTH(WAKTU_PESAN)'))
                ->orderBy('bulan')
                ->get();

            // Debug data
            if ($data->isEmpty()) {
                Log::warning("Tidak ada data transaksi untuk tahun $tahun");
            }

            $bulanMap = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            $result = [];
            $total = 0;
            $totalBarang = 0;
            $chartLabels = [];
            $chartData = [];

            for ($i = 1; $i <= 12; $i++) {
                $found = $data->firstWhere('bulan', $i);
                $jumlah = $found ? $found->jumlah_terjual : 0;
                $penjualan = $found ? $found->total_penjualan : 0;

                $result[] = [
                    'bulan' => $bulanMap[$i],
                    'jumlah_terjual' => $jumlah,
                    'penjualan_kotor' => $penjualan,
                ];

                $total += $penjualan;
                $totalBarang += $jumlah;
                $chartLabels[] = $bulanMap[$i];
                $chartData[] = (float)$penjualan; // Cast to float
            }

            // Generate chart dengan config yang benar
            $chartImage = $this->generateChartImage($chartLabels, $chartData, $tahun);

            $pdf = Pdf::loadView('laporan.penjualan_bulanan', [
                'data' => $result,
                'tahun' => $tahun,
                'total' => $total,
                'totalBarang' => $totalBarang,
                'tanggalCetak' => Carbon::now()->translatedFormat('d F Y'),
                'chartImage' => $chartImage
            ]);

            return $pdf->download("laporan-penjualan-bulanan-{$tahun}.pdf");

        } catch (\Exception $e) {
            Log::error("Error generating PDF: " . $e->getMessage());
            return response()->json(['error' => 'Gagal generate PDF: ' . $e->getMessage()], 500);
        }
    }

    private function generateChartImage($labels, $data, $tahun)
    {
        $chartConfig = [
            "type" => "bar",
            "data" => [
                "labels" => $labels,
                "datasets" => [[
                    "label" => "Penjualan Kotor",
                    "data" => $data,
                    "backgroundColor" => "rgba(54, 162, 235, 0.6)"
                ]]
            ],
            "options" => [
                "plugins" => [
                    "title" => [
                        "display" => true,
                        "text" => "Grafik Penjualan Kotor Bulanan - Tahun $tahun"
                    ]
                ]
            ]
        ];

        $chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig));
        
        try {
            $response = Http::timeout(30)->get($chartUrl);
            
            if ($response->successful()) {
                return 'data:image/png;base64,' . base64_encode($response->body());
            } else {
                Log::error("QuickChart HTTP Error: " . $response->status());
                return '';
            }
        } catch (\Exception $e) {
            Log::error("QuickChart Exception: " . $e->getMessage());
            return '';
        }
    }

    public function getLaporanPenjualanBulananJSON()
    {
        $tahun = now()->year;

        $data = DB::table('transaksi')
            ->select(
                DB::raw('MONTH(WAKTU_PESAN) as bulan'),
                DB::raw('COUNT(ID_TRANSAKSI) as jumlah_terjual'),
                DB::raw('SUM(TOTAL_HARGA) as total_penjualan')
            )
            ->whereYear('WAKTU_PESAN', $tahun)
            ->whereIn('STATUS_TRANSAKSI', ['Selesai', 'Hangus'])
            ->groupBy(DB::raw('MONTH(WAKTU_PESAN)'))
            ->orderBy('bulan')
            ->get();

        $bulanMap = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];

        $result = [];
        for ($i = 1; $i <= 12; $i++) {
            $found = $data->firstWhere('bulan', $i);
            $result[] = [
                'bulan' => $bulanMap[$i],
                'jumlah_terjual' => $found->jumlah_terjual ?? 0,
                'penjualan_kotor' => $found->total_penjualan ?? 0,
            ];
        }

        return response()->json([
            'tahun' => $tahun,
            'tanggal_cetak' => now()->translatedFormat('d F Y'),
            'data' => $result,
        ]);
    }

    public function cancelValidTransaction($id)
    {
        try {
            DB::beginTransaction();
            
            $transaksi = Transaksi::with(['detailTransaksi.barang', 'pembeli'])->findOrFail($id);
            
            // Check if transaction can be cancelled (only "Diproses" status)
            if (!in_array($transaksi->STATUS_TRANSAKSI, ['Diproses', 'Disiapkan'])) {
                return response()->json([
                    'message' => 'Transaksi dengan status ini tidak dapat dibatalkan'
                ], 422);
            }
            
            // Check if transaction belongs to authenticated buyer
            $currentBuyer = auth('sanctum')->user();
            if ($transaksi->ID_PEMBELI !== $currentBuyer->ID_PEMBELI) {
                return response()->json([
                    'message' => 'Unauthorized access to this transaction'
                ], 403);
            }
            
            // Update transaction status
            $transaksi->STATUS_TRANSAKSI = 'Dibatalkan Pembeli';
            $transaksi->save();
            
            // Convert transaction total to points (per 10,000 rupiah = 1 point)
            $totalAmount = $transaksi->TOTAL_AKHIR;
            $pointsToAdd = floor($totalAmount / 10000);
            
            // Add points to buyer
            $pembeli = $transaksi->pembeli;
            $pembeli->POIN += $pointsToAdd;
            $pembeli->save();
            
            // Reset items status to Available and handle commissions
            foreach ($transaksi->detailTransaksi as $detail) {
                $barang = Barang::find($detail->ID_BARANG);
                
                if ($barang) {
                    // Reset barang status to available
                    $barang->STATUS_BARANG = 'Tersedia';
                    $barang->TANGGAL_JUAL = null; // Reset sold date
                    $barang->save();
                    
                    // Find and reverse commission if exists
                    $komisi = Komisi::where('ID_BARANG', $barang->ID_BARANG)->first();
                    
                    if ($komisi) {
                        // Find penitipan details
                        $detailPenitipan = DetailPenitipan::where('ID_BARANG', $barang->ID_BARANG)->first();
                        
                        if ($detailPenitipan) {
                            $penitipan = Penitipan::find($detailPenitipan->ID_PENITIPAN);
                            
                            if ($penitipan && $penitipan->penitip) {
                                // Reverse the commission payment
                                $totalCommission = $komisi->JUMLAH_KOMISI_REUSE_MART + $komisi->JUMLAH_KOMISI_HUNTER;
                                $saldoToReverse = $barang->HARGA - $totalCommission + $komisi->BONUS_PENITIP;
                                
                                // Subtract the commission amount from penitip's balance
                                $penitip = $penitipan->penitip;
                                if ($penitip->SALDO >= $saldoToReverse) {
                                    $penitip->SALDO -= $saldoToReverse;
                                    $penitip->save();
                                }
                            }
                        }
                        
                        // Delete the commission record
                        $komisi->delete();
                    }
                }
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Transaksi berhasil dibatalkan',
                'data' => [
                    'transaction_id' => $transaksi->ID_TRANSAKSI,
                    'total_amount' => $totalAmount,
                    'points_added' => $pointsToAdd,
                    'new_total_points' => $pembeli->POIN,
                    'status' => $transaksi->STATUS_TRANSAKSI
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error cancelling valid transaction: ' . $e->getMessage(), [
                'transaction_id' => $id,
                'user_id' => auth('sanctum')->id(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Gagal membatalkan transaksi: ' . $e->getMessage()
            ], 500);
        }
    }
}