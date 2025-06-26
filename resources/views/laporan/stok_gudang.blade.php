<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Stok Gudang</title>
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

        p.meta {
            margin: 10px 0 20px 0;
            text-align: center;
            font-size: 13px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 11px;
        }

        thead th {
            background-color: #4CAF50;
            color: white;
            padding: 8px;
            border: 1px solid #ccc;
            text-align: center;
        }

        tbody td {
            padding: 7px;
            border: 1px solid #ccc;
            text-align: center;
        }

        tbody td:first-child {
            text-align: left;
        }

        tfoot td {
            font-weight: bold;
            background-color: #f9f9f9;
        }

        .footer {
            margin-top: 40px;
            text-align: right;
            font-size: 11px;
        }
    </style>
</head>
<body>

    <h2>ReUse Mart</h2>
    <h4>Jl. Green Eco Park No. 456 Yogyakarta</h4>
    <h3 style="margin-top: 15px;">LAPORAN STOK GUDANG</h3>
    <p class="meta">Tanggal Cetak: {{ $tanggalCetak->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>ID Produk</th>
                <th>Nama Produk</th>
                <th>ID Penitip</th>
                <th>Nama Penitip</th>
                <th>Tanggal Masuk</th>
                <th>Perpanjangan</th>
                <th>ID Pegawai QC</th>
                <th>Nama Pegawai QC</th>
                <th>Nama Hunter</th>
                <th>Harga</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($barang as $b)
                <tr>
                    <td>{{ $b->ID_BARANG }}</td>
                    <td>{{ $b->NAMA_BARANG }}</td>
                    <td>{{ $b->ID_PENITIP }}</td>
                    <td>{{ $b->penitip->NAMA_PENITIP ?? '-' }}</td>
                    <td>
                        {{ optional($b->TANGGAL_MASUK)
                            ? \Carbon\Carbon::parse($b->TANGGAL_MASUK)->format('d/m/Y')
                            : '-' }}
                    </td>
                    <td>
                        @if ($b->detailPenitipan && $b->detailPenitipan->penitipan)
                            {{ $b->detailPenitipan->penitipan->PERPANJANGAN ? 'Ya' : 'Tidak' }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $b->ID_PEGAWAI ?? '-' }}</td>
                    <td>
                        @if ($b->pegawai && $b->pegawai->jabatan && strtolower($b->pegawai->jabatan->NAMA_JABATAN) === 'gudang')
                            {{ $b->pegawai->NAMA_PEGAWAI }}
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if (
                            $b->detailPenitipan &&
                            $b->detailPenitipan->penitipan &&
                            $b->detailPenitipan->penitipan->hunter &&
                            strtolower($b->detailPenitipan->penitipan->hunter->jabatan->NAMA_JABATAN) === 'hunter'
                        )
                            {{ $b->detailPenitipan->penitipan->hunter->NAMA_PEGAWAI }}
                        @else
                            -
                        @endif
                    </td>
                    <td>Rp{{ number_format($b->HARGA, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dicetak oleh sistem ReUse Mart
    </div>

</body>
</html>
