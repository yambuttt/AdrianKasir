@extends('layouts.admin')
@section('title','Laporan | Kasirku')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Laporan</h1>
    <div class="flex gap-2">
      <a href="{{ route('admin.reports.export.csv', request()->query()) }}" class="px-3 py-1.5 border rounded">Export Excel (CSV)</a>
      <a href="{{ route('admin.reports.export.print', request()->query()) }}" target="_blank" class="px-3 py-1.5 border rounded">Export PDF (Cetak)</a>
    </div>
  </div>

  <form method="GET" class="flex flex-wrap items-end gap-3">
    <div>
      <label class="text-xs text-gray-600">Rentang</label>
      <select name="range" class="border rounded px-3 py-2">
        @foreach(['day'=>'Hari ini','week'=>'Minggu ini','month'=>'Bulan ini','year'=>'Tahun ini'] as $k=>$v)
          <option value="{{ $k }}" {{ $range===$k?'selected':'' }}>{{ $v }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="text-xs text-gray-600">Tanggal Acuan</label>
      <input type="date" name="at" value="{{ $anchor->format('Y-m-d') }}" class="border rounded px-3 py-2">
    </div>
    <button class="px-4 py-2 bg-indigo-600 text-white rounded">Terapkan</button>
  </form>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Omzet Kotor</div>
      <div class="text-xl font-semibold">Rp {{ number_format($summary['gross'],0,',','.') }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Refund Uang</div>
      <div class="text-xl font-semibold text-red-600">- Rp {{ number_format($summary['refund'],0,',','.') }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Omzet Nett</div>
      <div class="text-xl font-semibold">Rp {{ number_format($summary['nett'],0,',','.') }}</div>
    </div>
  </div>

  {{-- Grafik Nett --}}
  <div class="bg-white rounded shadow p-4">
    <canvas id="chartNett" height="120"></canvas>
  </div>

  {{-- Tabel Transaksi (nett per transaksi) --}}
  <div class="bg-white rounded shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left">Waktu</th>
          <th class="px-4 py-2 text-left">Kode</th>
          <th class="px-4 py-2 text-left">Kasir</th>
          <th class="px-4 py-2 text-right">Subtotal</th>
          <th class="px-4 py-2 text-right">Diskon</th>
          <th class="px-4 py-2 text-right">Pajak</th>
          <th class="px-4 py-2 text-right">Total</th>
          <th class="px-4 py-2 text-right">Refund Uang</th>
          <th class="px-4 py-2 text-right">Total Nett</th>
        </tr>
      </thead>
      <tbody>
        @foreach($sales as $s)
          <tr class="border-t">
            <td class="px-4 py-2">{{ $s->created_at->format('d M Y H:i') }}</td>
            <td class="px-4 py-2 font-mono">{{ $s->code }}</td>
            <td class="px-4 py-2">{{ optional($s->user)->name }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($s->subtotal,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">- Rp {{ number_format(($s->auto_discount + $s->voucher_discount),0,',','.') }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($s->tax_amount,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($s->total,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($s->refund_cash, 0, ',', '.') }}</td>
            <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($s->net_total,0,',','.') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div>{{ $sales->links() }}</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function(){
    const ctx = document.getElementById('chartNett');
    const chart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: {!! json_encode($labels) !!},
        datasets: [{
          label: 'Omzet Nett',
          data: {!! json_encode($dataNett) !!},
          tension: 0.25,
          fill: false
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false }},
        scales: {
          y: { ticks: { callback: (v)=> 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } }
        }
      }
    });
  })();
</script>
@endpush
