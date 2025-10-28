<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\ProductDamageLog;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function store(Request $request, RefundService $svc)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.qty_refund' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:normal,damaged'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);

        $userId = $request->user()->id;

        return DB::transaction(function () use ($data, $userId, $svc) {
            $sale = Sale::with('items')->lockForUpdate()->findOrFail($data['sale_id']);

            // Siapkan baris retur
            $rows = [];
            foreach ($data['items'] as $row) {
                $it = SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
                $qty_ref = min((int) $row['qty_refund'], (int) $it->qty); // jangan melebihi qty beli
                if ($qty_ref <= 0)
                    continue;
                $rows[] = ['sale_item' => $it, 'qty_refund' => $qty_ref, 'condition' => $row['condition']];
            }
            if (empty($rows)) {
                return back()->with('error', 'Tidak ada item yang direfund.');
            }

            // Hitung nominal refund pro-rata diskon + pajak
            $calc = $svc->compute($sale, $rows);

            // Buat dokumen return
            $ret = SaleReturn::create([
                'sale_id' => $sale->id,
                'processed_by' => $userId,
                'mode' => 'refund',
                'subtotal_refund' => $calc['summary']['subtotal_refund'],
                'auto_share' => $calc['summary']['auto_share'],
                'voucher_share' => $calc['summary']['voucher_share'],
                'dpp_refund' => $calc['summary']['dpp_refund'],
                'tax_rate' => $calc['summary']['tax_rate'],
                'tax_refund' => $calc['summary']['tax_refund'],
                'refund_total' => $calc['summary']['refund_total'],
                'notes' => $data['notes'] ?? null,
            ]);

            // Simpan item + update stok / log rusak
            foreach ($calc['lines'] as $i => $ln) {
                $cond = $rows[$i]['condition'] ?? 'normal';
                SaleReturnItem::create(array_merge($ln, [
                    'sale_return_id' => $ret->id,
                    'condition' => $cond,
                ]));

                // Update stok_kasir / log rusak
                $prod = Product::lockForUpdate()->where('kode_barang', $ln['kode_barang'])->first();
                if ($prod) {
                    if ($cond === 'normal') {
                        $prod->increment('stok_kasir', $ln['qty_refund']);
                    } else {
                        ProductDamageLog::create([
                            'kode_barang' => $ln['kode_barang'],
                            'qty' => $ln['qty_refund'],
                            'sale_return_id' => $ret->id,
                            'notes' => 'Retur barang rusak',
                            'created_by' => $userId,
                        ]);
                    }
                }
            }

            // (Opsional) Kebijakan voucher:
            // - Partial return: voucher tetap dianggap terpakai.
            // - Full return: kamu bisa buat tombol “Pulihkan voucher” tersendiri bila ingin.

            return redirect()->route('admin.transactions.show', $sale)->with(
                'ok',
                'Refund berhasil. Kembalikan uang Rp ' . number_format($ret->refund_total, 0, ',', '.')
            );
        });
    }

    public function preview(
        \Illuminate\Http\Request $request,
        \App\Services\RefundService $svc,
        \App\Services\DiscountEngine $engine,
        \App\Services\TaxService $tax
    ) {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'mode' => ['required', 'in:refund,exchange'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.qty_refund' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:normal,damaged'],
            'replacement' => ['array'],                 // hanya untuk exchange
            'replacement.*.kode_barang' => ['required_with:replacement', 'string'],
            'replacement.*.qty' => ['required_with:replacement', 'integer', 'min:1'],
        ]);

        $sale = \App\Models\Sale::with('items')->findOrFail($data['sale_id']);

        // Siapkan baris yang benar2 direfund
        $rows = [];
        foreach ($data['items'] as $row) {
            $it = \App\Models\SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
            $qty = min((int) $row['qty_refund'], (int) $it->qty);
            if ($qty <= 0)
                continue;
            $rows[] = ['sale_item' => $it, 'qty_refund' => $qty, 'condition' => $row['condition']];
        }

        $calc = $svc->compute($sale, $rows); // ->summary & ->lines

        $resp = [
            'refund' => $calc['summary'],
            'lines' => $calc['lines'],
        ];

        // Jika mode exchange, hitung penjualan pengganti (tanpa voucher)
        if ($data['mode'] === 'exchange' && !empty($data['replacement'])) {
            $kodeList = collect($data['replacement'])->pluck('kode_barang')->all();
            $products = \App\Models\Product::whereIn('kode_barang', $kodeList)->get()->keyBy('kode_barang');

            $subtotal = 0;
            foreach ($data['replacement'] as $r) {
                $p = $products->get($r['kode_barang']);
                if (!$p || is_null($p->harga_jual))
                    continue;
                $subtotal += (int) $p->harga_jual * (int) $r['qty'];
            }
            $auto = $engine->computeAutoDiscount($subtotal);          // diskon otomatis
            $dpp = max(0, $subtotal - (int) $auto['amount']);
            $tx = $tax->compute($dpp);                               // floor, rate konsisten
            $grand = $dpp + (int) $tx['tax'];

            $resp['exchange'] = [
                'subtotal' => $subtotal,
                'auto_discount' => (int) $auto['amount'],
                'dpp' => $dpp,
                'tax_rate' => (float) $tx['rate'],
                'tax' => (int) $tx['tax'],
                'total' => $grand,
            ];
            $resp['difference'] = $grand - (int) $calc['summary']['refund_total'];
        }

        return response()->json(['status' => 'success', 'data' => $resp]);
    }

}
