<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaksi;
use App\Models\Pembeli;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PesananController extends Controller
{
    /**
     * Validate user has CS role
     */
    private function validateCSRole()
    {
        $user = Auth::user();
        if (!$user || $user->jabatan->NAMA_JABATAN !== 'Customer Service') {
            return false;
        }
        return true;
    }

    /**
     * Get list of orders filtered by status
     */
    public function index(Request $request)
    {
        // Get parameters with defaults
        $status = $request->query('status', 'Diproses');
        $search = $request->query('search', '');
        
        try {
            // Base query with relationships - FIXED: using the correct camelCase relationship name
            $query = Transaksi::with([
                'pembeli', 
                'detailTransaksi.barang', // FIXED: using camelCase as defined in the model
                'alamat'
            ]);
            
            // Apply status filter (case-insensitive)
            if (strtolower($status) === 'diproses') {
                $query->whereIn('STATUS_TRANSAKSI', ['diproses', 'Diproses']);
            } elseif (strtolower($status) === 'selesai') {
                $query->whereIn('STATUS_TRANSAKSI', ['selesai', 'Selesai']);
            }
            
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('NO_NOTA', 'like', "%{$search}%")
                      ->orWhereHas('pembeli', function($subQuery) use ($search) {
                          $subQuery->where('NAMA_PEMBELI', 'like', "%{$search}%");
                      });
                });
            }
            
            // Sort by most recent first
            $query->orderBy('WAKTU_PESAN', 'desc');
            
            // Execute query with pagination
            $pesanan = $query->paginate(15);
            
            return response()->json([
                'success' => true,
                'data' => $pesanan
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching orders: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load orders: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get order details
     */
    public function show($id)
    {
        try {
            // FIXED: Using correct camelCase relationship name
            $pesanan = Transaksi::with([
                'pembeli', 
                'detailTransaksi.barang',
                'alamat'
            ])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $pesanan
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error fetching order detail: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load order details: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Mark order as completed and add loyalty points to customer
     */
    public function markAsCompleted($id)
    {
        try {
            DB::beginTransaction();
            
            // Find transaction or fail
            $transaksi = Transaksi::with('pembeli')->findOrFail($id);
            
            // Update status
            $transaksi->STATUS_TRANSAKSI = 'Selesai';
            $transaksi->save();
            
            // Calculate and add loyalty points to customer
            if ($transaksi->pembeli) {
                // Get the total amount
                $totalAmount = $transaksi->TOTAL_AKHIR;
                
                // Base points: 1 point per Rp10,000 (rounded up)
                $basePoints = ceil($totalAmount / 10000);
                
                // Bonus points for purchases above Rp500,000 (+20%)
                $bonusPoints = 0;
                if ($totalAmount > 500000) {
                    $bonusPoints = ceil($basePoints * 0.2);
                }
                
                // Total new points
                $totalPoints = $basePoints + $bonusPoints;
                
                // Update customer's points
                $pembeli = $transaksi->pembeli;
                $currentPoints = $pembeli->POIN ?? 0;
                $pembeli->POIN = $currentPoints + $totalPoints;
                $pembeli->save();
                
                // Add information to transaction log
                $transaksi->POIN_DIDAPAT = $totalPoints;
                $transaksi->save();
            }
            
            // Process commissions to penitip if applicable
            // FIXED: Using correct camelCase relationship name
            foreach ($transaksi->detailTransaksi as $item) {
                if ($item->barang && $item->barang->penitip) {
                    // Commission processing logic would go here
                }
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil diproses dan poin loyalitas telah ditambahkan'
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing order: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pesanan: ' . $e->getMessage()
            ], 500);
        }
    }
}