<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Request Donasi</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            font-size: 12px;
            margin: 30px;
            color: #333;
        }

        h2, h3, h4 {
            text-align: center;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 12px;
            margin-bottom: 10px;
        }

        .meta {
            margin-top: 10px;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead th {
            background-color: #4CAF50;
            color: white;
            text-align: center;
            padding: 8px;
            border: 1px solid #ccc;
        }

        tbody td {
            padding: 8px;
            border: 1px solid #ccc;
            text-align: center;
        }

        tbody td:first-child {
            text-align: left;
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
    <h3 style="margin-top: 20px;">LAPORAN REQUEST DONASI</h3>
    <p class="meta"><strong>Tanggal Cetak:</strong> {{ $tanggalCetak->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>ID Organisasi</th>
                <th>Nama Organisasi</th>
                <th>Alamat</th>
                <th>Nama Barang</th>
                <th>Deskripsi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($requestDonasi as $r)
                <tr>
                    <td style="text-align: left;">{{ $r->organisasi->ID_ORGANISASI ?? '-' }}</td>
                    <td>{{ $r->organisasi->NAMA_ORGANISASI ?? '-' }}</td>
                    <td>{{ $r->organisasi->ALAMAT ?? '-' }}</td>
                    <td>{{ $r->NAMA_BARANG }}</td>
                    <td>{{ $r->DESKRIPSI }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px;">Tidak ada data request donasi</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh sistem ReUse Mart
    </div>

</body>
</html>
