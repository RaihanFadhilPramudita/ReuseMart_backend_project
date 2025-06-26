<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Barang;
use Carbon\Carbon;

class UpdateGaransiBarang extends Command
{
    protected $signature = 'barang:update-garansi';
    protected $description = 'Update status garansi barang berdasarkan tanggal';

    public function handle()
    {
        $barangs = Barang::whereNotNull('TANGGAL_GARANSI')->get();
        $updated = 0;

        foreach ($barangs as $barang) {
            $newStatus = now()->lessThan($barang->TANGGAL_GARANSI);
            if ($barang->STATUS_GARANSI !== $newStatus) {
                $barang->STATUS_GARANSI = $newStatus;
                $barang->save();
                $updated++;
            }
        }

        $this->info("Status garansi diperbarui untuk {$updated} barang.");
    }

}
