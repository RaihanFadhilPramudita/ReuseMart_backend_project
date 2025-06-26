<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Penitip;
use App\Models\Komisi;

class HitungBonusTopSeller extends Command
{
    protected $signature = 'hitung:topseller';
    protected $description = 'Hitung dan beri badge + bonus untuk Top Seller bulanan';

    public function handle()
    {
        $this->info('Mulai proses perhitungan Top Seller...');

        $bulanLaluCarbon = Carbon::now()->subMonth();
        $bulanLalu = $bulanLaluCarbon->month;
        $tahunLalu = $bulanLaluCarbon->year;
        $namaBulan = $bulanLaluCarbon->translatedFormat('F');

        $penjualan = DB::table('transaksi')
            ->join('detail_transaksi', 'transaksi.ID_TRANSAKSI', '=', 'detail_transaksi.ID_TRANSAKSI')
            ->join('barang', 'detail_transaksi.ID_BARANG', '=', 'barang.ID_BARANG')
            ->join('penitip', 'barang.ID_PENITIP', '=', 'penitip.ID_PENITIP')
            ->select('barang.ID_PENITIP', DB::raw('SUM(barang.HARGA) as total_penjualan'))
            ->where('transaksi.STATUS_TRANSAKSI', 'Selesai')
            ->whereMonth('transaksi.WAKTU_PESAN', $bulanLalu)
            ->whereYear('transaksi.WAKTU_PESAN', $tahunLalu)
            ->groupBy('barang.ID_PENITIP')
            ->orderByDesc('total_penjualan')
            ->get();

        if ($penjualan->isEmpty()) {
            $this->info('Tidak ada transaksi selesai bulan lalu.');
            return;
        }

        $topValue = $penjualan->first()->total_penjualan;
        $topCandidates = $penjualan->where('total_penjualan', $topValue);

        if ($topCandidates->count() > 1) {
            $this->warn('Terdapat lebih dari 1 penitip dengan total penjualan sama. Menggunakan yang pertama secara urut.');
        }

        $topPenitipData = $topCandidates->sortBy('ID_PENITIP')->first();
        
        Penitip::where('BADGE', 'like', 'Top Seller%')
            ->where('ID_PENITIP', '!=', $topPenitipData->ID_PENITIP)
            ->update(['BADGE' => 'Seller']);

        $penitip = Penitip::find($topPenitipData->ID_PENITIP);
        $bonus = $topPenitipData->total_penjualan * 0.01;

        $badgeText = 'Top Seller ' . $namaBulan;
        $penitip->BADGE = $badgeText;
        $penitip->SALDO += $bonus;
        $penitip->save();

        Komisi::create([
            'JUMLAH_KOMISI_REUSE_MART' => 0,
            'JUMLAH_KOMISI_HUNTER' => 0,
            'BONUS_PENITIP' => $bonus,
            'TANGGAL_KOMISI' => now()->toDateString(),
            'ID_PENITIP' => $penitip->ID_PENITIP,
            'ID_BARANG' => null,
            'ID_PEGAWAI' => null,
        ]);

        $this->info("✅ {$badgeText}: {$penitip->NAMA_PENITIP} (ID: {$penitip->ID_PENITIP})");
        $this->info("🎁 Bonus diberikan: Rp " . number_format($bonus, 0, ',', '.'));
    }
}
