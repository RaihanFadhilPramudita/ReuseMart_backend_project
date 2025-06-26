<!DOCTYPE html>
<html>
<head>
    <title>Nota Penjualan</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: left; margin-bottom: 20px; }
        .details { margin-bottom: 20px; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th, .items td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .totals { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2>ReUse Mart</h2>
        <p>Jl. Green Eco Park No. 456 Yogyakarta</p>
    </div>
    
    <div class="details">
        <p><strong>No Nota:</strong> {{ $transaksi->NO_NOTA }}</p>
        <p><strong>Tanggal pesan:</strong> {{ date('d/m/Y H:i', strtotime($transaksi->WAKTU_PESAN)) }}</p>
        <p><strong>Lunas pada:</strong> {{ date('d/m/Y H:i', strtotime($transaksi->WAKTU_BAYAR)) }}</p>
        <p><strong>Tanggal kirim:</strong> {{ date('d/m/Y', strtotime($transaksi->pengiriman->TANGGAL_KIRIM ?? $transaksi->WAKTU_BAYAR)) }}</p>
        
        <p><strong>Pembeli:</strong> {{ $transaksi->pembeli->EMAIL }} / {{ $transaksi->pembeli->NAMA_PEMBELI }}</p>
        <p>{{ $transaksi->alamat->ALAMAT_LENGKAP ?? '' }}</p>
        <p>{{ $transaksi->alamat->KECAMATAN ?? '' }}, {{ $transaksi->alamat->KOTA ?? '' }}, {{ $transaksi->alamat->KODE_POS ?? '' }}</p>
        <p><strong>Delivery:</strong> Kurir ReUseMart ({{ $transaksi->pengiriman->pegawai->NAMA_PEGAWAI ?? 'Belum ditentukan' }})</p>
    </div>
    
    <table class="items">
        <tr>
            <th>Produk</th>
            <th>Harga</th>
        </tr>
        @foreach($transaksi->detailTransaksi as $detail)
        <tr>
            <td>{{ $detail->barang->NAMA_BARANG }}</td>
            <td>{{ number_format($detail->barang->HARGA, 0, ',', '.') }}</td>
        </tr>
        @endforeach
    </table>
    
    <div class="totals">
        <p><strong>Total:</strong> {{ number_format($transaksi->TOTAL_HARGA, 0, ',', '.') }}</p>
        <p><strong>Ongkos Kirim:</strong> {{ number_format($transaksi->ONGKOS_KIRIM, 0, ',', '.') }}</p>
        <p><strong>Total:</strong> {{ number_format($transaksi->TOTAL_HARGA + $transaksi->ONGKOS_KIRIM, 0, ',', '.') }}</p>
        @if($transaksi->POTONGAN_POIN > 0)
        <p><strong>Potongan {{ floor($transaksi->POTONGAN_POIN / 10000) * 100 }} poin:</strong> -{{ number_format($transaksi->POTONGAN_POIN, 0, ',', '.') }}</p>
        @endif
        <p><strong>Total:</strong> {{ number_format($transaksi->TOTAL_AKHIR, 0, ',', '.') }}</p>
        <p>Poin dari pesanan ini: {{ $transaksi->POIN_DIDAPAT }}</p>
        <p>Total poin customer: {{ $transaksi->pembeli->POIN }}</p>
    </div>
    
    <p><strong>QC oleh:</strong> {{ $transaksi->pegawai->NAMA_PEGAWAI }} ({{ $transaksi->pegawai->ID_PEGAWAI }})</p>
    
    <div style="margin-top: 50px;">
        <p><strong>Diterima oleh:</strong></p>
        <p>(.............................................)</p>
        <p>Tanggal: ...................................</p>
    </div>
</body>
</html>