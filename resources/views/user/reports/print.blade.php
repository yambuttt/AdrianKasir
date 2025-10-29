<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Laporan Transaksi Saya (Cetak)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;margin:24px;color:#111}
    h1{margin:0 0 6px}
    .muted{color:#666;font-size:12px}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #eee;padding:6px 0;text-align:left;font-size:13px}
    tfoot td{border:0;padding-top:6px}
    .right{text-align:right}
    .no-print{margin-bottom:12px}
    @media print{.no-print{display:none}}
  </style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<h1>Laporan Transaksi Saya</h1>
<div class="muted">
  Rentang: {{ strtoupper($range) }} • {{ $start->format('d/m/Y') }} – {{ $end->subSecond()->format('d/m/Y') }}
</div>

<table>
  <thead>
    <tr>
      <th>Waktu</th><th>Kode</th>
      <th class="right">Subtotal</th><th class="right">Diskon</th>
      <th class="right">Pajak</th><th class="right">Total</th>
      <th class="right">Refund Uang</th><th class="right">Total Nett</th>
    </tr>
  </thead>
  <tbody>
    @foreach($sales as $s)
    @php $refund = (int)($refundMap[$s->id] ?? 0); @endphp
    <tr>
      <td>{{ $s->created_at->format('Y-m-d H:i') }}</td>
      <td>{{ $s->code }}</td>
      <td class="right">{{ number_format($s->subtotal,0,',','.') }}</td>
      <td class="right">- {{ number_format($s->auto_discount + $s->voucher_discount,0,',','.') }}</td>
      <td class="right">{{ number_format($s->tax_amount,0,',','.') }}</td>
      <td class="right">{{ number_format($s->total,0,',','.') }}</td>
      <td class="right">{{ number_format($refund,0,',','.') }}</td>
      <td class="right"><b>{{ number_format((int)$s->total - $refund,0,',','.') }}</b></td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr><td colspan="7" class="right"><b>Omzet Kotor</b></td><td class="right"><b>{{ number_format($gross,0,',','.') }}</b></td></tr>
    <tr><td colspan="7" class="right"><b>Refund Uang</b></td><td class="right"><b>- {{ number_format($refund,0,',','.') }}</b></td></tr>
    <tr><td colspan="7" class="right"><b>Omzet Nett</b></td><td class="right"><b>{{ number_format($nett,0,',','.') }}</b></td></tr>
  </tfoot>
</table>
</body>
</html>
