<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;

use App\Http\Controllers\PembeliController;
use App\Http\Controllers\PenitipController;
use App\Http\Controllers\OrganisasiController;
use App\Http\Controllers\PegawaiController;

use App\Http\Controllers\AlamatController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\KategoriBarangController;
use App\Http\Controllers\PenitipanController;
use App\Http\Controllers\DetailPenitipanController;
use App\Http\Controllers\TransaksiController;
use App\Http\Controllers\DetailTransaksiController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\PengambilanController;
use App\Http\Controllers\MerchandiseController;
use App\Http\Controllers\RedeemMerchController;
use App\Http\Controllers\DetailRedeemController;
use App\Http\Controllers\DonasiController;
use App\Http\Controllers\RequestDonasiController;
use App\Http\Controllers\DiskusiController;
use App\Http\Controllers\JabatanController;
use App\Http\Controllers\KomisiController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PembeliPasswordResetController;
use App\Models\Organisasi;
use App\Http\Controllers\CartController;
use App\Http\Controllers\PesananController;
use App\Http\Controllers\NotificationController;


Route::prefix('auth/pembeli')->group(function () {
    Route::post('register', [PembeliController::class, 'register']);
    Route::post('login', [PembeliController::class, 'login']);
    Route::post('forgot-password', [PembeliController::class, 'forgotPassword']);
    Route::post('reset-password', [PembeliController::class, 'resetPassword']);
});

Route::prefix('auth/penitip')->group(function () {
    Route::post('login', [PenitipController::class, 'login']);
    Route::post('forgot-password', [PenitipController::class, 'forgotPassword']);
     Route::post('reset-password', [PembeliController::class, 'resetPassword']);
});

Route::prefix('auth/organisasi')->group(function () {
    Route::post('register', [OrganisasiController::class, 'register']);
    Route::post('login', [OrganisasiController::class, 'login']);
    Route::post('forgot-password', [OrganisasiController::class, 'forgotPassword']);
    Route::post('auth/organisasi/reset-password', [OrganisasiController::class, 'resetPassword']);
});

Route::prefix('auth/pegawai')->group(function () {
    Route::post('login', [PegawaiController::class, 'login']);
    Route::post('reset-password', [PegawaiController::class, 'resetPasswordByBirthdate']);
});

Route::get('barang', [BarangController::class, 'index']);
Route::get('barang/search', [BarangController::class, 'search']);
Route::get('barang/{id}', [BarangController::class, 'show']);
Route::get('/barang/{id}/similar', [BarangController::class, 'similar']);

Route::get('kategori', [KategoriBarangController::class, 'index']);
Route::get('kategori/{id}', [KategoriBarangController::class, 'show']);
Route::get('kategori/{id}/barang', [KategoriBarangController::class, 'items']);

Route::get('merchandise', [MerchandiseController::class, 'index']);
Route::get('merchandise/search', [MerchandiseController::class, 'search']);
Route::get('merchandise/{id}', [MerchandiseController::class, 'show']);

