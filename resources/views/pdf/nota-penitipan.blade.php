<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Nota Penitipan Barang</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
            color: #2c3e50;
        }
        .subtitle {
            text-align: center;
            margin-bottom: 20px;
            color: #555;
            font-size: 12px;
        }
        .info p {
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 11.5px;
        }
        th {
            background-color: #f1f1f1;
            text-align: left;
            padding: 6px;
            border: 1px solid #ccc;
        }
        td {
            padding: 6px;
            border: 1px solid #ccc;
        }
        .footer {
            margin-top: 40px;
        }
        .signature {
            margin-top: 60px;
        }
        .signature p {
            margin: 0;
            text-align: left;
        }
        .text-bold {
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="title">NOTA PENITIPAN BARANG</div>
    <div class="subtitle">ReuseMart - Titip Jual Barang Bekas Berkualitas</div>

    @php
        $barangPertama = $penitipan->detailPenitipan->first()->barang ?? null;
        $tanggalMasuk = $barangPertama?->TANGGAL_MASUK;
        $tanggalKadaluarsa = $barangPertama?->detailPenitipan->penitipan->TANGGAL_KADALUARSA ?? null;
    @endphp

    <div class="info">
        <p><span class="text-bold">No Nota:</span> {{ now()->format('y.m.') . str_pad($penitipan->ID_PENITIPAN, 3, '0', STR_PAD_LEFT) }}</p>
        <p><span class="text-bold">Tanggal Titip:</span> {{ \Carbon\Carbon::parse($tanggalMasuk)->format('d F Y') }}</p>
        <p><span class="text-bold">Kadaluarsa:</span> {{ \Carbon\Carbon::parse($tanggalKadaluarsa)->format('d F Y') }}</p>
        <p><span class="text-bold">Penitip:</span> {{ $penitipan->penitip->NAMA_PENITIP }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Nama Barang</th>
                <th>Harga</th>
                <th>Garansi</th>
                <th>Petugas QC</th>
            </tr>
        </thead>
        <tbody>
            @foreach($penitipan->detailPenitipan as $detail)
                <tr>
                    <td>{{ $detail->barang->NAMA_BARANG }}</td>
                    <td>Rp {{ number_format($detail->barang->HARGA, 0, ',', '.') }}</td>
                    <td>
                        {{ $detail->barang->TANGGAL_GARANSI 
                            ? 'ON ' . \Carbon\Carbon::parse($detail->barang->TANGGAL_GARANSI)->format('M Y')
                            : '-' }}
                    </td>
                    <td>{{ $detail->barang->pegawai->NAMA_PEGAWAI ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <div class="signature">
            <p><strong>Diterbitkan oleh:</strong></p>
            <p>{{ $pegawai->NAMA_PEGAWAI ?? '-' }}</p>
        </div>
    </div>
</body>
</html>
