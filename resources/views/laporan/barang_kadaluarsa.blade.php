<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Barang Kadaluarsa</title>
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
            color: #fff;
            text-align: center;
            padding: 8px;
            border: 1px solid #ddd;
        }

        tbody td {
            padding: 8px;
            border: 1px solid #ddd;
            text-align: center;
        }

        tbody td:first-child {
            text-align: left;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            font-style: italic;
        }

        .signature {
            margin-top: 50px;
            text-align: right;
        }

        .signature p {
            margin: 5px 0;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            margin-top: 50px;
            text-align: center;
        }
    </style>
</head>
<body>

    <h2>ReUse Mart</h2>
    <h4 class="subtitle">Jl. Green Eco Park No. 456 Yogyakarta</h4>
    <h3 style="margin-top: 20px;">LAPORAN BARANG KADALUARSA</h3>

    <p class="meta"><strong>Tanggal Cetak:</strong> {{ $tanggalCetak->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>ID Penitip</th>
                <th>Nama Penitip</th>
                <th>Tanggal Masuk</th>
                <th>Tanggal Kadaluarsa</th>
                <th>Batas Ambil</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($barangKadaluarsa as $b)
                <tr>
                    <td>{{ $b->ID_BARANG }}</td>
                    <td style="text-align: left;">{{ $b->NAMA_BARANG }}</td>
                    <td>{{ $b->ID_PENITIP ?? '-' }}</td>
                    <td>{{ $b->penitip->NAMA_PENITIP ?? '-' }}</td>
                    <td>{{ optional(optional($b->detailPenitipan)->penitipan)->TANGGAL_MASUK 
                        ? \Carbon\Carbon::parse($b->detailPenitipan->penitipan->TANGGAL_MASUK)->format('d/m/Y') 
                        : '-' }}</td>
                    <td>{{ optional(optional($b->detailPenitipan)->penitipan)->TANGGAL_KADALUARSA 
                        ? \Carbon\Carbon::parse($b->detailPenitipan->penitipan->TANGGAL_KADALUARSA)->format('d/m/Y') 
                        : '-' }}</td>
                    <td>{{ optional(optional($b->detailPenitipan)->penitipan)->TANGGAL_BATAS_AMBIL 
                        ? \Carbon\Carbon::parse($b->detailPenitipan->penitipan->TANGGAL_BATAS_AMBIL)->format('d/m/Y') 
                        : '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="no-data">Tidak ada barang kadaluarsa</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="signature">
        <p>Yogyakarta, {{ $tanggalCetak->format('d F Y') }}</p>
        <p><strong>Manajer Gudang</strong></p>
        <div class="signature-line">(_______________________)</div>
    </div>

</body>
</html>