Route::get('diskusi/barang/{barangId}', [DiskusiController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', function (Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    });
    
    Route::middleware('auth:sanctum,pembeli')->prefix('pembeli')->group(function () {
        Route::get('profile', [PembeliController::class, 'profile']);
        Route::put('profile', [PembeliController::class, 'update']);
        Route::post('change-password', [PembeliController::class, 'changePassword']);
        Route::get('transaksi', [PembeliController::class, 'orderHistory']);
        
        Route::get('alamat/show', [AlamatController::class, 'index']);
        Route::get('alamat/search', [AlamatController::class, 'index']);
        Route::post('alamat', [AlamatController::class, 'store']);
        Route::get('alamat/{id}', [AlamatController::class, 'show']);
        Route::put('alamat/{id}', [AlamatController::class, 'update']);
        Route::delete('alamat/{id}', [AlamatController::class, 'destroy']);
        
        Route::get('transaksi/show', [TransaksiController::class, 'index']);
        Route::get('transaksi/search', [TransaksiController::class, 'search']);
        Route::post('transaksi', [TransaksiController::class, 'store']);
        Route::get('transaksi/{id}', [TransaksiController::class, 'show']);
        Route::post('transaksi/{id}/upload-bukti', [TransaksiController::class, 'uploadPaymentProof']);
        Route::post('transaksi/{id}/cancel', [TransaksiController::class, 'cancel']);

        Route::post('transaksi/{id}/cancel-valid', [TransaksiController::class, 'cancelValidTransaction']);
        
        Route::post('diskusi', [DiskusiController::class, 'store']);
        Route::get('diskusi', [DiskusiController::class, 'customerDiscussions']);
        
        Route::get('redeem', [RedeemMerchController::class, 'customerRedemptions']);
        Route::post('redeem', [RedeemMerchController::class, 'store']);
        Route::post('redeem/{id}/cancel', [RedeemMerchController::class, 'cancel']);
        Route::post('logout', [PembeliController::class, 'logout']);
    });
    
    Route::middleware('auth:sanctum,penitip')->prefix('penitip')->group(function () {
         Route::get('profile', [PenitipController::class, 'profile']);
        Route::put('profile', [PenitipController::class, 'update']);
        Route::post('change-password', [PenitipController::class, 'changePassword']);
        Route::get('barang', [PenitipController::class, 'itemHistory']);
        Route::get('penitipan', [PenitipController::class, 'depositHistory']);
        Route::get('saldo', [PenitipController::class, 'showBalance']);
        Route::get('komisi', [KomisiController::class, 'depositorCommissionHistory']);
        Route::post('barang/{id}/konfirmasi-ambil', [BarangController::class, 'konfirmasiAmbil']);
        Route::get('tampil-barang', [BarangController::class, 'indexPenitip']);
        Route::get('tampil-barang/{id}', [BarangController::class, 'show']); 
        Route::get('barang/search', [BarangController::class, 'searchGudang']);
        Route::post('penitipan/{id}/extend', [PenitipanController::class, 'extend']);
        Route::post('penitipan/{id}/cancel', [PenitipanController::class, 'cancel']);
        Route::post('logout', [PenitipController::class, 'logout']);
        Route::post('barang/{id}/extend', [BarangController::class, 'extendConsignment']);
    });
    
    Route::middleware('auth:sanctum,organisasi')->prefix('organisasi')->group(function () {
        Route::get('profile', [OrganisasiController::class, 'profile']);
        Route::put('profile', [OrganisasiController::class, 'update']);
        Route::post('change-password', [OrganisasiController::class, 'changePassword']);
        
        Route::get('request', [OrganisasiController::class, 'donationRequests']);
        Route::post('request', [RequestDonasiController::class, 'store']);
        Route::put('request/{id}', [RequestDonasiController::class, 'update']);
        Route::delete('request/{id}', [RequestDonasiController::class, 'destroy']);
        
        Route::get('donasi', [OrganisasiController::class, 'donationsReceived']);

        Route::post('logout', [OrganisasiController::class, 'logout']);
    });
    
    Route::middleware(['auth:sanctum', 'can:admin-access'])->prefix('admin')->group(function () {
        Route::get('pegawai/search', [PegawaiController::class, 'search']);
        Route::apiResource('pegawai', PegawaiController::class);
        Route::get('pegawai/{id}/commissions', [PegawaiController::class, 'commissions']);

        Route::get('jabatan/search', [JabatanController::class, 'search']);
        Route::apiResource('jabatan', JabatanController::class);
        Route::get('jabatan/{id}/employees', [JabatanController::class, 'employees']);

        Route::get('organisasi/search', [OrganisasiController::class, 'search']);
        Route::apiResource('organisasi', OrganisasiController::class, ['except' => ['store']]);

        Route::get('merchandise/search', [MerchandiseController::class, 'search']);
        Route::apiResource('merchandise', MerchandiseController::class);
        Route::put('merchandise/{id}/stock', [MerchandiseController::class, 'updateStock']);
        
        Route::post('hitung-top-seller', [PenitipController::class, 'hitungTopSeller']);
        Route::get('/penitip/penjualan', [PenitipController::class, 'penjualan']);
        Route::get('/penitip', [PenitipController::class, 'index']);
        Route::get('/penitip/{id}', [PenitipController::class, 'show']);

        Route::post('logout', [PegawaiController::class, 'logout']);
    });

    Route::middleware(['auth:sanctum', 'can:owner-access'])->prefix('owner')->group(function () {
        // Route::get('report/sales', [TransaksiController::class, 'salesReport']);
        // Route::get('report/inventory', [BarangController::class, 'inventoryReport']);
        // Route::get('report/commissions', [KomisiController::class, 'commissionReport']);
        // Route::get('report/donations', [DonasiController::class, 'report']);

        // Route::apiResource('donasi', DonasiController::class);
        // Route::post('logout', [PegawaiController::class, 'logout']);
        // Route::get('/donasi/history-search', [DonasiController::class, 'historyByNamaOrganisasi']); // Pindahkan ke atas dulu
        // Route::get('/donasi-search', [DonasiController::class, 'search']);
        // Route::get('/donasi', [DonasiController::class, 'index']);
        // Route::get('/donasi/{id}', [DonasiController::class, 'show']);
        // Route::post('/donasi', [DonasiController::class, 'store']);
        // Route::put('/donasi/{id}', [DonasiController::class, 'update']);


        // Route::get('request_donasi/search', [RequestDonasiController::class, 'searchAll']);
        // Route::get('request_donasi', [RequestDonasiController::class, 'indexAll']);
        // Route::post('logout', [PegawaiController::class, 'logout']);

        Route::get('/kategori', [KategoriBarangController::class, 'getKategori']);
        Route::get('/laporan/donasi-barang', [DonasiController::class, 'exportLaporanDonasiBarang']);
        Route::get('/laporan/request-donasi', [RequestDonasiController::class, 'exportLaporanRequestDonasi']);
        Route::get('/laporan/stok-barang', [BarangController::class, 'exportLaporanStokGudang']);
        Route::get('/laporan/kategori', [KategoriBarangController::class, 'exportLaporanKategoriTahunan']);

        Route::get('/donasi/history-search', [DonasiController::class, 'historyByNamaOrganisasi']); // Pindahkan ke atas dulu
        Route::get('/donasi-search', [DonasiController::class, 'search']);
        Route::get('/donasi', [DonasiController::class, 'index']);
        Route::get('/donasi/{id}', [DonasiController::class, 'show']);
        Route::post('/donasi', [DonasiController::class, 'store']);
        Route::put('/donasi/{id}', [DonasiController::class, 'update']);

        Route::get('/barang/layak-donasi', [BarangController::class, 'barangLayakDonasi']);
        Route::get('request_donasi/search', [RequestDonasiController::class, 'searchAll']);
        Route::get('request_donasi', [RequestDonasiController::class, 'indexAll']);
        Route::post('logout', [PegawaiController::class, 'logout']);
        Route::get('/laporan/barang-kadaluarsa', [BarangController::class, 'exportLaporanBarangKadaluarsa']);

        Route::apiResource('komisi', KomisiController::class);
        Route::get('/laporan/komisi-bulanan', [KomisiController::class, 'exportLaporanKomisiBulanan']);
        Route::get('stok-barang', [BarangController::class, 'index']);
        Route::get('stok-barang/search', [BarangController::class, 'searchGudang']);
        Route::apiResource('donasi', DonasiController::class);
        Route::post('logout', [PegawaiController::class, 'logout']);
         Route::get('/laporan/transaksi-bulanan/export', [TransaksiController::class, 'laporanPenjualanBulananPDF']);
        Route::get('/laporan/transaksi-bulanan', [TransaksiController::class, 'getLaporanPenjualanBulananJSON']);

        Route::get('/laporan/transaksi-penitip', [PenitipController::class, 'getLaporanTransaksiPenitip']);
        Route::get('/laporan/transaksi-penitip/export', [PenitipController::class, 'exportLaporanTransaksiPenitip']);
        Route::get('/penitip/list', [PenitipController::class, 'listPenitip']);

    });

    Route::middleware(['auth:sanctum', 'can:cs-access'])->prefix('cs')->group(function () {
        Route::get('/klaim-merchandise', [MerchandiseController::class, 'klaimList']);
        Route::put('/klaim-merchandise/{id}/tanggal-ambil', [MerchandiseController::class, 'isiTanggalAmbil']);
        Route::get('penitip/search', [PenitipController::class, 'search']);
        Route::apiResource('penitip', PenitipController::class);
        Route::get('transaksi', [TransaksiController::class, 'index']);
        Route::get('transaksi/{id}', [TransaksiController::class, 'show']);
        Route::post('transaksi/{id}/verify-payment', [TransaksiController::class, 'verifyPayment']);
        Route::get('diskusi', [DiskusiController::class, 'index']);
        Route::post('diskusi', [DiskusiController::class, 'store']);
        Route::get('redeem', [RedeemMerchController::class, 'index']);
        Route::get('redeem/{id}', [RedeemMerchController::class, 'show']);
        Route::get('merchandise/klaim-list', [MerchandiseController::class, 'klaimList']);
        Route::post('merchandise/klaim/{id_redeem}/ambil', [MerchandiseController::class, 'isiTanggalAmbil']);
        Route::post('logout', [PegawaiController::class, 'logout']);
    });

  Route::middleware(['auth:sanctum', 'can:gudang-access'])->prefix('gudang')->group(function () {
        Route::get('barang/search', [BarangController::class, 'search']);
        Route::get('penitip', [PenitipController::class, 'index']);
        Route::apiResource('barang', BarangController::class);
        Route::post('barang/{id}/sold-out', [BarangController::class, 'soldOut']);
        Route::apiResource('kategori', KategoriBarangController::class);
        Route::apiResource('penitipan', PenitipanController::class);
        Route::get('penitipan/expired', [PenitipanController::class, 'expiredConsignments']);
        Route::get('penitipan/past-due', [PenitipanController::class, 'pastDueConsignments']);
        Route::resource('penitipan.items', DetailPenitipanController::class);
        Route::post('transaksi/{id}/update-status', [TransaksiController::class, 'updateStatus']);
        Route::apiResource('pengiriman', PengirimanController::class);
        Route::apiResource('pengambilan', PengambilanController::class);
        Route::post('pengambilan/{id}/cancel', [PengambilanController::class, 'confirmCancellation']);
        Route::resource('transaksi.items', DetailTransaksiController::class);
        Route::apiResource('donasi', DonasiController::class, ['except' => ['destroy', 'update']]);
        Route::apiResource('request-donasi', RequestDonasiController::class);
        Route::get('request-donasi/pending', [RequestDonasiController::class, 'pendingRequests']);
        Route::post('logout', [PegawaiController::class, 'logout']);
        Route::get('penitipan/{id}/nota-preview', [PenitipanController::class, 'previewNota']);
        Route::get('penitipan/nota-download/{id}', [PenitipanController::class, 'cetakNota']);
        Route::get('pegawai/pesanan-diproses', [PegawaiController::class, 'pesananDiproses']);
        Route::get('nota/{id}', [PengirimanController::class, 'cetakNotaKurir']);
        Route::get('nota-pembeli/{id}', [PengirimanController::class, 'cetakNotaPembeli']);
        Route::post('barang/{id}/ambil', [BarangController::class, 'ambilKembali']);
        Route::post('barang/{id}/extend', [BarangController::class, 'extendConsignment']);
        Route::get('pegawai-hunter', [PegawaiController::class, 'listHunter']);
    });

    Route::middleware(['auth:sanctum', 'can:kurir-access'])->prefix('kurir')->group(function () {
        Route::get('pengiriman', [PengirimanController::class, 'index']);
        Route::get('pengiriman/{id}', [PengirimanController::class, 'show']);
        Route::post('pengiriman/{id}/update-status', [PengirimanController::class, 'updateStatus']);
        Route::get('profile', [PegawaiController::class, 'profile']);
        Route::post('logout', [PegawaiController::class, 'logout']);
    });

    Route::middleware(['auth:pegawai', 'can:hunter-access'])->prefix('hunter')->group(function () {
        // Profile - Fixed to ensure numeric values
        Route::get('profile', function(Request $request) {
            $hunter = $request->user()->load('jabatan');
            
            // Ensure GAJI is returned as number, not string
            if ($hunter->jabatan) {
                $hunter->jabatan->GAJI = (float) $hunter->jabatan->GAJI;
            }
            
            return response()->json(['data' => $hunter]);
        });
        
        // Commission routes
        Route::get('komisi', [KomisiController::class, 'hunterCommissionHistory']);
        Route::get('komisi/summary', [KomisiController::class, 'hunterCommissionSummary']);
        Route::get('komisi/monthly', [KomisiController::class, 'hunterMonthlyCommission']);
        Route::get('komisi/{id}', [KomisiController::class, 'hunterCommissionDetail']);
        
        // Logout
        Route::post('logout', [PegawaiController::class, 'logout']);
    });

    Route::prefix('redeem')->group(function () {
        Route::get('{redeemId}/items', [DetailRedeemController::class, 'index']);
        Route::post('{redeemId}/items', [DetailRedeemController::class, 'store']);
        Route::get('{redeemId}/items/{merchandiseId}', [DetailRedeemController::class, 'show']);
        Route::put('{redeemId}/items/{merchandiseId}', [DetailRedeemController::class, 'update']);
        Route::delete('{redeemId}/items/{merchandiseId}', [DetailRedeemController::class, 'destroy']);

        // Password Reset Routes
        Route::post('auth/{userType}/forgot-password', [PasswordResetController::class, 'forgotPassword']);
        Route::post('auth/{userType}/reset-password', [PasswordResetController::class, 'resetPassword']);
            });
        });


       Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);

