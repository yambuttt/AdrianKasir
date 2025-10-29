@extends('layouts.admin')
@section('title','Refund | Kasirku')

@section('content')
<div class="space-y-6">
  <h1 class="text-2xl font-semibold">Daftar Refund</h1>

  <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="date" name="from" value="{{ request('from') }}" class="border rounded px-3 py-2">
    <input type="date" name="to" value="{{ request('to') }}" class="border rounded px-3 py-2">
    <select name="mode" class="border rounded px-3 py-2">
      <option value="">Semua Mode</option>
      <option value="refund" {{ request('mode')==='refund'?'selected':'' }}>Refund Uang</option>
      <option value="exchange" {{ request('mode')==='exchange'?'selected':'' }}>Tukar Barang</option>
    </select>
    <select name="cashier_id" class="border rounded px-3 py-2">
      <option value="">Semua Kasir</option>
      @foreach($cashiers as $c)
        <option value="{{ $c->id }}" {{ (int)request('cashier_id')===$c->id?'selected':'' }}>{{ $c->name }}</option>
      @endforeach
    </select>
    <div class="flex gap-2">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kode struk (TRX-..)"
             class="border rounded px-3 py-2 flex-1">
      <button class="px-4 py-2 bg-indigo-600 text-white rounded">Terapkan</button>
    </div>
  </form>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Total Refund</div>
      <div class="text-xl font-semibold">{{ number_format($summary->total_refunds ?? 0) }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Uang Dikembalikan</div>
      <div class="text-xl font-semibold text-red-600">- Rp {{ number_format($summary->money_returned ?? 0,0,',','.') }}</div>
    </div>
    <div class="p-4 bg-white rounded shadow">
      <div class="text-sm text-gray-500">Subtotal Retur</div>
      <div class="text-xl font-semibold">Rp {{ number_format($summary->subtotal_sum ?? 0,0,',','.') }}</div>
    </div>
  </div>

  <div class="bg-white rounded shadow overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50">
        <tr>
          <th class="px-4 py-2 text-left">Waktu</th>
          <th class="px-4 py-2 text-left">Kode Struk</th>
          <th class="px-4 py-2 text-left">Mode</th>
          <th class="px-4 py-2 text-right">Subtotal</th>
          <th class="px-4 py-2 text-right">Auto/Voucher</th>
          <th class="px-4 py-2 text-right">DPP</th>
          <th class="px-4 py-2 text-right">Pajak</th>
          <th class="px-4 py-2 text-right">Uang Kembali</th>
          <th class="px-4 py-2 text-left">Diproses Oleh</th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        @forelse($refunds as $r)
          <tr class="border-t">
            <td class="px-4 py-2">{{ $r->created_at->format('d M Y H:i') }}</td>
            <td class="px-4 py-2 font-mono">{{ $r->sale->code ?? '-' }}</td>
            <td class="px-4 py-2">{{ $r->mode === 'exchange' ? 'Tukar' : 'Refund Uang' }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($r->subtotal_refund,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">- Rp {{ number_format(($r->auto_share + $r->voucher_share),0,',','.') }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($r->dpp_refund,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($r->tax_refund,0,',','.') }}</td>
            <td class="px-4 py-2 text-right">
              @if($r->mode==='exchange')
                <span class="text-gray-500">Rp 0 (tukar)</span>
              @else
                <span class="font-semibold">Rp {{ number_format($r->refund_total,0,',','.') }}</span>
              @endif
            </td>
            <td class="px-4 py-2">{{ $r->processedBy->name ?? '-' }}</td>
            <td class="px-4 py-2 text-right">
              <a href="{{ route('admin.refunds.show',$r) }}" class="px-3 py-1.5 border rounded">Detail</a>
            </td>
          </tr>
        @empty
          <tr><td colspan="10" class="px-4 py-6 text-center text-gray-500">Belum ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>{{ $refunds->links() }}</div>
</div>
@endsection
