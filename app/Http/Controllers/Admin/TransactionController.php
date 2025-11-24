<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'q' => ['nullable', 'string', 'max:50'],
        ]);

        $q = Sale::query()
            ->with(['user', 'items'])
            ->when($request->filled('from'), fn($qq) => $qq->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn($qq) => $qq->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('cashier_id'), fn($qq) => $qq->where('user_id', $request->integer('cashier_id')))
            ->when($request->filled('q'), fn($qq) => $qq->where('code', 'like', '%' . $request->q . '%'))
            ->latest('created_at');


        $summary = (clone $q)
            ->selectRaw('COUNT(*) as total_orders, SUM(total) as revenue, SUM(auto_discount) as auto_sum, SUM(voucher_discount) as voucher_sum')
            ->first(); 

        $saleIdsForSummary = (clone $q)->pluck('id');
        $refundSum = DB::table('sale_returns')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $saleIdsForSummary)
            ->sum('refund_total');

        $summary->net_revenue = (int) ($summary->revenue ?? 0) - (int) $refundSum;

        $sales = $q->paginate(15)->withQueryString();

  
        $pageSaleIds = $sales->pluck('id');
        $refundBySale = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) AS refund_cash')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $pageSaleIds)
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        foreach ($sales as $sale) {
            $sale->calc_subtotal = (int) $sale->subtotal;
            $sale->calc_auto_discount = (int) $sale->auto_discount;
            $sale->calc_voucher_discount = (int) $sale->voucher_discount;
            $sale->calc_grand_total = (int) $sale->total;
            $sale->net_total = (int) $sale->total - (int) ($refundBySale[$sale->id] ?? 0);
        }

        $cashiers = User::query()->where('role', 'user')->orderBy('name')->get(['id', 'name']);

        return view('admin/transactions/index', [
            'sales' => $sales,
            'cashiers' => $cashiers,
            'summary' => $summary,
        ]);
    }

    public function show(Sale $sale)
    {

        $sale->load(['user', 'items', 'returns.items']);

        $sale->calc_subtotal = (int) $sale->subtotal;
        $sale->calc_auto_discount = (int) $sale->auto_discount;
        $sale->calc_voucher_discount = (int) $sale->voucher_discount;
        $sale->calc_grand_total = (int) $sale->total;

        $refundCash = (int) $sale->returns()
            ->where('mode', 'refund')
            ->sum('refund_total');

        $sale->calc_refund_cash = $refundCash;
        $sale->calc_net_total = max(0, $sale->calc_grand_total - $refundCash);

        return view('admin/transactions/show', compact('sale'));
    }
}
