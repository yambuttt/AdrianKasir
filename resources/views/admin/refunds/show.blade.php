@extends('layouts.admin')
@section('title', 'Detail Refund | Kasirku')

@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold">Detail Refund</h1>
                <p class="text-sm text-gray-500">
                    Ref: #{{ $refund->id }} • {{ $refund->created_at->format('d M Y H:i') }}
                    • Mode: <span class="font-medium">{{ $refund->mode === 'exchange' ? 'Tukar Barang' : 'Refund Uang' }}</span>
                </p>
                <p class="text-sm text-gray-500">
                    Struk: <span class="font-mono">{{ $refund->sale->code ?? '-' }}</span> •
                    Kasir: <span class="font-medium">{{ $refund->processedBy->name ?? '-' }}</span>
                </p>
            </div>
            <a href="{{ route('admin.refunds.index') }}" class="px-4 py-2 border rounded">Kembali</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="p-4 bg-white rounded shadow">
                <div class="text-sm text-gray-500">Subtotal Retur</div>
                <div class="text-xl font-semibold">Rp {{ number_format($refund->subtotal_refund, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-white rounded shadow">
                <div class="text-sm text-gray-500">Diskon (Auto/Voucher)</div>
                <div class="text-xl font-semibold text-red-600">- Rp
                    {{ number_format($refund->auto_share + $refund->voucher_share, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-white rounded shadow">
                <div class="text-sm text-gray-500">Pajak
                    ({{ rtrim(rtrim(number_format($refund->tax_rate, 2, '.', ''), '0'), '.') }}%)</div>
                <div class="text-xl font-semibold">Rp {{ number_format($refund->tax_refund, 0, ',', '.') }}</div>
            </div>
            <div class="p-4 bg-white rounded shadow">
                <div class="text-sm text-gray-500">{{ $refund->mode === 'exchange' ? 'Selisih' : 'Uang Dikembalikan' }}</div>
                <div class="text-xl font-semibold">
                    @if($refund->mode === 'exchange') Rp 0 @else Rp {{ number_format($refund->refund_total, 0, ',', '.') }}
                    @endif
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
                        <th class="px-4 py-2 text-right">Auto</th>
                        <th class="px-4 py-2 text-right">Voucher</th>
                        <th class="px-4 py-2 text-right">DPP</th>
                        <th class="px-4 py-2 text-right">Pajak</th>
                        <th class="px-4 py-2 text-right">Kembali</th>
                        <th class="px-4 py-2 text-left">Kondisi</th>
                        <th class="px-4 py-2 text-left">Bukti</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($refund->items as $it)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $it->nama_barang }}
                                <div class="text-xs text-gray-500">Kode: {{ $it->kode_barang }}</div>
                            </td>
                            <td class="px-4 py-2 text-right">{{ $it->qty_refund }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($it->harga_jual, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($it->line_subtotal, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right text-red-600">- Rp {{ number_format($it->auto_share, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-right text-red-600">- Rp {{ number_format($it->voucher_share, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($it->dpp_refund, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">Rp {{ number_format($it->tax_refund, 0, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right font-semibold">Rp {{ number_format($it->refund_amount, 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-2">{{ $it->condition === 'damaged' ? 'Rusak' : 'Normal' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($refund->notes)
            <div class="p-3 bg-white rounded shadow text-sm">Catatan: <em>{{ $refund->notes }}</em></div>
        @endif
    </div>
@endsection