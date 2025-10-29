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

            // Siapkan baris retur + cegah refund ganda
            $rows = [];
            foreach ($data['items'] as $row) {
                $it = SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
                $refunded = SaleReturnItem::where('sale_item_id', $it->id)->sum('qty_refund');
                $maxLeft = max(0, (int) $it->qty - (int) $refunded);
                $qty_ref = min((int) $row['qty_refund'], $maxLeft);
                if ($qty_ref <= 0)
                    continue;
                $rows[] = ['sale_item' => $it, 'qty_refund' => $qty_ref, 'condition' => $row['condition']];
            }
            if (empty($rows))
                return back()->with('error', 'Tidak ada item yang direfund.');

            // Hitung nominal refund pro-rata (sudah ada) :contentReference[oaicite:7]{index=7}
            $calc = $svc->compute($sale, $rows);

            // Buat dokumen return (mode=refund uang)
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

            // Simpan item + update stok / log rusak (sudah ada) :contentReference[oaicite:8]{index=8}
            foreach ($calc['lines'] as $i => $ln) {
                $cond = $rows[$i]['condition'] ?? 'normal';
                SaleReturnItem::create(array_merge($ln, [
                    'sale_return_id' => $ret->id,
                    'condition' => $cond,
                ]));

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

            return redirect()->route('admin.transactions.show', $sale)
                ->with('ok', 'Refund berhasil. Kembalikan uang Rp ' . number_format($ret->refund_total, 0, ',', '.'));
        });
    }

    public function preview(Request $request, RefundService $svc)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'mode' => ['required', 'in:refund,exchange'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'items.*.qty_refund' => ['required', 'integer', 'min:0'],
            'items.*.condition' => ['required', 'in:normal,damaged'],
        ]);

        $sale = Sale::with('items')->findOrFail($data['sale_id']);

        // Baris + anti refund ganda
        $rows = [];
        foreach ($data['items'] as $row) {
            $it = SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);
            $refunded = SaleReturnItem::where('sale_item_id', $it->id)->sum('qty_refund');
            $maxLeft = max(0, (int) $it->qty - (int) $refunded);
            $qty = min((int) $row['qty_refund'], $maxLeft);
            if ($qty <= 0)
                continue;
            $rows[] = ['sale_item' => $it, 'qty_refund' => $qty, 'condition' => $row['condition']];
        }

        $calc = $svc->compute($sale, $rows); // :contentReference[oaicite:9]{index=9}

        $resp = [
            'refund' => $calc['summary'],
            'lines' => $calc['lines'],
        ];

        // Mode exchange (zero-diff): tidak hitung penjualan baru, difference=0
        if ($data['mode'] === 'exchange') {
            $resp['replacement_lines'] = array_map(fn($ln) => [
                'kode_barang' => $ln['kode_barang'],
                'qty' => $ln['qty_refund'],
            ], $calc['lines']);
            $resp['difference'] = 0;
        }

        return response()->json(['status' => 'success', 'data' => $resp]);
    }

    

}