// User-specific password reset endpoints (for backward compatibility)
Route::post('/auth/pembeli/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/auth/penitip/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/auth/organisasi/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail']);

Route::post('/auth/pembeli/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/auth/penitip/reset-password', [PasswordResetController::class, 'reset']);
Route::post('/auth/organisasi/reset-password', [PasswordResetController::class, 'reset']);

// Include existing API routes...

Route::get('/diskusi/produk/{barangId}', [DiskusiController::class, 'productDiscussions']);
Route::post('/diskusi', [DiskusiController::class, 'store']);

Route::get('/cek-organisasi', function (Request $request) {
    try {
        $nama = $request->query('nama');
        $org = Organisasi::where('nama_organisasi', $nama)->first(); // ✅ pakai nama field sebenarnya

        if ($org) {
            return response()->json([
                'exists' => true,
                'alamat' => $org->ALAMAT
            ]);
        }

        return response()->json(['exists' => false]);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});


//MINGGU 2 PRAMOEX

// Shopping cart endpoints

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pembeli/cart', [CartController::class, 'store']);
    Route::get('/pembeli/cart', [CartController::class, 'index']);
    Route::delete('/pembeli/cart/{id}', [CartController::class, 'destroy']);
});

Route::post('pembeli/transaksi/{id}/bukti-pembayaran', [App\Http\Controllers\TransaksiController::class, 'uploadPaymentProof'])
    ->middleware('auth:pembeli');


