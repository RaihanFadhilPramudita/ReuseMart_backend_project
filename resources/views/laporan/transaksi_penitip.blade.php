<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Laporan Transaksi Penitip</title>
    <style>
        body {
            font-family: sans-serif;
            font-size: 12px;
        }

        h2 {
            text-align: center;
            margin: 0;
            color: green;
            font-size: xx-large;
        }

        h4 {
            text-align: center;
            margin: 0;
        }

        .info {
            margin: 15px 0;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
        }

        .right {
            text-align: right;
        }
    </style>
</head>

<body>

    <h2>ReUse Mart</h2>
    <h4>Jl. Green Eco Park No. 456 Yogyakarta</h4>

    <h3 style="text-align: center;">LAPORAN TRANSAKSI PENITIP</h3>

    <div class="info">
        <p><strong>ID Penitip :</strong> {{ $penitip->ID_PENITIP }}</p>
        <p><strong>Nama Penitip :</strong> {{ $penitip->NAMA_PENITIP }}</p>
        <p><strong>Bulan :</strong> {{ $bulan }}</p>
        <p><strong>Tahun :</strong> {{ $tahun }}</p>
        <p><strong>Tanggal cetak :</strong> {{ $tanggalCetak }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>Tanggal Masuk</th>
                <th>Tanggal Laku</th>
                <th>Harga Jual Bersih <br>(sudah dipotong Komisi)</th>
                <th>Bonus terjual cepat</th>
                <th>Pendapatan</th>
            </tr>
        </thead>
        <tbody>
            @php
            $totalBersih = 0;
            $totalBonus = 0;
            $totalPendapatan = 0;
            @endphp
            @foreach($transaksi as $item)
            <tr>
                <td>{{ $item['KODE_PRODUK'] }}</td>
                <td>{{ $item['NAMA_BARANG'] }}</td>
                <td>{{ $item['TANGGAL_MASUK'] ? \Carbon\Carbon::parse($item['TANGGAL_MASUK'])->format('d/m/Y') : '-' }}</td>
                <td>{{ $item['TANGGAL_LAKU'] ? \Carbon\Carbon::parse($item['TANGGAL_LAKU'])->format('d/m/Y') : '-' }}</td>
                <td class="right">{{ number_format($item['HARGA_JUAL_BERSIH'], 0, ',', '.') }}</td>
                <td class="right">{{ number_format($item['BONUS_TERJUAL_CEPAT'], 0, ',', '.') }}</td>
                <td class="right">{{ number_format($item['PENDAPATAN'], 0, ',', '.') }}</td>
            </tr>
            @php
            $totalBersih += $item['HARGA_JUAL_BERSIH'];
            $totalBonus += $item['BONUS_TERJUAL_CEPAT'];
            $totalPendapatan += $item['PENDAPATAN'];
            @endphp
            @endforeach
            <tr>
                <th colspan="4" class="right">TOTAL</th>
                <th class="right">{{ number_format($totalBersih, 0, ',', '.') }}</th>
                <th class="right">{{ number_format($totalBonus, 0, ',', '.') }}</th>
                <th class="right">{{ number_format($totalPendapatan, 0, ',', '.') }}</th>
            </tr>
        </tbody>
    </table>

</body>

</html>