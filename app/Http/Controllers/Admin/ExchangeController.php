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

class ExchangeController extends Controller
{
    /** Tukar barang rusak → barang baru yang sama (kode & qty sama), tanpa selisih/uang */
    public function store(Request $request, RefundService $svc)
    {
        $data = $request->validate([
            'sale_id' => ['required','integer','exists:sales,id'],
            'items'   => ['required','array','min:1'],
            'items.*.sale_item_id' => ['required','integer','exists:sale_items,id'],
            'items.*.qty_refund'   => ['required','integer','min:0'],
            // terima 'normal'/'damaged' tapi nanti enforce 'damaged' utk yang qty>0
            'items.*.condition'    => ['required','in:normal,damaged,rusak'],
            'notes'   => ['nullable','string','max:200'],
        ]);

        $userId = $request->user()->id;

        return DB::transaction(function () use ($data, $svc, $userId) {
            $sale = Sale::with('items')->lockForUpdate()->findOrFail($data['sale_id']);

            // siapkan baris retur + anti refund ganda
            $rows = [];
            foreach ($data['items'] as $row) {
                /** @var SaleItem $it */
                $it = SaleItem::where('sale_id', $sale->id)->findOrFail($row['sale_item_id']);

                // total qty yang sudah pernah direfund utk item ini
                $refunded = SaleReturnItem::where('sale_item_id', $it->id)->sum('qty_refund');
                $maxLeft  = max(0, (int)$it->qty - (int)$refunded);

                $qty = min((int)$row['qty_refund'], $maxLeft);
                if ($qty <= 0) continue;

                // enforce: yang ditukar harus 'damaged'
                $cond = strtolower((string)$row['condition']) === 'rusak' ? 'damaged' : $row['condition'];
                if ($cond !== 'damaged') {
                    return back()->with('error', 'Tukar barang hanya untuk barang rusak.');
                }

                $rows[] = ['sale_item'=>$it, 'qty_refund'=>$qty, 'condition'=>'damaged'];
            }
            if (empty($rows)) {
                return back()->with('error','Tidak ada item yang bisa ditukar (mungkin sudah pernah direfund).');
            }

            // hitung alokasi retur (pro-rata diskon & pajak) — catat sebagai jejak
            $calc = $svc->compute($sale, $rows);

            // buat dokumen retur (mode exchange), TANPA uang keluar/masuk
            $ret = SaleReturn::create([
                'sale_id'         => $sale->id,
                'processed_by'    => $userId,
                'mode'            => 'exchange',
                'subtotal_refund' => $calc['summary']['subtotal_refund'],
                'auto_share'      => $calc['summary']['auto_share'],
                'voucher_share'   => $calc['summary']['voucher_share'],
                'dpp_refund'      => $calc['summary']['dpp_refund'],
                'tax_rate'        => $calc['summary']['tax_rate'],
                'tax_refund'      => $calc['summary']['tax_refund'],
                'refund_total'    => 0, // zero-difference
                'notes'           => $data['notes'] ?? null,
            ]);

            // simpan item retur + damage log
            foreach ($calc['lines'] as $i => $ln) {
                SaleReturnItem::create(array_merge($ln, [
                    'sale_return_id' => $ret->id,
                    'condition'      => 'damaged',
                ]));

                ProductDamageLog::create([
                    'kode_barang'    => $ln['kode_barang'],
                    'qty'            => $ln['qty_refund'],
                    'sale_return_id' => $ret->id,
                    'notes'          => 'Tukar barang: barang asal rusak',
                    'created_by'     => $userId,
                ]);
            }

            // keluarkan stok barang pengganti (kode & qty sama dgn yang direfund)
            foreach ($calc['lines'] as $ln) {
                $prod = Product::lockForUpdate()->where('kode_barang', $ln['kode_barang'])->first();
                if (!$prod) throw new \Exception("Produk pengganti tidak ditemukan: {$ln['kode_barang']}");
                if ($prod->stok_kasir < $ln['qty_refund']) {
                    throw new \Exception("Stok pengganti {$prod->nama_barang} kurang.");
                }
                $prod->decrement('stok_kasir', $ln['qty_refund']);
            }

            return redirect()
                ->route('admin.transactions.show', $sale)
                ->with('ok', 'Tukar barang selesai (barang sama, tanpa selisih).');
        });
    }
}