Route::prefix('cs')->middleware('auth:pegawai')->group(function () {
    Route::get('/verifikasi', [App\Http\Controllers\TransaksiController::class, 'pendingVerifications']);
    Route::post('/transaksi/{id}/verify', [App\Http\Controllers\TransaksiController::class, 'verifyPayment']);
});

Route::prefix('cs')->middleware(['auth:sanctum'])->group(function () {
    // Get orders list
    Route::get('/pesanan', [PesananController::class, 'index']);
    
    // Get order details
    Route::get('/pesanan/{id}', [PesananController::class, 'show']);
    
    // Mark order as completed
    Route::put('/pesanan/{id}/selesai', [PesananController::class, 'markAsCompleted']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Endpoint untuk mendapatkan PDF nota
    Route::get('/pembeli/transaksi/{id}/pdf', [TransaksiController::class, 'generatePdf']);
});

Route::middleware('auth:sanctum,pembeli')->prefix('pembeli')->group(function () {
    // Rute-rute yang sudah ada
    
    // Tambahkan route untuk rating
    Route::post('rating', [PembeliController::class, 'submitRating']);
});

Route::middleware('auth:sanctum')->group(function () {
    // FCM Token management
    Route::post('/fcm-token', [App\Http\Controllers\NotificationController::class, 'updateFcmToken']);
    
    // Notifications
    Route::get('/notifications', [App\Http\Controllers\NotificationController::class, 'getUserNotifications']);
    Route::put('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [App\Http\Controllers\NotificationController::class, 'getUnreadCount']);
});

Route::get('/test-firebase', function() {
    try {
        // Test 1: Cek konfigurasi Firebase V1
        $projectId = config('fcm.project_id');
        $credentialsPath = config('fcm.key');
        
        if (!$projectId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Firebase Project ID not configured',
                'data' => [
                    'project_id_exists' => false,
                    'instructions' => 'Add FIREBASE_PROJECT_ID to .env file'
                ]
            ]);
        }

        // Test 2: Cek service account file
        $fullCredentialsPath = $credentialsPath ? storage_path('app/firebase/serviceAccountKey.json') : null;
        $credentialsExists = $fullCredentialsPath && file_exists($fullCredentialsPath);
        
        return response()->json([
            'status' => $credentialsExists ? 'success' : 'warning',
            'message' => $credentialsExists ? 'Firebase V1 API configuration OK' : 'Service account file missing',
            'data' => [
                'api_version' => 'Firebase V1 API (Latest)',
                'project_id_configured' => true,
                'project_id' => $projectId,
                'credentials_file_exists' => $credentialsExists,
                'credentials_path' => $fullCredentialsPath,
                'instructions' => $credentialsExists ? null : 'Download serviceAccountKey.json from Firebase Console'
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Firebase test failed: ' . $e->getMessage()
        ]);
    }
});


// Test send notification (butuh auth)
Route::middleware('auth:sanctum')->post('/test-send-notification', function(Illuminate\Http\Request $request) {
    try {
        $user = $request->user();
        
        // Determine user type
        $userType = 'pembeli'; // default
        $userId = method_exists($user, 'ID_PEMBELI') ? $user->ID_PEMBELI : $user->id;
        
        if (method_exists($user, 'ID_PENITIP')) {
            $userType = 'penitip';
            $userId = $user->ID_PENITIP;
        } elseif (method_exists($user, 'ID_PEGAWAI')) {
            $userType = 'pegawai';
            $userId = $user->ID_PEGAWAI;
        }

        // Check if user has FCM token
        if (empty($user->fcm_token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not have FCM token. Please update from mobile app first.',
                'user_type' => $userType,
                'user_id' => $userId
            ]);
        }

        // Send test notification
        $notificationService = new \App\Services\NotificationService();
        $result = $notificationService->sendNotification(
            $userType,
            $userId,
            'test',
            '🔥 Test Notification ReuseMart',
            'Jika Anda melihat notifikasi ini, setup Firebase berhasil!',
            ['test_time' => now()->toISOString()]
        );

        return response()->json([
            'status' => $result ? 'success' : 'failed',
            'message' => $result ? 'Test notification sent successfully!' : 'Failed to send notification',
            'data' => [
                'user_type' => $userType,
                'user_id' => $userId,
                'has_fcm_token' => !empty($user->fcm_token),
                'fcm_token_preview' => substr($user->fcm_token, 0, 20) . '...'
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Test notification failed: ' . $e->getMessage()
        ]);
    }
});

Route::get('/test-firebase', function() {
    try {
        // Test 1: Cek konfigurasi
        $serverKey = config('services.fcm.key');
        $projectId = config('services.fcm.project_id');
        
        if (!$serverKey || !$projectId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Firebase configuration missing',
                'data' => [
                    'server_key_exists' => !empty($serverKey),
                    'project_id_exists' => !empty($projectId)
                ]
            ]);
        }

        // Test 2: Cek service account file
        $credentialsPath = storage_path('app/firebase/serviceAccountKey.json');
        $credentialsExists = file_exists($credentialsPath);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Firebase configuration OK',
            'data' => [
                'server_key_configured' => true,
                'project_id_configured' => true,
                'project_id' => $projectId,
                'credentials_file_exists' => $credentialsExists,
                'credentials_path' => $credentialsPath
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Firebase test failed: ' . $e->getMessage()
        ]);
    }
});

