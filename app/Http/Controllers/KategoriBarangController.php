<?php

namespace App\Http\Controllers;

use App\Models\KategoriBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class KategoriBarangController extends Controller
{
    public function index()
    {
        $kategoris = KategoriBarang::all();
        return response()->json(['data' => $kategoris]);
    }

    public function show($id)
    {
        $kategori = KategoriBarang::findOrFail($id);
        return response()->json(['data' => $kategori]);
    }

    public function items($id)
    {
        $barang = \App\Models\Barang::where('ID_KATEGORI', $id)->with('penitip')->get();

        return response()->json(['data' => $barang]);
    }

    public function getKategori()
    {
        $tahun = now()->year;

        $data = DB::table('detail_transaksi as dt')
            ->join('barang as b', 'dt.ID_BARANG', '=', 'b.ID_BARANG')
            ->join('kategori_barang as k', 'b.ID_KATEGORI', '=', 'k.ID_KATEGORI')
            ->join('transaksi as t', 'dt.ID_TRANSAKSI', '=', 't.ID_TRANSAKSI')
            ->whereYear('t.WAKTU_PESAN', $tahun)
            ->selectRaw("
                k.NAMA_KATEGORI as kategori,
                SUM(CASE WHEN t.STATUS_TRANSAKSI IN ('Selesai', 'Hangus', 'Dijadwalkan Kirim', 'Dijadwalkan Ambil', 'Dalam Perjalanan') THEN 1 ELSE 0 END) as terjual,
                SUM(CASE WHEN t.STATUS_TRANSAKSI IN ('Pembayaran Ditolak', 'Dibatalkan') THEN 1 ELSE 0 END) as gagal
            ")
            ->groupBy('k.NAMA_KATEGORI')
            ->get();

        return response()->json(['data' => $data]);
    }


    public function exportLaporanKategoriTahunan()
    {
        $tahun = now()->year;
        $tanggalCetak = now()->translatedFormat('d F Y');

        // Ambil data penjualan per kategori
        $kategoriData = DB::table('kategori_barang as k')
            ->leftJoin('barang as b', 'k.ID_KATEGORI', '=', 'b.ID_KATEGORI')
            ->leftJoin('detail_transaksi as dt', 'b.ID_BARANG', '=', 'dt.ID_BARANG')
            ->leftJoin('transaksi as t', 'dt.ID_TRANSAKSI', '=', 't.ID_TRANSAKSI')
            ->select(
                'k.NAMA_KATEGORI as kategori',
                DB::raw("SUM(CASE WHEN t.STATUS_TRANSAKSI IN ('Selesai', 'Hangus', 'Dijadwalkan Kirim', 'Dijadwalkan Ambil', 'Dalam Perjalanan') THEN 1 ELSE 0 END) as terjual"),
                DB::raw("SUM(CASE WHEN t.STATUS_TRANSAKSI IN ('pembayaran ditolak', 'Dibatalkan') THEN 1 ELSE 0 END) as gagal")
            )
            ->whereYear('t.WAKTU_PESAN', $tahun)
            ->groupBy('k.NAMA_KATEGORI')
            ->orderBy('k.NAMA_KATEGORI')
            ->get();

        $data = $kategoriData->map(function ($item) {
            return [
                'kategori' => $item->kategori,
                'terjual' => (int) $item->terjual,
                'gagal' => (int) $item->gagal,
            ];
        })->toArray();

        return Pdf::loadView('laporan.kategori', compact('data', 'tahun', 'tanggalCetak'))
            ->download("laporan-per-kategori-{$tahun}.pdf");
    }
}