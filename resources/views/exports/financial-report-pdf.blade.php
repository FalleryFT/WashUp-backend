{{-- resources/views/exports/financial-report-pdf.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'DejaVu Sans', Arial, sans-serif;
      font-size: 11px;
      color: #1a1a1a;
      background: #fff;
    }

    /* ── HEADER ── */
    .header {
      background: #0077b6;
      color: #fff;
      padding: 20px 28px 14px;
      margin-bottom: 0;
    }
    .header h1 {
      font-size: 20px;
      font-weight: 800;
      letter-spacing: 0.5px;
    }
    .header p {
      font-size: 12px;
      margin-top: 3px;
      opacity: 0.85;
    }
    .header-sub {
      background: #0096c7;
      color: #fff;
      padding: 8px 28px;
      font-size: 11px;
      font-weight: 600;
    }

    .body { padding: 20px 28px; }

    /* ── STAT CARDS ── */
    .stat-grid {
      display: table;
      width: 100%;
      margin-bottom: 20px;
      border-collapse: separate;
      border-spacing: 8px;
    }
    .stat-cell {
      display: table-cell;
      width: 33.33%;
      background: #eaf6fb;
      border: 1px solid #bee3f8;
      border-radius: 10px;
      padding: 12px 14px;
      vertical-align: top;
    }
    .stat-label {
      font-size: 9px;
      text-transform: uppercase;
      font-weight: 700;
      color: #4a5568;
      letter-spacing: 0.5px;
      margin-bottom: 4px;
    }
    .stat-value {
      font-size: 14px;
      font-weight: 800;
      color: #0077b6;
    }
    .stat-sub {
      font-size: 9px;
      color: #718096;
      margin-top: 3px;
    }

    /* ── TABEL ── */
    .section-title {
      font-size: 12px;
      font-weight: 700;
      color: #374151;
      margin-bottom: 8px;
      padding-bottom: 5px;
      border-bottom: 2px solid #0077b6;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10px;
    }
    thead tr {
      background: #0077b6;
      color: #fff;
    }
    thead th {
      padding: 8px 10px;
      text-align: center;
      font-weight: 600;
      border: 1px solid #005f92;
    }
    tbody tr:nth-child(even) { background: #eaf6fb; }
    tbody tr:nth-child(odd)  { background: #ffffff; }
    tbody td {
      padding: 7px 10px;
      border: 1px solid #d1d5db;
      text-align: center;
      color: #374151;
    }
    tbody td.text-left  { text-align: left; }
    tbody td.text-right { text-align: right; }
    tbody td.mono       { font-family: 'Courier New', monospace; }

    /* Baris Total */
    .row-total td {
      background: #e0f2fe;
      font-weight: 800;
      color: #0077b6;
      border: 1px solid #90cdf4;
    }

    /* ── FOOTER ── */
    .footer {
      margin-top: 22px;
      text-align: right;
      font-size: 9px;
      color: #9ca3af;
    }
  </style>
</head>
<body>

  {{-- Header --}}
  <div class="header">
    <h1>Laporan Keuangan WashUp</h1>
    <p>Periode: {{ $monthName }} {{ $year }}</p>
  </div>
  <div class="header-sub">
    Dicetak pada: {{ \Carbon\Carbon::now()->locale('id')->isoFormat('D MMMM YYYY, HH:mm') }} WIB
  </div>

  <div class="body">

    {{-- Stat Cards --}}
    <table class="stat-grid" style="margin-bottom:18px;">
      <tr>
        <td class="stat-cell">
          <div class="stat-label">Total Pendapatan</div>
          <div class="stat-value">Rp{{ number_format($totalPendapatan, 0, ',', '.') }},00</div>
        </td>
        <td class="stat-cell">
          <div class="stat-label">Jumlah Pesanan</div>
          <div class="stat-value">{{ $jumlahPesanan }} Order</div>
          <div class="stat-sub">Total transaksi bulan ini</div>
        </td>
        <td class="stat-cell">
          <div class="stat-label">Rata-Rata per Transaksi</div>
          <div class="stat-value">Rp{{ number_format($rataRata, 0, ',', '.') }},00</div>
        </td>
      </tr>
    </table>

    {{-- Tabel Transaksi --}}
    <div class="section-title">Rincian Transaksi</div>
    <table>
      <thead>
        <tr>
          <th style="width:36px;">No</th>
          <th>Tanggal</th>
          <th>No Nota</th>
          <th>Pelanggan</th>
          <th>Nominal</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($transactions as $i => $t)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $t['tanggal'] }}</td>
            <td class="mono">{{ $t['nota'] }}</td>
            <td class="text-left">{{ $t['pelanggan'] }}</td>
            <td class="text-right">Rp{{ number_format($t['nominal'], 0, ',', '.') }},00</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" style="text-align:center; color:#9ca3af; padding:20px;">
              Tidak ada data transaksi untuk periode ini.
            </td>
          </tr>
        @endforelse

        {{-- Baris total --}}
        @if(count($transactions) > 0)
          <tr class="row-total">
            <td colspan="4" style="text-align:right; font-weight:700;">TOTAL</td>
            <td class="text-right">Rp{{ number_format($totalPendapatan, 0, ',', '.') }},00</td>
          </tr>
        @endif
      </tbody>
    </table>

    <div class="footer">
      WashUp Laundry — Jl. Kutai Utara, Sumber, Banjarsari, Solo &nbsp;|&nbsp; washuplaundry@gmail.com
    </div>
  </div>

</body>
</html>
