<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Laporan nett:
     *  - Omzet nett = SUM(sales.total) - SUM(refund_uang.refund_total)
     *  - Tukar barang diabaikan (refund_total = 0)
     * Filter: range=day|week|month|year dan anchor tanggal (default: hari ini).
     */
    public function index(Request $request)
    {
        $range = $request->get('range', 'month');

        // Ambil anchor dengan aman: kalau query ?at= kosong -> pakai now()
        $anchorInput = $request->input('at');
        $anchor = $anchorInput ? \Illuminate\Support\Carbon::parse($anchorInput) : now();

        [$start, $end, $groupExpr, $labelFormat] = $this->makeWindowAndGrouping($range, $anchor);

        // Data transaksi pada window
        $salesQ = Sale::query()
            ->whereBetween('created_at', [$start, $end]);

        // Refund uang pada window (mode=refund)
        $refundQ = DB::table('sale_returns')
            ->whereBetween('created_at', [$start, $end])
            ->where('mode', 'refund');

        // --- Grafik: agregasi per bucket waktu
        $buckets = DB::table('sales')
            ->selectRaw("$groupExpr AS bucket, SUM(total) AS gross")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->pluck('gross', 'bucket');

        $refundBuckets = DB::table('sale_returns')
            ->selectRaw("$groupExpr AS bucket, SUM(refund_total) AS refund_cash")
            ->whereBetween('created_at', [$start, $end])
            ->where('mode', 'refund')
            ->groupBy('bucket')
            ->pluck('refund_cash', 'bucket');

        // Build labels + data nett (sinkron dari rentang)
        $labels = [];
        $dataNett = [];
        $cursor = $start->copy();
        while ($cursor < $end) {
            $bucketKey = $this->bucketKey($range, $cursor);
            $labels[] = $cursor->format($labelFormat);
            $gross = (int) ($buckets[$bucketKey] ?? 0);
            $refund = (int) ($refundBuckets[$bucketKey] ?? 0);
            $dataNett[] = $gross - $refund;
            $cursor = $this->stepCursor($range, $cursor);
        }

        // --- Summary dan tabel transaksi (nett per transaksi)
        $summaryGross = (int) $salesQ->sum('total');
        $summaryRefund = (int) $refundQ->sum('refund_total');
        $summaryNett = $summaryGross - $summaryRefund;

        $sales = Sale::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        // refund per sale untuk halaman terpaginasinya
        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) as refund_cash')
            ->where('mode', 'refund')
            ->whereIn('sale_id', $sales->pluck('id'))
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        foreach ($sales as $s) {
            $s->refund_cash = (int) ($refundMap[$s->id] ?? 0); // <-- tambahkan ini
            $s->net_total = (int) $s->total - $s->refund_cash; // sudah ada, tetap gunakan refund_cash
        }

        return view('admin/reports/index', [
            'range' => $range,
            'anchor' => $anchor,
            'start' => $start,
            'end' => $end,
            'labels' => $labels,
            'dataNett' => $dataNett,
            'summary' => [
                'gross' => $summaryGross,
                'refund' => $summaryRefund,
                'nett' => $summaryNett,
            ],
            'sales' => $sales,
        ]);
    }

    public function exportCsv(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $range = $request->get('range', 'month');
        $anchorInput = $request->input('at');
        $anchor = $anchorInput ? \Illuminate\Support\Carbon::parse($anchorInput) : now();

        [$start, $end] = $this->makeWindowAndGrouping($range, $anchor);

        $rows = Sale::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) as refund_cash')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        $filename = 'laporan-transaksi-' . now()->format('Ymd_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        return response()->stream(function () use ($rows, $refundMap) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Kode', 'Kasir', 'Subtotal', 'Diskon Auto', 'Diskon Voucher', 'Pajak', 'Total', 'Refund Uang', 'Total Nett']);
            foreach ($rows as $s) {
                $refund = (int) ($refundMap[$s->id] ?? 0);
                fputcsv($out, [
                    $s->created_at->format('Y-m-d H:i'),
                    $s->code,
                    optional($s->user)->name,
                    (int) $s->subtotal,
                    (int) $s->auto_discount,
                    (int) $s->voucher_discount,
                    (int) $s->tax_amount,
                    (int) $s->total,
                    $refund,
                    (int) $s->total - $refund,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /** Printable HTML -> pengguna bisa Cetak → Simpan sebagai PDF dari browser */
    public function printable(Request $request)
    {
        $range = $request->get('range', 'month');
        $anchorInput = $request->input('at');
        $anchor = $anchorInput ? \Illuminate\Support\Carbon::parse($anchorInput) : now();

        [$start, $end] = $this->makeWindowAndGrouping($range, $anchor);

        $sales = Sale::with('user')
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get();

        $refundMap = DB::table('sale_returns')
            ->selectRaw('sale_id, SUM(refund_total) as refund_cash')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('sale_id')
            ->pluck('refund_cash', 'sale_id');

        // summary
        $gross = (int) $sales->sum('total');
        $refund = (int) DB::table('sale_returns')
            ->where('mode', 'refund')
            ->whereBetween('created_at', [$start, $end])
            ->sum('refund_total');
        $nett = $gross - $refund;

        return view('admin/reports/print', compact('sales', 'refundMap', 'gross', 'refund', 'nett', 'range', 'start', 'end'));
    }

    // ----------------- Helpers -----------------

    private function makeWindowAndGrouping(string $range, \DateTimeInterface $anchor)
    {
        $anchor = \Illuminate\Support\Carbon::parse($anchor);
        switch ($range) {
            case 'day': // per jam
                $start = $anchor->copy()->startOfDay();
                $end = $anchor->copy()->endOfDay()->addSecond();
                $groupExpr = "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')";
                $labelFormat = 'H:i';
                break;
            case 'week': // per hari (7)
                $start = $anchor->copy()->startOfWeek();
                $end = $anchor->copy()->endOfWeek()->addDay();
                $groupExpr = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $labelFormat = 'd M';
                break;
            case 'year': // per bulan (12)
                $start = $anchor->copy()->startOfYear();
                $end = $anchor->copy()->endOfYear()->addMonth();
                $groupExpr = "DATE_FORMAT(created_at, '%Y-%m')";
                $labelFormat = 'M Y';
                break;
            case 'month':
            default: // per hari dalam bulan
                $start = $anchor->copy()->startOfMonth();
                $end = $anchor->copy()->endOfMonth()->addDay();
                $groupExpr = "DATE_FORMAT(created_at, '%Y-%m-%d')";
                $labelFormat = 'd M';
        }
        return [$start, $end, $groupExpr, $labelFormat];
    }

    private function bucketKey(string $range, \Illuminate\Support\Carbon $dt): string
    {
        return match ($range) {
            'day' => $dt->format('Y-m-d H:00:00'),
            'week' => $dt->format('Y-m-d'),
            'year' => $dt->format('Y-m'),
            default => $dt->format('Y-m-d'),
        };
    }

    private function stepCursor(string $range, \Illuminate\Support\Carbon $dt)
    {
        return match ($range) {
            'day' => $dt->copy()->addHour(),
            'week' => $dt->copy()->addDay(),
            'year' => $dt->copy()->addMonth(),
            default => $dt->copy()->addDay(),
        };
    }
}
