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

      <form id="refundForm" method="POST" action="{{ route('admin.returns.store') }}" class="p-4 space-y-4">
        @csrf
        <input type="hidden" name="sale_id" value="{{ $sale->id }}">

        {{-- Mode aksi --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="p-3 border rounded">
            <label class="flex items-center gap-2">
              <input type="radio" name="mode" value="refund" checked>
              <span class="font-medium">Refund ke Uang</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">Uang dikembalikan. Barang normal menambah stok; barang rusak dicatat
              sebagai rusak.</p>
          </div>
          <div class="p-3 border rounded">
            <label class="flex items-center gap-2">
              <input type="radio" name="mode" value="exchange" id="modeExchange">
              <span class="font-medium">Tukar Barang (Even Exchange)</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">Satu langkah: sistem buat dokumen refund & penjualan pengganti, lalu
              hitung selisih.</p>
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
                    <input type="number" min="0" max="{{ $it->qty }}" value="0" name="items[{{ $it->id }}][qty_refund]"
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
                <input type="text" name="replacement[0][kode_barang]" class="w-full border rounded px-3 py-2"
                  placeholder="Scan / ketik kode">
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
          <input type="text" name="notes" class="w-full border rounded px-3 py-2"
            placeholder="Alasan refund / tukar barang">
        </div>

        {{-- Tombol submit --}}
        <div class="flex items-center justify-end gap-2 pt-2 border-t">
          <button type="button" id="btnCancelRefund" class="px-4 py-2 border rounded">Batal</button>
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Proses</button>
          <div id="previewBox" class="p-3 border rounded bg-gray-50">
            <div class="font-medium">Preview</div>
            <div id="previewSummary" class="text-sm text-gray-700">Isi qty refund untuk melihat estimasi…</div>
            <div id="previewDetails" class="mt-2"></div>
          </div>

        </div>
      </form>
    </div>
  </div>

@endsection
@push('scripts')
  <script>
    (() => {
      // --- DOM refs
      const modal = document.getElementById('refundModal');
      const btnOpenRefund = document.getElementById('btnOpenRefund');
      const btnCloseRefund = document.getElementById('btnCloseRefund');
      const btnCancelRefund = document.getElementById('btnCancelRefund');
      const modeExchange = document.getElementById('modeExchange');
      const replacementBox = document.getElementById('replacementBox');
      const btnAddReplacement = document.getElementById('btnAddReplacement');
      const replacementList = document.getElementById('replacementList');
      const form = document.getElementById('refundForm');

      // --- util
      function showModal() { modal.classList.remove('hidden'); }
      function hideModal() { modal.classList.add('hidden'); }
      function rupiah(n) { return 'Rp ' + (n || 0).toLocaleString('id-ID'); }

      // --- action sesuai mode + toggle replacement section
      function setActionByMode() {
        form.action = modeExchange.checked
          ? "{{ route('admin.exchanges.store') }}"
          : "{{ route('admin.returns.store') }}";
        replacementBox.classList.toggle('hidden', !modeExchange.checked);
      }

      // --- kumpulkan payload untuk preview
      function collectPayload() {
        const mode = document.querySelector('input[name="mode"]:checked').value;
        const saleId = Number(form.querySelector('input[name="sale_id"]').value);
        const items = [];
        document.querySelectorAll('input[name^="items"][name$="[sale_item_id]"]').forEach((hid) => {
          const id = hid.name.match(/items\[(\d+)\]/)[1];
          const qty = Number(form.querySelector(`input[name="items[${id}][qty_refund]"]`).value || 0);
          const cond = form.querySelector(`select[name="items[${id}][condition]"]`).value;
          items.push({ sale_item_id: Number(hid.value), qty_refund: qty, condition: cond });
        });
        const payload = { sale_id: saleId, mode, items };

        if (mode === 'exchange') {
          const repl = [];
          form.querySelectorAll('input[name^="replacement"][name$="[kode_barang]"]').forEach((inp) => {
            const idx = inp.name.match(/replacement\[(\d+)\]/)[1];
            const qty = Number(form.querySelector(`input[name="replacement[${idx}][qty]"]`).value || 0);
            const code = inp.value.trim();
            if (code && qty > 0) repl.push({ kode_barang: code, qty });
          });
          payload.replacement = repl;
        }
        return payload;
      }

      // --- preview AJAX
      const previewUrl = "{{ route('admin.returns.preview') }}";
      const token = form.querySelector('input[name="_token"]').value;

      async function refreshPreview() {
        try {
          const res = await fetch(previewUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify(collectPayload())
          });
          const json = await res.json();
          if (!res.ok || json.status !== 'success') throw new Error(json.message || 'Gagal preview');
          const d = json.data;

          const summaryHtml = `
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
            <div><div class="text-gray-500">Subtotal Retur</div><div class="font-semibold">${rupiah(d.refund.subtotal_refund)}</div></div>
            <div><div class="text-gray-500">Diskon Otomatis (bagian)</div><div class="font-semibold text-red-600">- ${rupiah(d.refund.auto_share)}</div></div>
            <div><div class="text-gray-500">Diskon Voucher (bagian)</div><div class="font-semibold text-red-600">- ${rupiah(d.refund.voucher_share)}</div></div>
            <div><div class="text-gray-500">Pajak (${(d.refund.tax_rate || 0).toString().replace('.', ',')}%)</div><div class="font-semibold">${rupiah(d.refund.tax_refund)}</div></div>
            <div class="md:col-span-4"><div class="text-gray-500">Estimasi Kembalian</div><div class="text-lg font-bold">${rupiah(d.refund.refund_total)}</div></div>
          </div>
        `;
          document.getElementById('previewSummary').innerHTML = summaryHtml;

          let rows = '';
          (d.lines || []).forEach((ln) => {
            rows += `
            <tr class="border-t">
              <td class="px-2 py-1">${ln.nama_barang}<div class="text-xs text-gray-500">Kode: ${ln.kode_barang}</div></td>
              <td class="px-2 py-1 text-right">${ln.qty_refund}</td>
              <td class="px-2 py-1 text-right">${rupiah(ln.harga_jual)}</td>
              <td class="px-2 py-1 text-right">${rupiah(ln.line_subtotal)}</td>
              <td class="px-2 py-1 text-right text-red-600">- ${rupiah(ln.auto_share)}</td>
              <td class="px-2 py-1 text-right text-red-600">- ${rupiah(ln.voucher_share)}</td>
              <td class="px-2 py-1 text-right">${rupiah(ln.dpp_refund)}</td>
              <td class="px-2 py-1 text-right">${rupiah(ln.tax_refund)}</td>
              <td class="px-2 py-1 text-right font-semibold">${rupiah(ln.refund_amount)}</td>
            </tr>`;
          });
          document.getElementById('previewDetails').innerHTML = rows
            ? `<div class="mt-3 overflow-x-auto"><table class="min-w-full text-xs">
               <thead class="bg-gray-100"><tr>
                 <th class="px-2 py-1 text-left">Produk</th>
                 <th class="px-2 py-1 text-right">Qty</th>
                 <th class="px-2 py-1 text-right">Harga</th>
                 <th class="px-2 py-1 text-right">Subtotal</th>
                 <th class="px-2 py-1 text-right">Auto</th>
                 <th class="px-2 py-1 text-right">Voucher</th>
                 <th class="px-2 py-1 text-right">DPP</th>
                 <th class="px-2 py-1 text-right">Pajak</th>
                 <th class="px-2 py-1 text-right">Kembali</th>
               </tr></thead><tbody>${rows}</tbody></table></div>`
            : '';

          // mode exchange → info selisih
          const mode = document.querySelector('input[name="mode"]:checked').value;
          if (mode === 'exchange' && d.exchange) {
            const diff = d.difference || 0;
            const note = diff === 0 ? 'Tanpa selisih.'
              : diff > 0 ? `Pelanggan bayar selisih ${rupiah(diff)}.`
                : `Kembalikan selisih ${rupiah(Math.abs(diff))}.`;
            document.getElementById('previewDetails').insertAdjacentHTML('beforeend',
              `<div class="mt-3 p-2 border rounded bg-white">
               <div class="text-sm font-medium">Penjualan Pengganti</div>
               <div class="text-xs text-gray-600">Subtotal ${rupiah(d.exchange.subtotal)} • Diskon Otomatis -${rupiah(d.exchange.auto_discount)} • Pajak ${rupiah(d.exchange.tax)} • Total ${rupiah(d.exchange.total)}</div>
               <div class="mt-1 text-sm">${note}</div>
             </div>`);
          }
        } catch (e) {
          console.error(e);
          document.getElementById('previewSummary').innerText = 'Gagal memuat preview.';
        }
      }

      // --- events
      btnOpenRefund?.addEventListener('click', () => { showModal(); setActionByMode(); refreshPreview(); });
      btnCloseRefund?.addEventListener('click', hideModal);
      btnCancelRefund?.addEventListener('click', hideModal);
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hideModal(); });

      document.querySelectorAll('input[name="mode"]').forEach(r => {
        r.addEventListener('change', () => { setActionByMode(); refreshPreview(); })
      });

      // perubahan input/qty/condition → refresh preview
      form.querySelectorAll('input,select').forEach(el => {
        if (el.name === 'mode') return;
        el.addEventListener('input', refreshPreview);
        el.addEventListener('change', refreshPreview);
      });

      // Tambah baris barang pengganti
      let idx = 1;
      btnAddReplacement?.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = "grid grid-cols-1 md:grid-cols-3 gap-3";
        row.innerHTML = `
        <div><input type="text" name="replacement[${idx}][kode_barang]" class="w-full border rounded px-3 py-2" placeholder="Kode Barang"></div>
        <div><input type="number" name="replacement[${idx}][qty]" class="w-full border rounded px-3 py-2" min="1" value="1"></div>
        <div class="flex items-end"><button type="button" class="px-3 py-2 border rounded w-full btnRemove">Hapus</button></div>`;
        replacementList.appendChild(row);
        row.querySelector('.btnRemove').addEventListener('click', () => row.remove());
        idx++;
      });
    })();
  </script>
@endpush