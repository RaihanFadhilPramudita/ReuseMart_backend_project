<?php

namespace App\Http\Controllers;

use App\Models\DetailRedeem;
use App\Models\RedeemMerch;
use App\Models\Merchandise;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DetailRedeemController extends Controller
{
    public function index($redeemId)
    {
        $user = auth('sanctum')->user();
        $redeem = RedeemMerch::findOrFail($redeemId);
        
        if (isset($user->ID_PEMBELI) && $redeem->ID_PEMBELI != $user->ID_PEMBELI) {
            return response()->json(['error' => 'Unauthorized access to this redemption'], 403);
        }
        
        if (isset($user->ID_PEGAWAI) && isset($user->ID_JABATAN)) {
            $allowedRoles = [1, 2, 3]; // Owner, CS, Admin
            if (!in_array($user->ID_JABATAN, $allowedRoles)) {
                return response()->json(['error' => 'Unauthorized access to redemption data'], 403);
            }
        }
        
        $detailRedeem = DetailRedeem::with(['merchandise'])
            ->where('ID_REDEEM', $redeemId)
            ->get();
            
        return response()->json(['data' => $detailRedeem]);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_redeem' => 'required|exists:redeem_merch,ID_REDEEM',
            'id_merchandise' => 'required|exists:merchandise,ID_MERCHANDISE',
            'jumlah_merch' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // Check merchandise stock
        $merchandise = Merchandise::find($request->id_merchandise);
        if (!$merchandise || $merchandise->STOK < $request->jumlah_merch) {
            return response()->json([
                'message' => 'Not enough stock available',
                'available' => $merchandise ? $merchandise->STOK : 0
            ], 422);
        }
        
        // Check if this merchandise is already in the redeem
        $existingDetail = DetailRedeem::where('ID_REDEEM', $request->id_redeem)
            ->where('ID_MERCHANDISE', $request->id_merchandise)
            ->first();
            
        if ($existingDetail) {
            // Update quantity instead of creating new
            $existingDetail->JUMLAH_MERCH = (int)$existingDetail->JUMLAH_MERCH + (int)$request->jumlah_merch;
            $existingDetail->save();
            
            // Update merchandise stock
            $merchandise->STOK -= (int)$request->jumlah_merch;
            $merchandise->save();
            
            return response()->json([
                'message' => 'Merchandise quantity updated in redemption',
                'data' => $existingDetail->load('merchandise')
            ]);
        }
        
        // Create new detail
        $detailRedeem = new DetailRedeem();
        $detailRedeem->ID_REDEEM = $request->id_redeem;
        $detailRedeem->ID_MERCHANDISE = $request->id_merchandise;
        $detailRedeem->JUMLAH_MERCH = $request->jumlah_merch;
        $detailRedeem->save();
        
        // Update merchandise stock
        $merchandise->STOK -= (int)$request->jumlah_merch;
        $merchandise->save();
        
        return response()->json([
            'message' => 'Merchandise added to redemption successfully',
            'data' => $detailRedeem->load('merchandise')
        ], 201);
    }
    
    public function show($redeemId, $merchandiseId)
    {
        $detailRedeem = DetailRedeem::with(['merchandise', 'redeemMerch.pembeli'])
            ->where('ID_REDEEM', $redeemId)
            ->where('ID_MERCHANDISE', $merchandiseId)
            ->firstOrFail();
            
        return response()->json(['data' => $detailRedeem]);
    }
    
    public function update(Request $request, $redeemId, $merchandiseId)
    {
        $validator = Validator::make($request->all(), [
            'jumlah_merch' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $detailRedeem = DetailRedeem::where('ID_REDEEM', $redeemId)
            ->where('ID_MERCHANDISE', $merchandiseId)
            ->firstOrFail();
            
        $merchandise = Merchandise::find($merchandiseId);
        
        // Calculate stock difference
        $diff = (int)$request->jumlah_merch - (int)$detailRedeem->JUMLAH_MERCH;
        
        // Check if enough stock for increase
        if ($diff > 0 && $merchandise && $merchandise->STOK < $diff) {
            return response()->json([
                'message' => 'Not enough stock available for increase',
                'available' => $merchandise->STOK
            ], 422);
        }
        
        // Update merchandise stock
        if ($merchandise && $diff !== 0) {
            $merchandise->STOK -= $diff;
            $merchandise->save();
        }
        
        $detailRedeem->JUMLAH_MERCH = $request->jumlah_merch;
        $detailRedeem->save();
        
        return response()->json([
            'message' => 'Redemption detail updated successfully',
            'data' => $detailRedeem
        ]);
    }
    
    public function destroy($redeemId, $merchandiseId)
    {
        $detailRedeem = DetailRedeem::where('ID_REDEEM', $redeemId)
            ->where('ID_MERCHANDISE', $merchandiseId)
            ->firstOrFail();
            
        // Restore merchandise stock
        $merchandise = Merchandise::find($merchandiseId);
        if ($merchandise) {
            $merchandise->STOK += (int)$detailRedeem->JUMLAH_MERCH;
            $merchandise->save();
        }
        
        // Check if the main redemption has other items
        $otherItems = DetailRedeem::where('ID_REDEEM', $redeemId)
            ->where('ID_MERCHANDISE', '!=', $merchandiseId)
            ->count();
            
        $detailRedeem->delete();
        
        // If no other items, notify that the main redemption is empty
        $message = 'Merchandise removed from redemption successfully';
        if ($otherItems === 0) {
            $message .= '. Warning: Redemption is now empty';
        }
        
        return response()->json(['message' => $message]);
    }
}