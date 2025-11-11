<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SalesExportController extends Controller
{
    // GET /api/sales?start=2025-10-01&end=2025-10-31&cashier_id=...&q=...
    public function index(Request $req)
    {
        $start = $req->date('start');
        $end = $req->date('end');
        $cashierId = $req->input('cashier_id');
        $q = $req->input('q');

        $perPage = $req->integer('per_page', 50);

        $sales = \App\Models\Sale::with(['items', 'user'])
            ->when($start, fn($qq) => $qq->whereDate('created_at', '>=', $start))
            ->when($end, fn($qq) => $qq->whereDate('created_at', '<=', $end))
            ->when($cashierId, fn($qq) => $qq->where('user_id', $cashierId))
            ->when($q, fn($qq) => $qq->where(function ($w) use ($q) {
                $w->where('customer_name', 'like', "%$q%")
                    ->orWhere('code', 'like', "%$q%");
            }))
            ->latest('id')
            ->paginate($perPage);
        $refundBySale = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) AS refund_cash')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $sales->getCollection()->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        // ← hasil langsung array, TANPA { "data": ... }
        $body = $sales->getCollection()
            ->map(function (\App\Models\Sale $s) use ($refundBySale) {
                $refund = (int) ($refundBySale[$s->id] ?? 0);
                return $this->formatSale($s, $refund);
            })
            ->values()
            ->all();

        return response()
            ->json($body)
            ->header('X-Total-Count', $sales->total())
            ->header('X-Per-Page', $sales->perPage())
            ->header('X-Current-Page', $sales->currentPage())
            ->header('X-Last-Page', $sales->lastPage());
    }

    // GET /api/sales/{sale}
    public function show(\App\Models\Sale $sale)
    {
        $sale->loadMissing(['items', 'user']);

        $refund = (int) \DB::table('sale_returns')
            ->where('mode', 'refund')
            ->where('sale_id', $sale->id)
            ->sum('refund_total'); // nett = total - refund uang
        // (logika yang sama dipakai di Report/Transaction controller) :contentReference[oaicite:4]{index=4} :contentReference[oaicite:5]{index=5}

        return response()->json($this->formatSale($sale, $refund));
    }



    private function formatSale(\App\Models\Sale $s, int $refundCash = 0): array
    {
        $gross = (int) $s->total;     // grand total asli saat checkout
        $nett = $gross - $refundCash; // kurangi refund uang (bila ada)

        return [
            'id' => (int) $s->id,
            'created_at' => $s->created_at?->toISOString(),
            'total' => $nett, // ← sekarang menampilkan total setelah refund
            'user' => ['name' => $s->user?->name],
            'customer_name' => $s->customer_name,
            'items' => $s->items->map(fn($it) => [
                'sku' => $it->kode_barang,
                'name' => $it->nama_barang,
                'qty' => (int) $it->qty,
                'price' => (int) $it->harga_jual,
                'subtotal' => (int) $it->line_total,
            ])->all(),
        ];
    }

}
