<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Nota Penjualan</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 13px;
        }

        .box {
            border: 1px solid #000;
            padding: 16px;
            width: 100%;
        }

        .judul {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        td {
            padding: 4px;
            vertical-align: top;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .mt-1 {
            margin-top: 5px;
        }

        .mt-2 {
            margin-top: 10px;
        }

        .mt-3 {
            margin-top: 15px;
        }

        .signature {
            height: 80px;
            border-bottom: 1px dotted #000;
        }

        .note {
            font-size: 11px;
            color: gray;
            margin-top: 5px;
        }
    </style>
</head>

<body>

    <div class="judul">Nota Penjualan (dibawa oleh kurir)</div>

    <div class="box">
        <strong>ReUse Mart</strong><br>
        Jl. Green Eco Park No. 456 Yogyakarta

        <table class="mt-2">
            <tr>
                <td width="30%">No Nota</td>
                <td>: {{ $transaksi->NO_NOTA ?? '-' }}</td>
            </tr>
            <tr>
                <td>Tanggal pesan</td>
                <td>: {{ date('d/m/Y H:i', strtotime($transaksi->WAKTU_PESAN)) }}</td>
            </tr>
            <tr>
                <td>Lunas pada</td>
                <td>: {{ date('d/m/Y H:i', strtotime($transaksi->WAKTU_LUNAS ?? now())) }}</td>
            </tr>
            <tr>
                <td>Tanggal kirim</td>
                <td>: {{ date('d/m/Y', strtotime($transaksi->WAKTU_KIRIM ?? now())) }}</td>
            </tr>
        </table>

        <div class="mt-2">
            <strong>Pembeli</strong> : {{ $transaksi->pembeli->EMAIL ?? '-' }} / {{ $transaksi->pembeli->NAMA_PEMBELI ?? '-' }}<br>
            {{ $transaksi->alamat->ALAMAT_LENGKAP ?? '-' }}<br>
            Delivery: Kurir ReUseMart ({{ $transaksi->pegawai->NAMA_PEGAWAI ?? '-' }})
        </div>

        <table class="mt-2">
            @foreach($transaksi->detailTransaksi as $item)
            <tr>
                <td>{{ $item->barang->NAMA_BARANG ?? '-' }}</td>
                <td class="right">Rp{{ number_format($item->barang->HARGA, 0, ',', '.') }}</td>
            </tr>
            @endforeach

            <tr>
                <td colspan="2">
                    <hr>
                </td>
            </tr>
            <<tr>
                <td><strong>Total Harga Barang</strong></td>
                <td class="right"><strong>Rp{{ number_format($transaksi->TOTAL_HARGA ?? 0, 0, ',', '.') }}</strong></td>
                </tr>
                @if($transaksi->JENIS_DELIVERY === 'Antar')
                <tr>
                    <td>Ongkos Kirim</td>
                    <td class="right">Rp{{ number_format($transaksi->ONGKOS_KIRIM ?? 0, 0, ',', '.') }}</td>
                </tr>
                @endif
                <tr>
                    <td>Potongan {{ $transaksi->POTONGAN_POIN ?? 0 }} poin</td>
                    <td class="right">- Rp{{ number_format($transaksi->POTONGAN_POIN ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td colspan="2">
                        <hr>
                    </td>
                </tr>
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="right"><strong>Rp{{ number_format($transaksi->TOTAL_AKHIR ?? 0, 0, ',', '.') }}</strong></td>
                </tr>
        </table>

        <div class="mt-2">
            <p>Poin dari pesanan ini: {{ $poin }}</p>
            <p>Total poin customer: {{ $transaksi->pembeli->TOTAL_POIN ?? '-' }}</p>
        </div>

        <div class="mt-2">
            <p>QC oleh: {{ $transaksi->pegawai->NAMA_PEGAWAI ?? '-' }}</p>
        </div>

        <div class="mt-3">
            Diterima oleh:
            <div class="signature"></div>
            Tanggal: .......................................
        </div>
    </div>

</body>

</html>