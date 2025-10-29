<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Refund Receipt #{{ $refund->id }}</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    body{font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:16px; color:#111;}
    .box{max-width:560px;margin:0 auto;border:1px solid #eee;border-radius:12px; padding:16px;}
    h1{font-size:18px;margin:0 0 8px}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{font-size:13px;padding:6px 0;border-bottom:1px dashed #ddd;text-align:left}
    tfoot td{border:0;padding-top:6px}
    .right{text-align:right}
    .muted{color:#666}
    .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid #ddd;text-decoration:none;color:#111}
    @media print {.no-print{display:none}}
  </style>
</head>
<body>
<div class="box">
  <div class="no-print" style="text-align:right;margin-bottom:8px">
    <a href="javascript:window.print()" class="btn">Unduh / Cetak</a>
  </div>

  <h1>Kasirku</h1>
  <div class="muted" style="font-size:13px">
    <div>Jenis: <b>{{ $refund->mode === 'exchange' ? 'Tukar Barang' : 'Refund Uang' }}</b></div>
    <div>Kode Struk: <b>{{ $refund->sale->code ?? '—' }}</b></div>
    <div>Tanggal: {{ $refund->created_at->format('d/m/Y H:i') }}</div>
    <div>Kasir: {{ $refund->processedBy->name ?? '—' }}</div>
    @if($refund->notes)<div>Catatan: {{ $refund->notes }}</div>@endif
  </div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th class="right">Harga</th>
        <th class="right">Qty</th>
        <th class="right">Subtotal</th>
        <th class="right">Auto</th>
        <th class="right">Voucher</th>
        <th class="right">DPP</th>
        <th class="right">Pajak</th>
        <th class="right">Kembali</th>
      </tr>
    </thead>
    <tbody>
      @foreach($refund->items as $it)
      <tr>
        <td>{{ $it->nama_barang }}<div class="muted" style="font-size:12px">Kode: {{ $it->kode_barang }} • {{ $it->condition === 'damaged' ? 'Rusak' : 'Normal' }}</div></td>
        <td class="right">Rp {{ number_format($it->harga_jual,0,',','.') }}</td>
        <td class="right">{{ $it->qty_refund }}</td>
        <td class="right">Rp {{ number_format($it->line_subtotal,0,',','.') }}</td>
        <td class="right">- Rp {{ number_format($it->auto_share,0,',','.') }}</td>
        <td class="right">- Rp {{ number_format($it->voucher_share,0,',','.') }}</td>
        <td class="right">Rp {{ number_format($it->dpp_refund,0,',','.') }}</td>
        <td class="right">Rp {{ number_format($it->tax_refund,0,',','.') }}</td>
        <td class="right"><b>Rp {{ number_format($it->refund_amount,0,',','.') }}</b></td>
      </tr>
      @endforeach
    </tbody>
    <tfoot>
      <tr><td colspan="8" class="right">Subtotal Retur</td><td class="right">Rp {{ number_format($refund->subtotal_refund,0,',','.') }}</td></tr>
      <tr><td colspan="8" class="right">Diskon Otomatis</td><td class="right">- Rp {{ number_format($refund->auto_share,0,',','.') }}</td></tr>
      <tr><td colspan="8" class="right">Diskon Voucher</td><td class="right">- Rp {{ number_format($refund->voucher_share,0,',','.') }}</td></tr>
      <tr><td colspan="8" class="right">Pajak</td><td class="right">Rp {{ number_format($refund->tax_refund,0,',','.') }}</td></tr>
      <tr>
        <td colspan="8" class="right"><b>{{ $refund->mode==='exchange' ? 'Selisih' : 'Uang Dikembalikan' }}</b></td>
        <td class="right"><b>Rp {{ number_format($refund->mode==='exchange' ? 0 : $refund->refund_total,0,',','.') }}</b></td>
      </tr>
    </tfoot>
  </table>
</div>
</body>
</html>
