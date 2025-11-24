<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $q = Sale::query()
            ->where('user_id', $user->id)
            ->when($request->filled('from'), fn($qq) => $qq->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn($qq) => $qq->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), fn($qq) => $qq->where('code', 'like', '%' . $request->q . '%'))
            ->latest('created_at');


        $summary = (clone $q)
            ->selectRaw('COUNT(*) as total_orders, SUM(total) as revenue, SUM(auto_discount) as auto_sum, SUM(voucher_discount) as voucher_sum')
            ->first();

 
        $saleIds = (clone $q)->pluck('id');
        $refundSum = DB::table('sale_returns')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $saleIds)
            ->sum('refund_total');

        $summary->net_revenue = (int) ($summary->revenue ?? 0) - (int) $refundSum;

  
        $sales = $q->paginate(15)->withQueryString();


        $refundBySale = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) AS refund_cash')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        foreach ($sales as $s) {
            $s->calc_subtotal = (int) $s->subtotal;
            $s->calc_auto_discount = (int) $s->auto_discount;
            $s->calc_voucher_discount = (int) $s->voucher_discount;
            $s->calc_grand_total = (int) $s->total;
            $s->net_total = (int) $s->total - (int) ($refundBySale[$s->id] ?? 0);
        }

        return view('user/history/index', compact('sales', 'summary'));
    }

    public function show(Sale $sale)
    {
        
        abort_unless($sale->user_id === auth()->id(), 403);

        $sale->load(['items', 'returns.items']);

        $sale->calc_subtotal = (int) $sale->subtotal;
        $sale->calc_auto_discount = (int) $sale->auto_discount;
        $sale->calc_voucher_discount = (int) $sale->voucher_discount;
        $sale->calc_grand_total = (int) $sale->total;

        
        $refundCash = (int) $sale->returns()
            ->where('mode', 'refund')    
            ->sum('refund_total');        

       
        $sale->calc_refund_cash = $refundCash;
        $sale->calc_net_total = max(0, $sale->calc_grand_total - $refundCash);

        return view('user.history.show', compact('sale'));
    }
}
