<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Komisi Bulanan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; }
        h2, h4 { text-align: center; }
    </style>
</head>
<body>
    <h2>ReUse Mart</h2>
    <h4>Jl. Green Eco Park No. 456 Yogyakarta</h4>
    <h3 style="text-align: center;">LAPORAN KOMISI BULANAN</h3>
    <p>Tanggal cetak: {{ $tanggalCetak->format('d F Y') }}</p>

    <table>
        <thead>
            <tr>
                <th>Kode Produk</th>
                <th>Nama Produk</th>
                <th>Harga Jual</th>
                <th>Tanggal Masuk</th>
                <th>Tanggal Laku</th>
                <th>Komisi Hunter</th>
                <th>Komisi ReUse Mart</th>
                <th>Bonus Penitip</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalHunter = 0;
                $totalReuse = 0;
                $totalBonus = 0;
            @endphp

            @forelse ($komisi as $k)
                @php
                    $totalHunter += $k->JUMLAH_KOMISI_HUNTER;
                    $totalReuse += $k->JUMLAH_KOMISI_REUSE_MART;
                    $totalBonus += $k->BONUS_PENITIP;
                @endphp
                <tr>
                    <td>{{ $k->ID_BARANG }}</td>
                    <td>{{ $k->barang->NAMA_BARANG ?? '-' }}</td>
                    <td>Rp {{ number_format($k->barang->HARGA ?? 0, 0, ',', '.') }}</td>
                    <td>{{ optional(optional($k->barang->detailPenitipan)->penitipan)->TANGGAL_MASUK 
                        ? \Carbon\Carbon::parse($k->barang->detailPenitipan->penitipan->TANGGAL_MASUK)->format('d/m/Y') 
                        : '-' }}</td>
                    <td>{{ $k->barang->TANGGAL_JUAL 
                        ? \Carbon\Carbon::parse($k->barang->TANGGAL_JUAL)->format('d/m/Y') 
                        : '-' }}</td>
                    <td>Rp {{ number_format($k->JUMLAH_KOMISI_HUNTER, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($k->JUMLAH_KOMISI_REUSE_MART, 0, ',', '.') }}</td>
                    <td>Rp {{ number_format($k->BONUS_PENITIP, 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center;">Tidak ada data komisi bulan ini.</td>
                </tr>
            @endforelse

            <tr>
                <td colspan="5"><strong>Total</strong></td>
                <td><strong>Rp {{ number_format($totalHunter, 0, ',', '.') }}</strong></td>
                <td><strong>Rp {{ number_format($totalReuse, 0, ',', '.') }}</strong></td>
                <td><strong>Rp {{ number_format($totalBonus, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
