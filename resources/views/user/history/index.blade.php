@extends('layouts.user')
@section('title','Riwayat | Kasirku')

@section('content')
<div class="space-y-6">
  <h1 class="text-2xl font-semibold">Riwayat Transaksi Saya</h1>

  <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="date" name="from" value="{{ request('from') }}" class="border rounded px-3 py-2">
    <input type="date" name="to"   value="{{ request('to') }}"   class="border rounded px-3 py-2">
    <div class="md:col-span-2 flex gap-2">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="mis. TRX-251021-XXXX" class="border rounded px-3 py-2 w-full">
      <button class="px-4 py-2 bg-indigo-600 text-white rounded">Terapkan</button>
    </div>
    <a href="{{ route('user.history.index') }}" class="px-4 py-2 border rounded">Reset</a>
  </form>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Total Transaksi</div>
      <div class="text-xl font-semibold">{{ number_format($summary->total_orders ?? 0) }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Omzet (Nett setelah refund uang)</div>
      <div class="text-xl font-semibold">Rp {{ number_format($summary->net_revenue ?? 0,0,',','.') }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Total Diskon</div>
      <div class="text-xl font-semibold text-red-600">- Rp {{ number_format(($summary->auto_sum ?? 0) + ($summary->voucher_sum ?? 0),0,',','.') }}</div>
    </div>
  </div>

  <div class="bg-white rounded shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left">Waktu</th>
          <th class="px-4 py-2 text-left">Kode</th>
          <th class="px-4 py-2 text-right">Subtotal</th>
          <th class="px-4 py-2 text-right">Diskon</th>
          <th class="px-4 py-2 text-right">Total</th>
          <th class="px-4 py-2 text-right">Total Nett</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        @foreach($sales as $s)
        <tr class="border-t">
          <td class="px-4 py-2">{{ $s->created_at->format('d M Y H:i') }}</td>
          <td class="px-4 py-2 font-mono">{{ $s->code }}</td>
          <td class="px-4 py-2 text-right">Rp {{ number_format($s->calc_subtotal,0,',','.') }}</td>
          <td class="px-4 py-2 text-right">- Rp {{ number_format($s->calc_auto_discount + $s->calc_voucher_discount,0,',','.') }}</td>
          <td class="px-4 py-2 text-right">Rp {{ number_format($s->calc_grand_total,0,',','.') }}</td>
          <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($s->net_total,0,',','.') }}</td>
          <td class="px-4 py-2 text-right"><a href="{{ route('user.history.show',$s) }}" class="text-indigo-600 underline">Detail</a></td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div>{{ $sales->links() }}</div>
</div>
@endsection
