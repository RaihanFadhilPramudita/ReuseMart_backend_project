<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan per Kategori Barang</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }

        h1, h2, h3, h4 {
            text-align: center;
            margin: 0;
        }

        .info-header {
            text-align: center;
            margin-bottom: 10px;
        }

        .info-header p {
            margin: 3px 0;
        }

        .report-meta {
            margin-top: 10px;
            text-align: center;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 12px;
        }

        th {
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 8px;
            border: 1px solid #ddd;
        }

        td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        td:first-child {
            text-align: left;
        }

        tfoot td {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .signature {
            margin-top: 50px;
            text-align: right;
            font-size: 12px;
        }
    </style>
</head>
<body>

    <div class="info-header">
        <h3>ReUse Mart</h3>
        <p>Jl. Green Eco Park No. 456 Yogyakarta</p>
    </div>

    <h2>LAPORAN PENJUALAN PER KATEGORI BARANG</h2>

    <div class="report-meta">
        <p><strong>Tahun:</strong> {{ $tahun }}</p>
        <p><strong>Tanggal Cetak:</strong> {{ $tanggalCetak }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kategori</th>
                <th>Jumlah Item Terjual</th>
                <th>Jumlah Item Gagal Terjual</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $row)
            <tr>
                <td>{{ $row['kategori'] }}</td>
                <td>{{ $row['terjual'] }}</td>
                <td>{{ $row['gagal'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signature">
        <p>Yogyakarta, {{ $tanggalCetak }}</p>
        <p><strong>Reuse Mart</strong></p>
    </div>

</body>
</html>
