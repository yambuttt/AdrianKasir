<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $range  = $request->get('range', 'month');          // day|week|month|year
        $anchor = $request->input('at') ? Carbon::parse($request->input('at')) : now();

        [$start, $end, $groupExpr, $labelFormat] = $this->makeWindowAndGrouping($range, $anchor);

        // --- Agregasi untuk grafik (gross dan refund uang), dibatasi user ini
        $grossBuckets = DB::table('sales')
            ->selectRaw("$groupExpr AS bucket, SUM(total) AS gross")
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('bucket')
            ->pluck('gross', 'bucket');

        $refundBuckets = DB::table('sale_returns')
            ->selectRaw("$groupExpr AS bucket, SUM(refund_total) AS refund_cash")
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('sale_id', function ($q) use ($user) {
                $q->select('id')->from('sales')->where('user_id', $user->id);
            })
            ->groupBy('bucket')
            ->pluck('refund_cash', 'bucket');

        // Build labels & data nett
        $labels   = [];
        $dataNett = [];
        $cursor   = $start->copy();
        while ($cursor < $end) {
            $key      = $this->bucketKey($range, $cursor);
            $labels[] = $cursor->format($labelFormat);
            $gross    = (int)($grossBuckets[$key] ?? 0);
            $refund   = (int)($refundBuckets[$key] ?? 0);
            $dataNett[] = $gross - $refund;
            $cursor   = $this->stepCursor($range, $cursor);
        }

        // --- Ringkasan & tabel (nett per transaksi)
        $salesQ = Sale::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end]);

        $summaryGross = (int) $salesQ->sum('total');

        $summaryRefund = (int) DB::table('sale_returns')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('sale_id', function ($q) use ($user) {
                $q->select('id')->from('sales')->where('user_id', $user->id);
            })
            ->sum('refund_total');

        $summaryNett = $summaryGross - $summaryRefund;

        $sales = Sale::with('user')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) AS refund_cash')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        foreach ($sales as $s) {
            $s->refund_cash = (int)($refundMap[$s->id] ?? 0);
            $s->net_total   = (int)$s->total - $s->refund_cash;
        }

        return view('user/reports/index', [
            'range'    => $range,
            'anchor'   => $anchor,
            'start'    => $start,
            'end'      => $end,
            'labels'   => $labels,
            'dataNett' => $dataNett,
            'summary'  => [
                'gross'  => $summaryGross,
                'refund' => $summaryRefund,
                'nett'   => $summaryNett,
            ],
            'sales'    => $sales,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user   = $request->user();
        $range  = $request->get('range', 'month');
        $anchor = $request->input('at') ? Carbon::parse($request->input('at')) : now();
        [$start, $end] = $this->makeWindowAndGrouping($range, $anchor);

        $rows = Sale::with('user')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) as refund_cash')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('sale_id', $rows->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        $filename = 'laporan-user-'.now()->format('Ymd_His').'.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($rows, $refundMap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu','Kode','Subtotal','Diskon Auto','Diskon Voucher','Pajak','Total','Refund Uang','Total Nett']);
            foreach ($rows as $s) {
                $refund = (int)($refundMap[$s->id] ?? 0);
                fputcsv($out, [
                    $s->created_at->format('Y-m-d H:i'),
                    $s->code,
                    (int)$s->subtotal,
                    (int)$s->auto_discount,
                    (int)$s->voucher_discount,
                    (int)$s->tax_amount,
                    (int)$s->total,
                    $refund,
                    (int)$s->total - $refund,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function printable(Request $request)
    {
        $user   = $request->user();
        $range  = $request->get('range', 'month');
        $anchor = $request->input('at') ? Carbon::parse($request->input('at')) : now();
        [$start, $end] = $this->makeWindowAndGrouping($range, $anchor);

        $sales = Sale::with('user')
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) as refund_cash')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        $gross  = (int) $sales->sum('total');
        $refund = (int) DB::table('sale_returns')
            ->where('mode','refund')
            ->whereBetween('created_at',[$start,$end])
            ->whereIn('sale_id', $sales->pluck('id'))
            ->sum('refund_total');
        $nett   = $gross - $refund;

        return view('user/reports/print', compact('sales','refundMap','gross','refund','nett','range','start','end'));
    }

    // ----------------- Helpers -----------------

    private function makeWindowAndGrouping(string $range, \DateTimeInterface $anchor)
    {
        $anchor = Carbon::parse($anchor);
        switch ($range) {
            case 'day':   // per jam
                $start = $anchor->copy()->startOfDay();
                $end   = $anchor->copy()->endOfDay()->addSecond();
                $groupExpr   = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
                $labelFormat = 'H:i';
                break;
            case 'week':  // per hari
                $start = $anchor->copy()->startOfWeek();
                $end   = $anchor->copy()->endOfWeek()->addDay();
                $groupExpr   = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $labelFormat = 'd M';
                break;
            case 'year':  // per bulan
                $start = $anchor->copy()->startOfYear();
                $end   = $anchor->copy()->endOfYear()->addMonth();
                $groupExpr   = "DATE_FORMAT(created_at, '%Y-%m')";
                $labelFormat = 'M Y';
                break;
            case 'month':
            default:      // per hari dalam bulan
                $start = $anchor->copy()->startOfMonth();
                $end   = $anchor->copy()->endOfMonth()->addDay();
                $groupExpr   = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $labelFormat = 'd M';
        }
        return [$start, $end, $groupExpr, $labelFormat];
    }

    private function bucketKey(string $range, Carbon $dt): string
    {
        return match ($range) {
            'day'   => $dt->format('Y-m-d H:00:00'),
            'week'  => $dt->format('Y-m-d'),
            'year'  => $dt->format('Y-m'),
            default => $dt->format('Y-m-d'),
        };
    }

    private function stepCursor(string $range, Carbon $dt)
    {
        return match ($range) {
            'day'   => $dt->copy()->addHour(),
            'week'  => $dt->copy()->addDay(),
            'year'  => $dt->copy()->addMonth(),
            default => $dt->copy()->addDay(),
        };
    }
}
