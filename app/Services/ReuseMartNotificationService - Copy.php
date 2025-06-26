<?php
// app/Services/ReuseMartNotificationService.php
namespace App\Services;

use App\Models\Barang;
use App\Models\Penitipan;
use App\Models\Transaksi;
use App\Models\Donasi;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ReuseMartNotificationService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Notifikasi masa penitipan hampir habis (H-3 dan hari H)
     * Requirement 135: H-3 dan hari H
     */
    public function sendMasaPenitipanNotification()
    {
        try {
            $today = Carbon::today();
            $threeDaysFromNow = $today->copy()->addDays(3);

            // Get penitipan yang akan expired H-3 dan hari H
            $penitipanList = Penitipan::with(['penitip', 'detailPenitipan.barang'])
                ->where(function($query) use ($today, $threeDaysFromNow) {
                    $query->whereDate('TANGGAL_KADALUARSA', $today) // Hari H
                          ->orWhereDate('TANGGAL_KADALUARSA', $threeDaysFromNow); // H-3
                })
                ->where('STATUS_PENITIPAN', true) // masih aktif
                ->get();

            foreach ($penitipanList as $penitipan) {
                if (!$penitipan->penitip) continue;

                $daysDiff = $today->diffInDays($penitipan->TANGGAL_KADALUARSA, false);
                $barangCount = $penitipan->detailPenitipan->count();
                
                if ($daysDiff == 0) {
                    // Hari H - masa penitipan berakhir hari ini
                    $title = "⚠️ Masa Penitipan Berakhir Hari Ini!";
                    $message = "Masa penitipan {$barangCount} barang Anda berakhir hari ini. Segera ambil barang atau perpanjang masa penitipan sebelum didonasikan.";
                } elseif ($daysDiff == 3) {
                    // H-3 - masa penitipan berakhir 3 hari lagi
                    $title = "🔔 Masa Penitipan Akan Berakhir";
                    $message = "Masa penitipan {$barangCount} barang Anda akan berakhir dalam 3 hari. Jangan lupa untuk mengambil atau memperpanjang masa penitipan.";
                } else {
                    continue;
                }

                $data = [
                    'type' => 'masa_penitipan',
                    'penitipan_id' => $penitipan->ID_PENITIPAN,
                    'tanggal_kadaluarsa' => $penitipan->TANGGAL_KADALUARSA->format('Y-m-d'),
                    'days_remaining' => $daysDiff,
                    'barang_count' => $barangCount
                ];

                $this->notificationService->sendNotification(
                    'penitip',
                    $penitipan->penitip->ID_PENITIP,
                    'masa_penitipan',
                    $title,
                    $message,
                    $data
                );

                Log::info("Masa penitipan notification sent to penitip ID: {$penitipan->penitip->ID_PENITIP}");
            }

        } catch (\Exception $e) {
            Log::error("Failed to send masa penitipan notifications: " . $e->getMessage());
        }
    }

    /**
     * Notifikasi peringatan terakhir sebelum barang didonasikan
     * Requirement 136: Peringatan terakhir sebelum donasi
     */
    public function sendDonationWarningNotification()
    {
        try {
            $today = Carbon::today();

            // Get barang yang masa penitipannya sudah lewat dan dalam masa tunggu donasi (7 hari)
            $barangList = Barang::with(['penitip', 'detailPenitipan.penitipan'])
                ->whereHas('detailPenitipan.penitipan', function($query) use ($today) {
                    $query->where('TANGGAL_KADALUARSA', '<', $today)
                          ->where('TANGGAL_BATAS_AMBIL', '>=', $today)
                          ->where('STATUS_PENITIPAN', true);
                })
                ->where('STATUS_BARANG', 'Tersedia')
                ->get();

            foreach ($barangList as $barang) {
                if (!$barang->penitip) continue;

                $penitipan = $barang->detailPenitipan->first()?->penitipan;
                if (!$penitipan) continue;

                $daysUntilDonation = $today->diffInDays($penitipan->TANGGAL_BATAS_AMBIL, false);

                $title = "🚨 Peringatan Terakhir - Barang Akan Didonasikan!";
                $message = "Barang '{$barang->NAMA_BARANG}' akan didonasikan dalam {$daysUntilDonation} hari. Ini adalah peringatan terakhir untuk mengambil barang Anda.";

                $data = [
                    'type' => 'donation_warning',
                    'barang_id' => $barang->ID_BARANG,
                    'penitipan_id' => $penitipan->ID_PENITIPAN,
                    'days_remaining' => $daysUntilDonation,
                    'barang_name' => $barang->NAMA_BARANG
                ];

                $this->notificationService->sendNotification(
                    'penitip',
                    $barang->penitip->ID_PENITIP,
                    'donation_warning',
                    $title,
                    $message,
                    $data
                );
            }

        } catch (\Exception $e) {
            Log::error("Failed to send donation warning notifications: " . $e->getMessage());
        }
    }

    /**
     * Notifikasi barang terjual ke penitip
     * Requirement 138: Notifikasi barang terjual ke penitip
     */
    public function sendBarangTerjualNotification($barangId)
    {
        try {
            Log::info("Sending barang terjual notification for barang ID: {$barangId}");
            
            $barang = Barang::with('penitip')->find($barangId);
            
            if (!$barang) {
                Log::warning("Barang not found", ['barang_id' => $barangId]);
                return false;
            }

            if (!$barang->penitip) {
                Log::warning("Penitip not found for barang", ['barang_id' => $barangId]);
                return false;
            }

            Log::info("Found barang and penitip", [
                'barang_name' => $barang->NAMA_BARANG,
                'penitip_name' => $barang->penitip->NAMA_PENITIP,
                'penitip_id' => $barang->penitip->ID_PENITIP
            ]);

            $title = "🎉 Barang Anda Terjual!";
            $message = "Selamat! Barang '{$barang->NAMA_BARANG}' dengan harga Rp " . number_format($barang->HARGA, 0, ',', '.') . " telah terjual. Saldo Anda akan segera diperbarui.";

            $data = [
                'type' => 'barang_terjual',
                'barang_id' => $barang->ID_BARANG,
                'barang_name' => $barang->NAMA_BARANG,
                'harga' => $barang->HARGA
            ];

            $result = $this->notificationService->sendNotification(
                'penitip',
                $barang->penitip->ID_PENITIP,
                'barang_terjual',
                $title,
                $message,
                $data
            );

            Log::info("Barang terjual notification result", [
                'success' => $result,
                'barang_id' => $barangId,
                'penitip_id' => $barang->penitip->ID_PENITIP
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to send barang terjual notification", [
                'barang_id' => $barangId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Notifikasi barang sudah didonasikan
     * Requirement 139: Notifikasi ke penitip saat barang didonasikan
     */
    public function sendBarangDidonasikanNotification($donasiId)
    {
        try {
            $donasi = Donasi::with(['barang.penitip', 'requestDonasi.organisasi'])->find($donasiId);
            
            if (!$donasi || !$donasi->barang || !$donasi->barang->penitip) {
                return false;
            }

            $barang = $donasi->barang;
            $organisasi = $donasi->requestDonasi?->organisasi;
            $namaOrganisasi = $organisasi ? $organisasi->NAMA_ORGANISASI : 'organisasi sosial';

            $title = "💚 Barang Anda Telah Didonasikan";
            $message = "Barang '{$barang->NAMA_BARANG}' telah didonasikan ke {$namaOrganisasi}. Terima kasih atas kontribusi Anda untuk kebaikan!";

            $data = [
                'type' => 'barang_didonasikan',
                'barang_id' => $barang->ID_BARANG,
                'donasi_id' => $donasi->ID_DONASI,
                'barang_name' => $barang->NAMA_BARANG,
                'organisasi_name' => $namaOrganisasi,
                'tanggal_donasi' => $donasi->TANGGAL_DONASI
            ];

            return $this->notificationService->sendNotification(
                'penitip',
                $barang->penitip->ID_PENITIP,
                'barang_didonasikan',
                $title,
                $message,
                $data
            );

        } catch (\Exception $e) {
            Log::error("Failed to send barang didonasikan notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notifikasi status pengiriman
     * Requirement 137: Notifikasi status pengiriman
     */
    public function sendStatusPengirimanNotification($transaksiId, $status)
    {
        try {
            $transaksi = Transaksi::with(['pembeli', 'detailTransaksi.barang', 'pengiriman', 'pengambilan'])
                                  ->find($transaksiId);
            
            if (!$transaksi || !$transaksi->pembeli) {
                return false;
            }

            $barangNames = $transaksi->detailTransaksi->pluck('barang.NAMA_BARANG')->take(2)->implode(', ');
            $totalBarang = $transaksi->detailTransaksi->count();
            
            if ($totalBarang > 2) {
                $barangNames .= " dan " . ($totalBarang - 2) . " barang lainnya";
            }

            switch (strtolower($status)) {
                case 'diproses':
                case 'sedang disiapkan':
                    $title = "📦 Pesanan Sedang Disiapkan";
                    $message = "Pesanan Anda ({$barangNames}) sedang disiapkan oleh tim gudang kami.";
                    break;
                case 'siap diambil':
                    $title = "✅ Pesanan Siap Diambil";
                    $message = "Pesanan Anda ({$barangNames}) sudah siap diambil di gudang ReuseMart.";
                    break;
                case 'sedang diantar':
                case 'dikirim':
                    $title = "🚚 Pesanan Sedang Diantar";
                    $message = "Pesanan Anda ({$barangNames}) sedang dalam perjalanan menuju alamat tujuan.";
                    break;
                case 'selesai':
                case 'diterima':
                    $title = "🎉 Pesanan Telah Diterima";
                    $message = "Pesanan Anda ({$barangNames}) telah berhasil diterima. Terima kasih telah berbelanja di ReuseMart!";
                    break;
                default:
                    $title = "📱 Update Status Pesanan";
                    $message = "Status pesanan Anda ({$barangNames}) telah diperbarui menjadi: {$status}";
            }

            $data = [
                'type' => 'status_pengiriman',
                'transaksi_id' => $transaksi->ID_TRANSAKSI,
                'status' => $status,
                'no_nota' => $transaksi->NO_NOTA,
                'barang_count' => $totalBarang
            ];

            return $this->notificationService->sendNotification(
                'pembeli',
                $transaksi->pembeli->ID_PEMBELI,
                'status_pengiriman',
                $title,
                $message,
                $data
            );

        } catch (\Exception $e) {
            Log::error("Failed to send status pengiriman notification: " . $e->getMessage());
            return false;
        }
    }
}