@extends('layouts.admin')

@section('content')
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold">Detail Transaksi</h1>
        <p class="text-sm text-gray-500">Kode: {{ $sale->code }} • {{ $sale->created_at->format('d M Y H:i') }}</p>
        <p class="text-sm text-gray-500">Kasir: <span class="font-medium">{{ $sale->user->name ?? '-' }}</span></p>
      </div>
      <div class="flex gap-2">
        <a href="{{ route('admin.transactions.index') }}" class="px-4 py-2 border rounded">Kembali</a>
        <a href="{{ url('/user/sales/' . $sale->id . '/receipt') }}" class="px-4 py-2 bg-indigo-600 text-white rounded"
          target="_blank">Lihat Struk</a>
        <button type="button" id="btnOpenRefund" class="px-4 py-2 bg-red-600 text-white rounded">
          Refund / Tukar Barang
        </button>
      </div>
    </div>

    @php
      $subtotal = (int) ($sale->calc_subtotal ?? 0);
      $auto = (int) ($sale->calc_auto_discount ?? 0);
      $vou = (int) ($sale->calc_voucher_discount ?? 0);
      $grand = (int) ($sale->calc_grand_total ?? 0);
    @endphp

    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Subtotal</div>
        <div class="text-xl font-semibold">Rp {{ number_format($subtotal, 0, ',', '.') }}</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Diskon Otomatis</div>
        <div class="text-xl font-semibold text-red-600">- Rp {{ number_format($auto, 0, ',', '.') }}</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Diskon Voucher</div>
        <div class="text-xl font-semibold text-red-600">- Rp {{ number_format($vou, 0, ',', '.') }}</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-sm text-gray-500">Grand Total</div>
        <div class="text-xl font-semibold">Rp {{ number_format($grand, 0, ',', '.') }}</div>
        <div class="text-xs text-gray-500 mt-1">Bayar: Rp {{ number_format($sale->cash_paid, 0, ',', '.') }} • Kembali: Rp
          {{ number_format($sale->change_due, 0, ',', '.') }}
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
                <div class="font-medium">{{ $it->nama_barang }}</div>
                <div class="text-xs text-gray-500">Kode: {{ $it->kode_barang }}</div>
              </td>
              <td class="px-4 py-2 text-right">{{ number_format($it->qty) }}</td>
              <td class="px-4 py-2 text-right">Rp {{ number_format($it->harga_jual, 0, ',', '.') }}</td>
              <td class="px-4 py-2 text-right">Rp {{ number_format($it->line_total, 0, ',', '.') }}</td>
            </tr>
          @endforeach
        </tbody>
        <tfoot class="bg-gray-50">
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-medium">Subtotal</td>
            <td class="px-4 py-2 text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-medium">Diskon Otomatis</td>
            <td class="px-4 py-2 text-right text-red-600">- Rp {{ number_format($auto, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-medium">Diskon Voucher</td>
            <td class="px-4 py-2 text-right text-red-600">- Rp {{ number_format($vou, 0, ',', '.') }}</td>
          </tr>
          <tr>
            <td colspan="3" class="px-4 py-2 text-right font-semibold">Grand Total</td>
            <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($grand, 0, ',', '.') }}</td>
          </tr>
          <div class="card p-4">
            <div class="text-sm text-gray-500">Pajak
              ({{ rtrim(rtrim(number_format($sale->tax_rate, 2, '.', ''), '0'), '.') }}%)</div>
            <div class="mt-1 text-lg font-semibold">
              Rp {{ number_format($sale->tax_amount, 0, ',', '.') }}
            </div>
          </div>
        </tfoot>
      </table>
    </div>
  </div>

  {{-- Modal Refund / Exchange --}}
<div id="refundModal" class="fixed inset-0 z-50 hidden">
  {{-- backdrop --}}
  <div class="absolute inset-0 bg-black/40"></div>

  {{-- panel --}}
  <div class="relative mx-auto mt-20 w-full max-w-3xl rounded-lg bg-white shadow-lg">
    <div class="p-4 border-b flex items-center justify-between">
      <h3 class="text-lg font-semibold">Refund / Tukar Barang — {{ $sale->code }}</h3>
      <button type="button" id="btnCloseRefund" class="text-gray-500 hover:text-gray-700">&times;</button>
    </div>

    <form id="refundForm" method="POST" action="#" class="p-4 space-y-4">
      @csrf
      <input type="hidden" name="sale_id" value="{{ $sale->id }}">

      {{-- Mode aksi --}}
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="p-3 border rounded">
          <label class="flex items-center gap-2">
            <input type="radio" name="mode" value="refund" checked>
            <span class="font-medium">Refund ke Uang</span>
          </label>
          <p class="text-xs text-gray-500 mt-1">Uang dikembalikan. Barang normal menambah stok; barang rusak dicatat sebagai rusak.</p>
        </div>
        <div class="p-3 border rounded">
          <label class="flex items-center gap-2">
            <input type="radio" name="mode" value="exchange" id="modeExchange">
            <span class="font-medium">Tukar Barang (Even Exchange)</span>
          </label>
          <p class="text-xs text-gray-500 mt-1">Satu langkah: sistem buat dokumen refund & penjualan pengganti, lalu hitung selisih.</p>
        </div>
      </div>

      {{-- Tabel item yang direfund --}}
      <div class="bg-white rounded border overflow-hidden">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left">Produk</th>
              <th class="px-3 py-2 text-right">Qty Beli</th>
              <th class="px-3 py-2 text-right">Qty Refund</th>
              <th class="px-3 py-2 text-left">Kondisi</th>
              <th class="px-3 py-2 text-right">Harga</th>
              <th class="px-3 py-2 text-right">Subtotal</th>
            </tr>
          </thead>
          <tbody>
          @foreach($sale->items as $it)
            <tr class="border-t">
              <td class="px-3 py-2">
                <div class="font-medium">{{ $it->nama_barang }}</div>
                <div class="text-xs text-gray-500">Kode: {{ $it->kode_barang }}</div>
                <input type="hidden" name="items[{{ $it->id }}][sale_item_id]" value="{{ $it->id }}">
              </td>
              <td class="px-3 py-2 text-right">{{ number_format($it->qty) }}</td>
              <td class="px-3 py-2 text-right">
                <input type="number" min="0" max="{{ $it->qty }}" value="0"
                       name="items[{{ $it->id }}][qty_refund]"
                       class="w-20 border rounded px-2 py-1 text-right">
              </td>
              <td class="px-3 py-2">
                <select name="items[{{ $it->id }}][condition]" class="border rounded px-2 py-1">
                  <option value="normal">Normal</option>
                  <option value="damaged">Rusak</option>
                </select>
              </td>
              <td class="px-3 py-2 text-right">Rp {{ number_format($it->harga_jual, 0, ',', '.') }}</td>
              <td class="px-3 py-2 text-right">Rp {{ number_format($it->line_total, 0, ',', '.') }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>

      {{-- Seksi barang pengganti (muncul kalau mode = exchange) --}}
      <div id="replacementBox" class="hidden">
        <div class="mt-2 p-3 border rounded">
          <div class="flex items-center justify-between">
            <h4 class="font-medium">Barang Pengganti</h4>
            <small class="text-gray-500">Opsional di UI awal — detail pricing akan dihitung backend.</small>
          </div>
          <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-sm text-gray-600">Kode Barang</label>
              <input type="text" name="replacement[0][kode_barang]" class="w-full border rounded px-3 py-2" placeholder="Scan / ketik kode">
            </div>
            <div>
              <label class="block text-sm text-gray-600">Qty</label>
              <input type="number" name="replacement[0][qty]" class="w-full border rounded px-3 py-2" min="1" value="1">
            </div>
            <div class="flex items-end">
              <button type="button" id="btnAddReplacement" class="px-3 py-2 border rounded w-full">Tambah Baris</button>
            </div>
          </div>
          <div id="replacementList" class="mt-2 space-y-2"></div>
        </div>
      </div>

      {{-- Catatan opsional --}}
      <div>
        <label class="block text-sm text-gray-600">Catatan</label>
        <input type="text" name="notes" class="w-full border rounded px-3 py-2" placeholder="Alasan refund / tukar barang">
      </div>

      {{-- Tombol submit --}}
      <div class="flex items-center justify-end gap-2 pt-2 border-t">
        <button type="button" id="btnCancelRefund" class="px-4 py-2 border rounded">Batal</button>
        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Proses</button>
      </div>
    </form>
  </div>
</div>

@endsection
@push('scripts')
<script>
(function(){
  const modal = document.getElementById('refundModal');
  const open = document.getElementById('btnOpenRefund');
  const close = document.getElementById('btnCloseRefund');
  const cancel = document.getElementById('btnCancelRefund');
  const modeExchange = document.getElementById('modeExchange');
  const replacementBox = document.getElementById('replacementBox');
  const btnAddReplacement = document.getElementById('btnAddReplacement');
  const replacementList = document.getElementById('replacementList');

  function showModal(){ modal.classList.remove('hidden'); }
  function hideModal(){ modal.classList.add('hidden'); }

  open?.addEventListener('click', showModal);
  close?.addEventListener('click', hideModal);
  cancel?.addEventListener('click', hideModal);
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') hideModal(); });

  // Toggle replacement section
  document.querySelectorAll('input[name="mode"]').forEach(r => {
    r.addEventListener('change', () => {
      replacementBox.classList.toggle('hidden', !modeExchange.checked);
      // ganti action form sesuai mode
      const form = document.getElementById('refundForm');
      form.action = modeExchange.checked ? "{{ route('admin.exchanges.store') }}" : "{{ route('admin.returns.store') }}";
    });
  });

  // Tambah baris barang pengganti
  let idx = 1;
  btnAddReplacement?.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = "grid grid-cols-1 md:grid-cols-3 gap-3";
    row.innerHTML = `
      <div><input type="text" name="replacement[${idx}][kode_barang]" class="w-full border rounded px-3 py-2" placeholder="Kode Barang"></div>
      <div><input type="number" name="replacement[${idx}][qty]" class="w-full border rounded px-3 py-2" min="1" value="1"></div>
      <div class="flex items-end"><button type="button" class="px-3 py-2 border rounded w-full btnRemove">Hapus</button></div>
    `;
    replacementList.appendChild(row);
    row.querySelector('.btnRemove').addEventListener('click', () => row.remove());
    idx++;
  });
})();
</script>
@endpush
