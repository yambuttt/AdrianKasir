<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'mode' => ['nullable', 'in:refund,exchange'],
            'q' => ['nullable', 'string', 'max:50'], // cari kode transaksi
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $q = SaleReturn::query()
            ->with(['sale', 'processedBy'])
            ->when($request->filled('from'), fn($qq) => $qq->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn($qq) => $qq->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('mode'), fn($qq) => $qq->where('mode', $request->mode))
            ->when($request->filled('q'), fn($qq) => $qq->whereHas('sale', fn($qs) => $qs->where('code', 'like', '%' . $request->q . '%')))
            ->when($request->filled('cashier_id'), fn($qq) => $qq->where('processed_by', $request->integer('cashier_id')))
            ->latest('created_at');

        $refunds = $q->paginate(15)->withQueryString();

        // ringkasan cepat (mengikuti gaya Transaksi)
        $summary = (clone $q)->selectRaw(
            "COUNT(*) as total_refunds,
             SUM(refund_total) as money_returned,
             SUM(subtotal_refund) as subtotal_sum"
        )->first();

        $cashiers = User::query()->where('role', 'user')->orderBy('name')->get(['id', 'name']);

        return view('admin/refunds/index', compact('refunds', 'summary', 'cashiers'));
    }

    public function show(SaleReturn $refund)
    {
        $refund->load(['sale.user', 'items', 'processedBy']);
        return view('admin/refunds/show', compact('refund'));
    }
    public function receipt(\App\Models\SaleReturn $refund)
    {
        $refund->load(['sale.user', 'items', 'processedBy']);
        return view('admin.refunds.receipt', compact('refund'));
    }
}
