<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        // KPI kartu atas (dinamis)
        $transaksiHariIni = DB::table('sales')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->count();

        $grossHariIni = (int) DB::table('sales')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('total');

        $refundHariIni = (int) DB::table('sale_returns')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('refund_total');

        $pendapatanNett = $grossHariIni - $refundHariIni;

        $itemTerjual = (int) DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereBetween('s.created_at', [$todayStart, $todayEnd])
            ->sum('si.qty');

        // -------- Grafik 7 hari terakhir (nett harian) --------
        $start = now()->subDays(6)->startOfDay();
        $end = now()->endOfDay();

        // gross per hari
        $grossBuckets = DB::table('sales')
            ->selectRaw("DATE(created_at) as d, SUM(total) as gross")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('d')
            ->pluck('gross', 'd'); // ['2025-10-24' => 12345, ...]

        // refund-uang per hari
        $refundBuckets = DB::table('sale_returns')
            ->selectRaw("DATE(created_at) as d, SUM(refund_total) as refund")
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('d')
            ->pluck('refund', 'd');

        $chartLabels = [];
        $chartNett = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $chartLabels[] = $cursor->format('d M');
            $gross = (int) ($grossBuckets[$key] ?? 0);
            $refund = (int) ($refundBuckets[$key] ?? 0);
            $chartNett[] = $gross - $refund;
            $cursor->addDay();
        }

        return view('admin.dashboard', [
            'transaksiHariIni' => $transaksiHariIni,
            'pendapatanNett' => $pendapatanNett,
            'itemTerjual' => $itemTerjual,
            'chartLabels' => $chartLabels,
            'chartNett' => $chartNett,
        ]);
    }
}