// routes/api.php - Test send notification (butuh auth)
Route::middleware('auth:sanctum')->post('/test-send-notification', function(Request $request) {
    try {
        $user = $request->user();
        
        // Determine user type
        $userType = 'pembeli'; // default
        $userId = method_exists($user, 'ID_PEMBELI') ? $user->ID_PEMBELI : $user->id;
        
        if (method_exists($user, 'ID_PENITIP')) {
            $userType = 'penitip';
            $userId = $user->ID_PENITIP;
        } elseif (method_exists($user, 'ID_PEGAWAI')) {
            $userType = 'pegawai';
            $userId = $user->ID_PEGAWAI;
        }

        // Check if user has FCM token
        if (empty($user->fcm_token)) {
            return response()->json([
                'status' => 'error',
                'message' => 'User does not have FCM token. Please update from mobile app first.',
                'user_type' => $userType,
                'user_id' => $userId
            ]);
        }

        // Send test notification
        $notificationService = new \App\Services\NotificationService();
        $result = $notificationService->sendNotification(
            $userType,
            $userId,
            'test',
            '🔥 Test Notification ReuseMart',
            'Jika Anda melihat notifikasi ini, setup Firebase berhasil!',
            ['test_time' => now()->toISOString()]
        );

        return response()->json([
            'status' => $result ? 'success' : 'failed',
            'message' => $result ? 'Test notification sent successfully!' : 'Failed to send notification',
            'data' => [
                'user_type' => $userType,
                'user_id' => $userId,
                'has_fcm_token' => !empty($user->fcm_token),
                'fcm_token_preview' => substr($user->fcm_token, 0, 20) . '...'
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Test notification failed: ' . $e->getMessage()
        ]);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    // Update FCM token (generic endpoint that detects user type)
    Route::post('/fcm/update-token', [NotificationController::class, 'updateFcmToken']);
    
    // Get user notifications
    Route::get('/notifications', [NotificationController::class, 'getUserNotifications']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
});

// Specific endpoints for each user type (optional, for backward compatibility)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/pembeli/fcm-token', [NotificationController::class, 'updatePembeliFcmToken']);
    Route::post('/penitip/fcm-token', [NotificationController::class, 'updatePenitipFcmToken']);
    Route::post('/pegawai/fcm-token', [NotificationController::class, 'updatePegawaiFcmToken']);
});
//MINGGU 2 PRAMOEX

//erika
Route::middleware('auth:sanctum')->prefix('pegawai')->group(function () {
    Route::get('/transaksi', [PegawaiController::class, 'transaksiAktif']);
    Route::post('/jadwal-pengiriman', [PegawaiController::class, 'jadwalPengiriman']);
    Route::post('/jadwal-ambil', [PegawaiController::class, 'jadwalAmbil']);
    Route::post('/konfirmasi-terima', [PegawaiController::class, 'konfirmasiTerima']);
    Route::get('/pesanan-diproses', [PegawaiController::class, 'pesananDiproses']);
});

Route::middleware('auth:sanctum')->prefix('penitip')->group(function () {
    Route::post('/barang/{id}/ajukan-ambil', [BarangController::class, 'ajukanAmbil']);
});

Route::middleware('auth:sanctum')->prefix('gudang')->group(function () {
    Route::get('/barang-menunggu-konfirmasi', [BarangController::class, 'barangMenungguKonfirmasi']);
    Route::post('/barang/{id}/konfirmasi-ambil', [BarangController::class, 'konfirmasiAmbilGudang']);
});

//erika
Route::prefix('gudang')->middleware(['auth:sanctum'])->group(function () {
    // Get orders list for Gudang
    Route::get('/pesanan', [TransaksiController::class, 'index']);

    // Get order detail
    Route::get('/pesanan/{id}', [TransaksiController::class, 'show']);

    // Mark order as completed
    Route::put('/pesanan/{id}/selesai', [TransaksiController::class, 'updateStatus']);
});
//erika