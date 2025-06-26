<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Donasi Barang</title>
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

        .signature {
            margin-top: 50px;
            text-align: right;
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
    <h3 style="margin-top: 20px;">LAPORAN DONASI BARANG</h3>
    <p class="meta"><strong>Tanggal Cetak:</strong> {{ $tanggalCetak->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Nama Produk</th>
                <th>ID Penitip</th>
                <th>Nama Penitip</th>
                <th>Tanggal Donasi</th>
                <th>Organisasi</th>
                <th>Nama Penerima</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($donasi as $d)
            <tr>
                <td style="text-align: left;">{{ $d->barang->NAMA_BARANG ?? '-' }}</td>
                <td>{{ $d->barang->ID_PENITIP ?? '-' }}</td>
                <td>{{ $d->barang->penitip->NAMA_PENITIP ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($d->TANGGAL_DONASI)->format('d/m/Y') }}</td>
                <td>{{ $d->requestDonasi->organisasi->NAMA_ORGANISASI ?? '-' }}</td>
                <td>{{ $d->NAMA_PENERIMA ?? '-' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data donasi</td>
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
