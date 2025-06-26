<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaksi;
use App\Models\Komisi;
use App\Models\Pegawai;
use Carbon\Carbon;

class TransaksiHangus extends Command
{
    protected $signature = 'transaksi:cek-hangus';
    protected $description = 'Ubah status transaksi menjadi Hangus jika sudah lebih dari 2 hari dari jadwal ambil, dan proses komisi';

    public function handle()
    {
        $today = Carbon::today();
        $count = 0;

        $transaksiList = Transaksi::with([
            'detailTransaksi.barang.penitip.detailPenitipan.penitipan',
        ])
        ->where('STATUS_TRANSAKSI', 'Dijadwalkan Ambil')
        ->whereDate('TANGGAL_DIAMBIL', '<', $today->copy()->subDays(2))
        ->get();

        foreach ($transaksiList as $transaksi) {
            foreach ($transaksi->detailTransaksi as $detail) {
                $barang = $detail->barang;

                if (!$barang || !$barang->penitip) continue;

                $penitip = $barang->penitip;

                $detailPenitipan = $penitip->detailPenitipan
                    ->firstWhere('ID_BARANG', $barang->ID_BARANG);

                if (!$detailPenitipan || !$detailPenitipan->penitipan) continue;

                $penitipan = $detailPenitipan->penitipan;
                $pegawaiHunterId = $penitipan->PEGAWAI_HUNTER;

                $isHunter = false;
                if ($pegawaiHunterId) {
                    $pegawaiHunter = Pegawai::with('jabatan')->find($pegawaiHunterId);
                    $isHunter = $pegawaiHunter && strtolower($pegawaiHunter->jabatan->NAMA_JABATAN ?? '') === 'hunter';
                }

                $komisiSudahAda = Komisi::where('ID_BARANG', $barang->ID_BARANG)
                    ->where('ID_PEGAWAI', $pegawaiHunterId)
                    ->exists();

                if ($komisiSudahAda) continue;

                $hargaBarang = $barang->HARGA;
                $komisiReusemart = 0;
                $komisiHunter = 0;
                $bonusPenitip = 0;

                $isPerpanjangan = $penitipan->PERPANJANGAN == 1;

                if ($isPerpanjangan) {
                    $komisiReusemart = $isHunter ? $hargaBarang * 0.25 : $hargaBarang * 0.30;
                    $komisiHunter = $isHunter ? $hargaBarang * 0.05 : 0;
                } else {
                    $komisiReusemart = $isHunter ? $hargaBarang * 0.15 : $hargaBarang * 0.20;
                    $komisiHunter = $isHunter ? $hargaBarang * 0.05 : 0;
                }

                $tanggalMasuk = Carbon::parse($penitipan->TANGGAL_MASUK);
                $daysDifference = Carbon::now()->diffInDays($tanggalMasuk);

                if ($daysDifference < 7) {
                    $bonusPenitip = $komisiReusemart * 0.10;
                    $komisiReusemart -= $bonusPenitip;
                }

                Komisi::create([
                    'JUMLAH_KOMISI_REUSE_MART' => $komisiReusemart,
                    'JUMLAH_KOMISI_HUNTER' => $komisiHunter,
                    'BONUS_PENITIP' => $bonusPenitip,
                    'TANGGAL_KOMISI' => now()->toDateString(),
                    'ID_PENITIP' => $penitip->ID_PENITIP,
                    'ID_BARANG' => $barang->ID_BARANG,
                    'ID_PEGAWAI' => $pegawaiHunterId, 
                ]);

                $totalKomisi = $komisiReusemart + $komisiHunter;
                $saldoPenitip = $hargaBarang - $totalKomisi + $bonusPenitip;
                $penitip->SALDO += $saldoPenitip;
                $penitip->save();

                $barang->STATUS_BARANG = 'Barang untuk Donasi';
                $barang->save();
            }

            $transaksi->STATUS_TRANSAKSI = 'Hangus';
            $transaksi->save();
            $count++;
        }

        $this->info("{$count} transaksi diubah menjadi 'Hangus' dan komisi diproses.");
    }
}
