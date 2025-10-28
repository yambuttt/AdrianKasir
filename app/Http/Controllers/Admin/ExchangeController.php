<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\ProductDamageLog;
use App\Services\DiscountEngine;
use App\Services\RefundService;
use App\Services\TaxService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExchangeController extends Controller
{
  public function __construct(private DiscountEngine $engine, private TaxService $tax) {}

  public function store(Request $request, RefundService $svc)
  {
    $data = $request->validate([
      'sale_id' => ['required','integer','exists:sales,id'],
      'items'   => ['required','array','min:1'],
      'items.*.sale_item_id' => ['required','integer','exists:sale_items,id'],
      'items.*.qty_refund'   => ['required','integer','min:0'],
      'items.*.condition'    => ['required','in:normal,damaged'],
      'replacement'          => ['required','array','min:1'],
      'replacement.*.kode_barang' => ['required','string'],
      'replacement.*.qty'         => ['required','integer','min:1'],
      'notes'   => ['nullable','string','max:200'],
    ]);

    $userId = $request->user()->id;

    return DB::transaction(function () use ($data, $svc, $userId) {
      $sale = Sale::with('items')->lockForUpdate()->findOrFail($data['sale_id']);

      // 1) Hitung nilai refund (seperti ReturnController)
      $rows = [];
      foreach ($data['items'] as $row) {
        $it  = SaleItem::where('sale_id',$sale->id)->findOrFail($row['sale_item_id']);
        $qty = min((int)$row['qty_refund'], (int)$it->qty);
        if ($qty <= 0) continue;
        $rows[] = ['sale_item'=>$it, 'qty_refund'=>$qty, 'condition'=>$row['condition']];
      }
      if (empty($rows)) return back()->with('error','Tidak ada item yang direfund.');

      $calc = $svc->compute($sale, $rows);

      // 2) Buat dokumen return (mode=exchange) + update stok / log rusak
      $ret = SaleReturn::create([
        'sale_id' => $sale->id,
        'processed_by' => $userId,
        'mode' => 'exchange',
        'subtotal_refund' => $calc['summary']['subtotal_refund'],
        'auto_share' => $calc['summary']['auto_share'],
        'voucher_share' => $calc['summary']['voucher_share'],
        'dpp_refund' => $calc['summary']['dpp_refund'],
        'tax_rate' => $calc['summary']['tax_rate'],
        'tax_refund' => $calc['summary']['tax_refund'],
        'refund_total' => $calc['summary']['refund_total'],
        'notes' => $data['notes'] ?? null,
      ]);

      foreach ($calc['lines'] as $i => $ln) {
        $cond = $rows[$i]['condition'] ?? 'normal';
        SaleReturnItem::create(array_merge($ln, [
          'sale_return_id' => $ret->id,
          'condition' => $cond,
        ]));
        $prod = Product::lockForUpdate()->where('kode_barang',$ln['kode_barang'])->first();
        if ($prod) {
          if ($cond === 'normal') {
            $prod->increment('stok_kasir', $ln['qty_refund']);
          } else {
            ProductDamageLog::create([
              'kode_barang' => $ln['kode_barang'],
              'qty' => $ln['qty_refund'],
              'sale_return_id' => $ret->id,
              'notes' => 'Tukar barang: barang asal rusak',
              'created_by' => $userId,
            ]);
          }
        }
      }

      // 3) Buat SALE pengganti (tanpa voucher; diskon otomatis & pajak sesuai aturan saat ini)
      $kodeList = collect($data['replacement'])->pluck('kode_barang')->all();
      $products = Product::whereIn('kode_barang', $kodeList)->lockForUpdate()->get()->keyBy('kode_barang');

      $lines = []; $subtotal = 0;
      foreach ($data['replacement'] as $r) {
        $p = $products->get($r['kode_barang']);
        if (!$p || is_null($p->harga_jual)) throw new \Exception("Produk pengganti tidak valid: {$r['kode_barang']}");
        if ($r['qty'] > $p->stok_kasir) throw new \Exception("Stok pengganti {$p->nama_barang} kurang.");
        $lt = (int)$p->harga_jual * (int)$r['qty'];
        $subtotal += $lt;
        $lines[] = ['product'=>$p, 'kode_barang'=>$p->kode_barang, 'nama_barang'=>$p->nama_barang, 'harga_jual'=>(int)$p->harga_jual, 'qty'=>(int)$r['qty'], 'line_total'=>$lt];
      }

      $auto = $this->engine->computeAutoDiscount($subtotal); // amount, scheme, tier
      $dpp  = max(0, $subtotal - (int)$auto['amount']);
      $tax  = $this->tax->compute($dpp); // ['tax'=>int,'rate'=>float]
      $grand = $dpp + (int)$tax['tax'];

      $saleNew = Sale::create([
        'code' => 'EXC-'.now()->format('ymd').'-'.Str::upper(Str::random(5)),
        'user_id' => $userId,
        'customer_name' => null,
        'subtotal' => $subtotal,
        'auto_discount' => (int)$auto['amount'],
        'voucher_discount' => 0, // voucher tidak diulang saat tukar
        'tax_rate' => (float)$tax['rate'],
        'tax_amount' => (int)$tax['tax'],
        'total' => $grand,
        'cash_paid' => 0, // tidak ada pembayaran di dokumen ini
        'change_due' => 0,
        'discount_snapshot' => ['auto'=>$auto, 'voucher'=>null, 'tax'=>['rate'=>(float)$tax['rate'], 'amount'=>(int)$tax['tax']]],
      ]);

      foreach ($lines as $ln) {
        \App\Models\SaleItem::create([
          'sale_id' => $saleNew->id,
          'kode_barang' => $ln['kode_barang'],
          'nama_barang' => $ln['nama_barang'],
          'harga_jual' => $ln['harga_jual'],
          'qty' => $ln['qty'],
          'line_total' => $ln['line_total'],
        ]);
        $ln['product']->decrement('stok_kasir', $ln['qty']);
      }

      // 4) Catat hubungan (untuk pelacakan) + infokan selisih
      $ret->update(['replacement_sale_id' => $saleNew->id]);

      $difference = $grand - $ret->refund_total; // >0: customer bayar; <0: kembalikan uang
      $msg = $difference === 0
        ? 'Tukar barang selesai tanpa selisih.'
        : ($difference > 0
            ? 'Pelanggan perlu bayar selisih Rp '.number_format($difference,0,',','.')
            : 'Kembalikan selisih ke pelanggan Rp '.number_format(abs($difference),0,',','.'));

      return redirect()->route('admin.transactions.show',$sale)->with('ok',$msg);
    });
  }
}
