<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // 🔥 CRITICAL: Missing import
use Illuminate\Support\Facades\Log; // 🔥 ADD: For error logging
use App\Models\RedeemMerch;
use App\Models\DetailRedeem;
use App\Models\Merchandise;
use App\Models\Pembeli;

class RedeemMerchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $redeems = RedeemMerch::with(['pembeli', 'detailRedeem.merchandise'])
                ->orderBy('TANGGAL_REDEEM', 'desc')
                ->get();

            return response()->json([
                'message' => 'Redemptions retrieved successfully',
                'data' => $redeems
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching redemptions: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch redemptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_pembeli' => 'required|integer|exists:pembeli,ID_PEMBELI',
                'items' => 'required|array|min:1',
                'items.*.id_merchandise' => 'required|integer|exists:merchandise,ID_MERCHANDISE',
                'items.*.jumlah' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 🔥 FIX: Now DB should work because we imported it
            DB::beginTransaction();
            
            // Get user's current points
            $pembeli = Pembeli::findOrFail($request->id_pembeli);
            $currentPoints = (float) $pembeli->POIN;
            
            // Calculate total points needed
            $totalPointsNeeded = 0;
            $itemsData = [];
            
            foreach ($request->items as $item) {
                $merchandise = Merchandise::findOrFail($item['id_merchandise']);
                
                // Check stock availability
                if ($merchandise->STOK < $item['jumlah']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Not enough stock',
                        'merchandise' => $merchandise->NAMA_MERCHANDISE,
                        'available' => $merchandise->STOK,
                        'requested' => $item['jumlah']
                    ], 422);
                }
                
                $pointsForItem = $merchandise->POIN_REQUIRED * $item['jumlah'];
                $totalPointsNeeded += $pointsForItem;
                
                $itemsData[] = [
                    'merchandise' => $merchandise,
                    'quantity' => $item['jumlah'],
                    'points' => $pointsForItem
                ];
            }
            
            // Check if user has enough points
            if ($currentPoints < $totalPointsNeeded) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Not enough points',
                    'required_points' => $totalPointsNeeded,
                    'available_points' => $currentPoints,
                    'shortage' => $totalPointsNeeded - $currentPoints
                ], 422);
            }
            
            // 🔥 FIX: Create redeem record without TOTAL_POIN (column doesn't exist)
            $redeem = new RedeemMerch();
            $redeem->ID_PEMBELI = $request->id_pembeli;
            $redeem->TANGGAL_REDEEM = now();
            // ❌ REMOVED: $redeem->TOTAL_POIN = $totalPointsNeeded; (column doesn't exist)
            $redeem->STATUS = 'Pending'; // 🔥 CRITICAL: Set STATUS field to avoid database error
            $redeem->save();
            
            // Create detail records and update stock
            foreach ($itemsData as $itemData) {
                // Create detail redeem
                DetailRedeem::create([
                    'ID_REDEEM' => $redeem->ID_REDEEM,
                    'ID_MERCHANDISE' => $itemData['merchandise']->ID_MERCHANDISE,
                    'JUMLAH_MERCH' => $itemData['quantity']
                ]);
                
                // Update merchandise stock
                $itemData['merchandise']->STOK -= $itemData['quantity'];
                $itemData['merchandise']->save();
            }
            
            // Deduct points from user
            $pembeli->POIN = $currentPoints - $totalPointsNeeded;
            $pembeli->save();
            
            DB::commit();
            
            return response()->json([
                'message' => 'Merchandise redeemed successfully',
                'data' => $redeem->load('detailRedeem.merchandise'),
                'points_used' => $totalPointsNeeded,
                'remaining_points' => $pembeli->POIN
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Log the error for debugging
            Log::error('Redeem merchandise error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return JSON error response instead of HTML
            return response()->json([
                'message' => 'Failed to redeem merchandise',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $redeem = RedeemMerch::with(['pembeli', 'detailRedeem.merchandise'])
                ->findOrFail($id);

            return response()->json([
                'message' => 'Redemption retrieved successfully',
                'data' => $redeem
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching redemption: ' . $e->getMessage());
            return response()->json([
                'message' => 'Redemption not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cancel redemption - restore points and stock
     */
    public function cancel($id)
    {
        try {
            DB::beginTransaction();

            $redeem = RedeemMerch::with(['pembeli', 'detailRedeem.merchandise'])
                ->findOrFail($id);

            // Check if redemption can be cancelled
            if ($redeem->STATUS === 'Completed') {
                return response()->json([
                    'message' => 'Cannot cancel completed redemption'
                ], 422);
            }

            if ($redeem->STATUS === 'Cancelled') {
                return response()->json([
                    'message' => 'Redemption is already cancelled'
                ], 422);
            }

            // Calculate total points from detail records (since TOTAL_POIN column doesn't exist)
            $totalPointsToRestore = 0;
            foreach ($redeem->detailRedeem as $detail) {
                $totalPointsToRestore += $detail->merchandise->POIN_REQUIRED * $detail->JUMLAH_MERCH;
            }

            // Restore user points
            $pembeli = $redeem->pembeli;
            $pembeli->POIN += $totalPointsToRestore;
            $pembeli->save();

            // Restore merchandise stock
            foreach ($redeem->detailRedeem as $detail) {
                $merchandise = $detail->merchandise;
                $merchandise->STOK += $detail->JUMLAH_MERCH;
                $merchandise->save();
            }

            // Update redemption status
            $redeem->STATUS = 'Cancelled';
            $redeem->save();

            DB::commit();

            return response()->json([
                'message' => 'Redemption cancelled successfully',
                'data' => $redeem->load('detailRedeem.merchandise'),
                'points_restored' => $totalPointsToRestore,
                'current_points' => $pembeli->POIN
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error cancelling redemption: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to cancel redemption',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer redemptions
     */
    public function customerRedemptions()
    {
        try {
            $user = auth('sanctum')->user();
            
            if (!$user || !isset($user->ID_PEMBELI)) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 403);
            }

            $redemptions = RedeemMerch::with(['detailRedeem.merchandise'])
                ->where('ID_PEMBELI', $user->ID_PEMBELI)
                ->orderBy('TANGGAL_REDEEM', 'desc')
                ->get();

            return response()->json([
                'message' => 'Customer redemptions retrieved successfully',
                'data' => $redemptions
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching customer redemptions: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to fetch redemptions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update redemption status (for staff)
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|string|in:Pending,Processing,Completed,Cancelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $redeem = RedeemMerch::findOrFail($id);
            $redeem->STATUS = $request->status;
            $redeem->save();

            return response()->json([
                'message' => 'Redemption status updated successfully',
                'data' => $redeem->load('detailRedeem.merchandise')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating redemption status: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Failed to update redemption status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to calculate total points from detail records
     */
    private function calculateTotalPoints($redemptionId)
    {
        $details = DetailRedeem::with('merchandise')
            ->where('ID_REDEEM', $redemptionId)
            ->get();
        
        $total = 0;
        foreach ($details as $detail) {
            $total += $detail->merchandise->POIN_REQUIRED * $detail->JUMLAH_MERCH;
        }
        
        return $total;
    }
}