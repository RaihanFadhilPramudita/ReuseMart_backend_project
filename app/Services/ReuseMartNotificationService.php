<?php
// 🔥 ENHANCED ReuseMartNotificationService.php - REAL INTEGRATION
namespace App\Services;

use App\Models\Barang;
use App\Models\Penitipan;
use App\Models\Transaksi;
use App\Models\Donasi;
use App\Models\Pengiriman;
use App\Models\Pengambilan;
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
     * 🔥 REQUIREMENT 123 & 124: Notifikasi masa penitipan (H-3 dan hari H)
     * Sesuai dengan SOAL UTAMA: "H-3 dan hari H"
     */
    public function sendMasaPenitipanNotification()
    {
        try {
            $today = Carbon::today();
            $threeDaysFromNow = $today->copy()->addDays(3);

            Log::info("🔔 Checking masa penitipan notifications for dates:", [
                'today' => $today->format('Y-m-d'),
                'h_minus_3' => $threeDaysFromNow->format('Y-m-d')
            ]);

            // ✅ Get penitipan yang akan expired H-3 dan hari H
            $penitipanList = Penitipan::with(['penitip', 'detailPenitipan.barang'])
                ->where(function($query) use ($today, $threeDaysFromNow) {
                    $query->whereDate('TANGGAL_KADALUARSA', $today) // Hari H
                          ->orWhereDate('TANGGAL_KADALUARSA', $threeDaysFromNow); // H-3
                })
                ->where('STATUS_PENITIPAN', true) // masih aktif
                ->get();

            Log::info("Found penitipan needing notifications: " . $penitipanList->count());

            foreach ($penitipanList as $penitipan) {
                if (!$penitipan->penitip) {
                    Log::warning("Penitipan {$penitipan->ID_PENITIPAN} has no penitip");
                    continue;
                }

                $daysDiff = $today->diffInDays($penitipan->TANGGAL_KADALUARSA, false);
                $barangCount = $penitipan->detailPenitipan->count();
                
                if ($daysDiff == 0) {
                    // 🔥 REQUIREMENT 124: Hari H - masa penitipan berakhir hari ini
                    $title = "⚠️ Masa Penitipan Berakhir Hari Ini!";
                    $message = "Masa penitipan {$barangCount} barang Anda berakhir hari ini. Segera ambil barang atau perpanjang masa penitipan sebelum didonasikan.";
                    $notificationType = 'masa_penitipan_hari_h';
                } elseif ($daysDiff == 3) {
                    // 🔥 REQUIREMENT 123: H-3 - masa penitipan berakhir 3 hari lagi
                    $title = "🔔 Masa Penitipan Akan Berakhir";
                    $message = "Masa penitipan {$barangCount} barang Anda akan berakhir dalam 3 hari. Jangan lupa untuk mengambil atau memperpanjang masa penitipan.";
                    $notificationType = 'masa_penitipan_h_minus_3';
                } else {
                    continue;
                }

                $data = [
                    'type' => $notificationType,
                    'penitipan_id' => $penitipan->ID_PENITIPAN,
                    'tanggal_kadaluarsa' => $penitipan->TANGGAL_KADALUARSA->format('Y-m-d'),
                    'days_remaining' => $daysDiff,
                    'barang_count' => $barangCount
                ];

                $success = $this->notificationService->sendNotification(
                    'penitip',
                    $penitipan->penitip->ID_PENITIP,
                    $notificationType,
                    $title,
                    $message,
                    $data
                );

                if ($success) {
                    Log::info("✅ Masa penitipan notification sent", [
                        'type' => $notificationType,
                        'penitip_id' => $penitipan->penitip->ID_PENITIP,
                        'penitip_name' => $penitipan->penitip->NAMA_PENITIP
                    ]);
                } else {
                    Log::error("❌ Failed to send masa penitipan notification", [
                        'penitip_id' => $penitipan->penitip->ID_PENITIP
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("❌ Failed to send masa penitipan notifications: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 🔥 REQUIREMENT 125: Notifikasi barang terjual ke penitip
     * Sesuai dengan SOAL UTAMA: "ketika bukti pembayaran valid"
     */
    public function sendBarangTerjualNotification($barangId)
    {
        try {
            Log::info("🔔 Sending barang terjual notification for barang ID: {$barangId}");
            
            $barang = Barang::with('penitip')->find($barangId);
            
            if (!$barang) {
                Log::warning("Barang not found", ['barang_id' => $barangId]);
                return false;
            }

            if (!$barang->penitip) {
                Log::warning("Penitip not found for barang", ['barang_id' => $barangId]);
                return false;
            }

            $title = "🎉 Barang Anda Terjual!";
            $message = "Selamat! Barang '{$barang->NAMA_BARANG}' dengan harga Rp " . number_format($barang->HARGA, 0, ',', '.') . " telah terjual. Saldo Anda akan diperbarui setelah transaksi selesai.";

            $data = [
                'type' => 'barang_terjual',
                'barang_id' => $barang->ID_BARANG,
                'barang_name' => $barang->NAMA_BARANG,
                'harga' => $barang->HARGA
            ];

            $success = $this->notificationService->sendNotification(
                'penitip',
                $barang->penitip->ID_PENITIP,
                'barang_terjual',
                $title,
                $message,
                $data
            );

            if ($success) {
                Log::info("✅ Barang terjual notification sent successfully", [
                    'barang_id' => $barangId,
                    'penitip_name' => $barang->penitip->NAMA_PENITIP
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error("❌ Failed to send barang terjual notification", [
                'barang_id' => $barangId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 🔥 REQUIREMENT 126 & 127: Notifikasi jadwal pengiriman dan pengambilan
     * Sesuai dengan SOAL UTAMA: muncul di hp Penitip, Pembeli dan Kurir
     */
    public function sendJadwalNotification($transaksiId, $jenisDelivery, $jadwal)
    {
        try {
            $transaksi = Transaksi::with(['pembeli', 'detailTransaksi.barang.penitip'])->find($transaksiId);
            
            if (!$transaksi) {
                Log::error("Transaksi not found", ['transaksi_id' => $transaksiId]);
                return false;
            }

            $barangNames = $transaksi->detailTransaksi->pluck('barang.NAMA_BARANG')->take(2)->implode(', ');
            $totalBarang = $transaksi->detailTransaksi->count();
            if ($totalBarang > 2) {
                $barangNames .= " dan " . ($totalBarang - 2) . " barang lainnya";
            }

            if ($jenisDelivery === 'Antar') {
                // 🔥 REQUIREMENT 126: Notifikasi jadwal pengiriman
                $title = "📦 Jadwal Pengiriman Ditentukan";
                $message = "Pesanan Anda ({$barangNames}) akan dikirim pada {$jadwal}. Pastikan ada yang menerima di alamat tujuan.";
                $notificationType = 'jadwal_pengiriman';
            } else {
                // 🔥 REQUIREMENT 127: Notifikasi jadwal pengambilan
                $title = "📍 Jadwal Pengambilan Ditentukan";
                $message = "Pesanan Anda ({$barangNames}) dapat diambil pada {$jadwal} di gudang ReuseMart (jam 8:00-20:00).";
                $notificationType = 'jadwal_pengambilan';
            }

            $data = [
                'type' => $notificationType,
                'transaksi_id' => $transaksi->ID_TRANSAKSI,
                'no_nota' => $transaksi->NO_NOTA,
                'jadwal' => $jadwal,
                'jenis_delivery' => $jenisDelivery
            ];

            $results = [];
            
            // ✅ Kirim ke Pembeli
            if ($transaksi->pembeli) {
                $results['pembeli'] = $this->notificationService->sendNotification(
                    'pembeli',
                    $transaksi->pembeli->ID_PEMBELI,
                    $notificationType,
                    $title,
                    $message,
                    $data
                );
                Log::info("Jadwal notification sent to pembeli: " . ($results['pembeli'] ? 'success' : 'failed'));
            }

            // ✅ Kirim ke Penitip (semua penitip yang barangnya dibeli)
            $penitipIds = $transaksi->detailTransaksi->pluck('barang.penitip.ID_PENITIP')->filter()->unique();
            foreach ($penitipIds as $penitipId) {
                $results['penitip_' . $penitipId] = $this->notificationService->sendNotification(
                    'penitip',
                    $penitipId,
                    $notificationType,
                    $title,
                    "Barang titipan Anda dalam pesanan {$transaksi->NO_NOTA} telah dijadwalkan untuk {$jadwal}.",
                    $data
                );
                Log::info("Jadwal notification sent to penitip {$penitipId}: " . ($results['penitip_' . $penitipId] ? 'success' : 'failed'));
            }

            // ✅ Kirim ke Kurir (jika pengiriman)
            if ($jenisDelivery === 'Antar') {
                // TODO: Implementasi ketika ada data kurir yang assign
                Log::info("TODO: Send jadwal notification to kurir for transaksi {$transaksiId}");
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("❌ Failed to send jadwal notification", [
                'transaksi_id' => $transaksiId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 🔥 REQUIREMENT 128, 129, 130: Notifikasi status pengiriman
     * 128: ketika barang dikirim oleh kurir
     * 129: ketika barang sudah sampai  
     * 130: ketika barang sudah diambil
     */
    public function sendStatusPengirimanNotification($transaksiId, $status)
    {
        try {
            $transaksi = Transaksi::with(['pembeli', 'detailTransaksi.barang.penitip', 'pengiriman', 'pengambilan'])
                                  ->find($transaksiId);
            
            if (!$transaksi) {
                Log::error("Transaksi not found for status notification", ['transaksi_id' => $transaksiId]);
                return false;
            }

            $barangNames = $transaksi->detailTransaksi->pluck('barang.NAMA_BARANG')->take(2)->implode(', ');
            $totalBarang = $transaksi->detailTransaksi->count();
            if ($totalBarang > 2) {
                $barangNames .= " dan " . ($totalBarang - 2) . " barang lainnya";
            }

            // ✅ Tentukan title, message, dan type berdasarkan status
            switch (strtolower($status)) {
                case 'sedang dikirim':
                case 'dikirim':
                    // 🔥 REQUIREMENT 128
                    $title = "🚚 Pesanan Sedang Dikirim";
                    $message = "Pesanan Anda ({$barangNames}) sedang dalam perjalanan menuju alamat tujuan.";
                    $notificationType = 'barang_dikirim';
                    break;
                    
                case 'sudah sampai':
                case 'sampai':
                    // 🔥 REQUIREMENT 129
                    $title = "📍 Pesanan Telah Sampai";
                    $message = "Pesanan Anda ({$barangNames}) telah sampai di tujuan. Terima kasih telah berbelanja di ReuseMart!";
                    $notificationType = 'barang_sampai';
                    break;
                    
                case 'sudah diambil':
                case 'diambil':
                case 'selesai':
                    // 🔥 REQUIREMENT 130
                    $title = "✅ Pesanan Telah Diterima";
                    $message = "Pesanan Anda ({$barangNames}) telah berhasil diterima. Terima kasih telah berbelanja di ReuseMart!";
                    $notificationType = 'barang_diambil';
                    break;
                    
                default:
                    $title = "📱 Update Status Pesanan";
                    $message = "Status pesanan Anda ({$barangNames}) telah diperbarui menjadi: {$status}";
                    $notificationType = 'status_update';
            }

            $data = [
                'type' => $notificationType,
                'transaksi_id' => $transaksi->ID_TRANSAKSI,
                'status' => $status,
                'no_nota' => $transaksi->NO_NOTA,
                'barang_count' => $totalBarang
            ];

            $results = [];

            // ✅ Kirim ke Pembeli
            if ($transaksi->pembeli) {
                $results['pembeli'] = $this->notificationService->sendNotification(
                    'pembeli',
                    $transaksi->pembeli->ID_PEMBELI,
                    $notificationType,
                    $title,
                    $message,
                    $data
                );
                Log::info("Status pengiriman notification sent to pembeli: " . ($results['pembeli'] ? 'success' : 'failed'));
            }

            // ✅ Kirim ke Penitip
            $penitipIds = $transaksi->detailTransaksi->pluck('barang.penitip.ID_PENITIP')->filter()->unique();
            foreach ($penitipIds as $penitipId) {
                $penitipMessage = "Barang titipan Anda dalam pesanan {$transaksi->NO_NOTA} telah {$status}.";
                $results['penitip_' . $penitipId] = $this->notificationService->sendNotification(
                    'penitip',
                    $penitipId,
                    $notificationType,
                    $title,
                    $penitipMessage,
                    $data
                );
                Log::info("Status pengiriman notification sent to penitip {$penitipId}: " . ($results['penitip_' . $penitipId] ? 'success' : 'failed'));
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("❌ Failed to send status pengiriman notification", [
                'transaksi_id' => $transaksiId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 🔥 REQUIREMENT 131: Notifikasi ketika barang sudah disumbangkan
     * Sesuai dengan SOAL UTAMA: "muncul di hp Penitip"
     */
    public function sendBarangDidonasikanNotification($donasiId)
    {
        try {
            $donasi = Donasi::with(['barang.penitip', 'requestDonasi.organisasi'])->find($donasiId);
            
            if (!$donasi || !$donasi->barang || !$donasi->barang->penitip) {
                Log::warning("Invalid donasi data", ['donasi_id' => $donasiId]);
                return false;
            }

            $barang = $donasi->barang;
            $organisasi = $donasi->requestDonasi?->organisasi;
            $namaOrganisasi = $organisasi ? $organisasi->NAMA_ORGANISASI : 'organisasi sosial';

            $title = "💚 Barang Anda Telah Didonasikan";
            $message = "Barang '{$barang->NAMA_BARANG}' telah didonasikan ke {$namaOrganisasi}. Terima kasih atas kontribusi Anda untuk kebaikan! Anda mendapat poin sosial sebagai apresiasi.";

            $data = [
                'type' => 'barang_didonasikan',
                'barang_id' => $barang->ID_BARANG,
                'donasi_id' => $donasi->ID_DONASI,
                'barang_name' => $barang->NAMA_BARANG,
                'organisasi_name' => $namaOrganisasi,
                'tanggal_donasi' => $donasi->TANGGAL_DONASI,
                'poin_sosial_earned' => floor($barang->HARGA / 10000) // 1 poin per 10k
            ];

            $success = $this->notificationService->sendNotification(
                'penitip',
                $barang->penitip->ID_PENITIP,
                'barang_didonasikan',
                $title,
                $message,
                $data
            );

            if ($success) {
                Log::info("✅ Barang didonasikan notification sent successfully", [
                    'barang_name' => $barang->NAMA_BARANG,
                    'penitip_name' => $barang->penitip->NAMA_PENITIP,
                    'organisasi' => $namaOrganisasi
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            Log::error("❌ Failed to send barang didonasikan notification", [
                'donasi_id' => $donasiId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * 🔥 PERINGATAN TERAKHIR sebelum barang didonasikan
     * Sesuai dengan SOAL UTAMA: 7 hari setelah masa penitipan habis
     */
    public function sendDonationWarningNotification()
    {
        try {
            $today = Carbon::today();

            // ✅ Get barang yang masa penitipannya sudah lewat dan dalam masa tunggu donasi (7 hari)
            $barangList = Barang::with(['penitip', 'detailPenitipan.penitipan'])
                ->whereHas('detailPenitipan.penitipan', function($query) use ($today) {
                    $query->where('TANGGAL_KADALUARSA', '<', $today)
                          ->where('TANGGAL_BATAS_AMBIL', '>=', $today)
                          ->where('STATUS_PENITIPAN', true);
                })
                ->where('STATUS_BARANG', 'Tersedia')
                ->get();

            Log::info("Found items needing donation warning: " . $barangList->count());

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

                $success = $this->notificationService->sendNotification(
                    'penitip',
                    $barang->penitip->ID_PENITIP,
                    'donation_warning',
                    $title,
                    $message,
                    $data
                );

                if ($success) {
                    Log::info("✅ Donation warning sent for barang: {$barang->NAMA_BARANG}");
                }
            }

        } catch (\Exception $e) {
            Log::error("❌ Failed to send donation warning notifications: " . $e->getMessage());
            throw $e;
        }
    }
    
}