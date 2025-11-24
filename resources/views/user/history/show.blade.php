@extends('layouts.user')
@section('title', 'Detail Transaksi | Kasirku')

@section('content')
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Detail Transaksi</h1>
        <p class="text-sm text-gray-500">
          Kode: {{ $sale->code }} • {{ $sale->created_at->format('d M Y H:i') }} • Kasir: {{ $sale->user->name ?? '-' }}
        </p>
      </div>
      <div class="flex gap-2">
        <a href="{{ route('user.history.index') }}" class="px-4 py-2 border rounded">Kembali</a>
        <a href="{{ url('/user/sales/' . $sale->id . '/receipt') }}" target="_blank"
          class="px-4 py-2 bg-indigo-600 text-white rounded">Lihat Struk</a>
     
      </div>
      @php
        
        $refundCash = 0;

        if ($sale->returns && $sale->returns->count()) {
          $refundCash = (int) $sale->returns
            ->where('mode', 'refund')
            ->sum('refund_total');
        }

        $grandBefore = (int) $sale->calc_grand_total;
        $grandAfter = max(0, $grandBefore - $refundCash);
      @endphp
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Subtotal</div>
        <div class="text-xl font-semibold">Rp {{ number_format($sale->calc_subtotal, 0, ',', '.') }}</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Diskon Otomatis</div>
        <div class="text-xl font-semibold text-red-600">- Rp {{ number_format($sale->calc_auto_discount, 0, ',', '.') }}
        </div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Diskon Voucher</div>
        <div class="text-xl font-semibold text-red-600">- Rp
          {{ number_format($sale->calc_voucher_discount, 0, ',', '.') }}
        </div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="p-4 bg-white rounded shadow">
          <div class="text-sm text-gray-500">Grand Total (setelah refund uang)</div>
          <div class="text-xl font-semibold">
            Rp {{ number_format($grandAfter, 0, ',', '.') }}
          </div>

          <div class="text-xs text-gray-500 mt-1 space-y-0.5">
            <div>
              Sebelum refund: Rp {{ number_format($grandBefore, 0, ',', '.') }}
            </div>
            @if($refundCash > 0)
              <div>
                Refund uang: - Rp {{ number_format($refundCash, 0, ',', '.') }}
              </div>
            @endif
            <div>
              Bayar: Rp {{ number_format($sale->paid_amount, 0, ',', '.') }}
              • Kembali: Rp {{ number_format($sale->change_amount, 0, ',', '.') }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left">Produk</th>
            <th class="px-4 py-2 text-right">Qty</th>
            <th class="px-4 py-2 text-right">Harga</th>
            <th class="px-4 py-2 text-right">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          @foreach($sale->items as $it)
            <tr class="border-t">
              <td class="px-4 py-2">
                {{ $it->nama_barang }}
                <div class="text-xs text-gray-500">Kode: {{ $it->kode_barang }}</div>
              </td>
              <td class="px-4 py-2 text-right">{{ $it->qty }}</td>
              <td class="px-4 py-2 text-right">Rp {{ number_format($it->harga_jual, 0, ',', '.') }}</td>
              <td class="px-4 py-2 text-right">Rp {{ number_format($it->line_subtotal, 0, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right">Subtotal</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($sale->calc_subtotal, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right">Diskon Otomatis</td>
            <td class="px-4 py-2 text-right text-red-600">- Rp {{ number_format($sale->calc_auto_discount, 0, ',', '.') }}
            </td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right">Diskon Voucher</td>
            <td class="px-4 py-2 text-right text-red-600">- Rp
              {{ number_format($sale->calc_voucher_discount, 0, ',', '.') }}
            </td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right">Grand Total</td>
            <td class="px-4 py-2 text-right font-semibold">
              Rp {{ number_format($grandAfter, 0, ',', '.') }}
            </td>
          </tr>

        </tfoot>
      </table>
    </div>

    {{-- Panel Riwayat Refund/Tukar (read-only) --}}
    @if($sale->returns && $sale->returns->count())
      <div class="mt-6 bg-white rounded shadow">
        <div class="px-4 py-3 border-b">
          <h3 class="font-semibold">Riwayat Refund / Tukar</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 text-left">Waktu</th>
                <th class="px-4 py-2 text-left">Mode</th>
                <th class="px-4 py-2 text-right">Subtotal Retur</th>
                <th class="px-4 py-2 text-right">Pajak</th>
                <th class="px-4 py-2 text-right">Uang Kembali / Selisih</th>
                <th class="px-4 py-2 text-left">Detail Item</th>
              </tr>
            </thead>
            <tbody>
              @foreach($sale->returns as $r)
                <tr class="border-t align-top">
                  <td class="px-4 py-2">{{ $r->created_at->format('d M Y H:i') }}</td>
                  <td class="px-4 py-2">{{ $r->mode === 'exchange' ? 'Tukar' : 'Refund Uang' }}</td>
                  <td class="px-4 py-2 text-right">Rp {{ number_format($r->subtotal_refund, 0, ',', '.') }}</td>
                  <td class="px-4 py-2 text-right">Rp {{ number_format($r->tax_refund, 0, ',', '.') }}</td>
                  <td class="px-4 py-2 text-right">
                    @if($r->mode === 'exchange') <span class="text-gray-600">Rp 0</span>
                    @else <span class="font-semibold">Rp {{ number_format($r->refund_total, 0, ',', '.') }}</span>
                    @endif
                  </td>
                  <td class="px-4 py-2">
                    <ul class="list-disc pl-5">
                      @foreach($r->items as $it)
                        <li>{{ $it->nama_barang }} — Kode {{ $it->kode_barang }}, Qty {{ $it->qty_refund }},
                          <em>{{ $it->condition === 'damaged' ? 'rusak' : 'normal' }}</em>
                        </li>
                      @endforeach
                    </ul>
                    @if($r->notes)
                      <div class="text-xs text-gray-500 mt-1">Catatan: {{ $r->notes }}</div>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  </div>
@endsection