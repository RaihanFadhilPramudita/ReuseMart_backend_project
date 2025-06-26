<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Penjualan Bulanan</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            font-size: 12px;
            margin: 30px;
            color: #333;
        }

        h2, h3, h4, p {
            text-align: center;
            margin: 0;
        }

        p.meta {
            margin: 10px 0 20px 0;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        thead th {
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: 1px solid #ccc;
        }

        tbody td, tbody th {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: center;
        }

        tbody td:first-child {
            text-align: left;
        }

        tfoot th {
            background-color: #f9f9f9;
            font-weight: bold;
        }

        img.chart {
            margin-top: 30px;
            max-height: 400px;
            width: 100%;
            border: 1px solid #ddd;
        }

        .footer {
            margin-top: 40px;
            font-size: 11px;
            text-align: right;
        }
    </style>
</head>
<body>

    <h2>ReUse Mart</h2>
    <h4 class="subtitle">Jl. Green Eco Park No. 456 Yogyakarta</h4>
    <p class="meta">Laporan Penjualan Bulanan Tahun {{ $tahun }}</p>
    <p class="meta">Tanggal Cetak: {{ \Carbon\Carbon::parse($tanggalCetak)->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Bulan</th>
                <th>Jumlah Transaksi</th>
                <th>Total Penjualan</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
                <tr>
                    <td style="text-align: left;">{{ $row['bulan'] }}</td>
                    <td>{{ $row['jumlah_terjual'] }}</td>
                    <td>Rp{{ number_format($row['penjualan_kotor'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th>Total</th>
                <th>{{ $totalBarang }}</th>
                <th>Rp{{ number_format($total, 0, ',', '.') }}</th>
            </tr>
        </tfoot>
    </table>

    <h3 style="margin-top: 40px;">Grafik Penjualan</h3>
    <img class="chart" src="{{ $chartImage }}" alt="Grafik Penjualan">

    <div class="footer">
        Dicetak oleh sistem ReUse Mart
    </div>

</body>
</html>
