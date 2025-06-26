<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Barang;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
   public function store(Request $request)
    {
        $request->validate([
            'id_barang' => 'required|exists:barang,ID_BARANG',
        ]);

        $pembeli = Auth::guard('pembeli')->user();
        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check if the item is already in the cart
        $cart = Cart::where('ID_PEMBELI', $pembeli->ID_PEMBELI)
                    ->where('ID_BARANG', $request->id_barang)
                    ->first();

        if ($cart) {
            // Item already in cart - do nothing or return a message
            return response()->json(['message' => 'Barang sudah ada di keranjang']);
        } else {
            // Get the barang and check if it's available
            $barang = Barang::find($request->id_barang);
            if (!$barang || $barang->STATUS_BARANG !== 'Tersedia') {
                return response()->json(['message' => 'Barang tidak tersedia'], 400);
            }
            
            // Mark the barang as Sold Out
            $barang->STATUS_BARANG = 'Sold Out';
            $barang->save();
            
            // Add new item with quantity 1
            $cart = Cart::create([
                'ID_PEMBELI' => $pembeli->ID_PEMBELI,
                'ID_BARANG' => $request->id_barang,
                'JUMLAH' => 1, // Always set to 1
            ]);
        }

        return response()->json(['message' => 'Barang ditambahkan ke keranjang', 'data' => $cart]);
    }

    public function index()
    {
        $pembeli = Auth::guard('pembeli')->user();
        if (!$pembeli) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $cartItems = Cart::with('barang')
            ->where('ID_PEMBELI', $pembeli->ID_PEMBELI)
            ->get();

        return response()->json(['data' => $cartItems]);
    }

   public function destroy($id)
    {
        $pembeli = Auth::guard('pembeli')->user();
        
        // Find the cart item
        $cart = Cart::where('ID_PEMBELI', $pembeli->ID_PEMBELI)
                    ->where('ID_BARANG', $id)
                    ->first();

        if (!$cart) {
            return response()->json(['message' => 'Item tidak ditemukan'], 404);
        }
        
        // Reset the barang status to Available
        $barang = Barang::find($id);
        if ($barang) {
            $barang->STATUS_BARANG = 'Tersedia';
            $barang->save();
        }

        $cart->delete();
        return response()->json(['message' => 'Item berhasil dihapus']);
    }
}