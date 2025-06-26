<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Penitipan;
use Carbon\Carbon;

class ExpireBarangPenitipan extends Command
{
    protected $signature = 'barang:expire';
    protected $description = 'Ubah status barang menjadi Kadaluarsa jika lewat dari tanggal kadaluarsa';

    public function handle()
    {
        $today = Carbon::today();
        $countDonasi = 0;
        $countKadaluarsa = 0;


        $expiredPenitipan = Penitipan::with('detailPenitipan.barang')
            ->where('TANGGAL_KADALUARSA', '<', $today)
            ->where('STATUS_PENITIPAN', true)
            ->get();

        foreach ($expiredPenitipan as $penitipan) {
            foreach ($penitipan->detailPenitipan as $detail) {
                $barang = $detail->barang;
                if ($barang && $barang->STATUS_BARANG === 'Tersedia') {
                    $barang->STATUS_BARANG = 'Kadaluarsa';
                    $barang->save();
                    $countKadaluarsa++;
                }
            }
        }

        $batasAmbilPenitipan = Penitipan::with('detailPenitipan.barang')
            ->where('TANGGAL_BATAS_AMBIL', '<', $today)
            ->where('STATUS_PENITIPAN', true)
            ->get();

        foreach ($batasAmbilPenitipan as $penitipan) {
            foreach ($penitipan->detailPenitipan as $detail) {
                $barang = $detail->barang;
                if ($barang && $barang->STATUS_BARANG !== 'Barang untuk Donasi') {
                    $barang->STATUS_BARANG = 'Barang untuk Donasi';
                    $barang->save();
                    $countDonasi++;
                }
            }
        }

        $this->info("{$countKadaluarsa} barang berhasil ditandai sebagai Kadaluarsa.");
        $this->info("{$countDonasi} barang berhasil ditandai sebagai Barang untuk Donasi.");
    }
}
