<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\WarehouseApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    public function __construct(protected WarehouseApiService $warehouse)
    {
    }

    public function ambil(Request $request)
    {
        $data = $request->validate([
            'kode_barang' => 'required|string',
            'qty' => 'required|integer|min:1',
        ]);


        $gudangRes = $this->warehouse->getAllBarang();

        if (!$gudangRes->successful() || $gudangRes->json('status') !== 'success') {
            return response()->json([
                'status' => 'failed',
                'message' => 'Tidak dapat memeriksa stok gudang. Coba refresh data gudang dulu.',
            ], 422);
        }

        $barang = collect($gudangRes->json('data') ?? [])
            ->firstWhere('kode_barang', $data['kode_barang']);

        if (!$barang) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Barang tidak ditemukan di gudang.',
            ], 422);
        }

        $stokGudang = (int) ($barang['stok_barang'] ?? 0);

        if ($data['qty'] > $stokGudang) {
            return response()->json([
                'status' => 'failed',
                'message' => "Stok gudang tidak mencukupi. Stok tersedia {$stokGudang}, diminta {$data['qty']}.",
            ], 422);
        }

  
        return DB::transaction(function () use ($data, $request) {
            $transfer = StockTransfer::create([
                'transfer_uid' => Str::uuid(),
                'kode_barang' => $data['kode_barang'],
                'direction' => 'inbound_from_warehouse',
                'qty' => $data['qty'],
                'created_by' => $request->user()->id,
                'status' => 'pending',
            ]);

            $payload = ['qty' => $data['qty'], 'operation' => 'kurangi'];
            $res = $this->warehouse->updateStok($data['kode_barang'], $data['qty'], 'kurangi');
            $transfer->warehouse_payload = $payload;

            if ($res->successful() && $res->json('status') === 'success') {
                $transfer->warehouse_response = $res->json();
                $transfer->status = 'committed';

                $barang = $res->json('data');

                Product::updateOrCreate(
                    ['kode_barang' => $barang['kode_barang']],
                    [
                        'nama_barang' => $barang['nama_barang'] ?? 'Tanpa Nama',
                        'kategori_barang' => $barang['kategori_barang'] ?? null,
                        'jenis_barang' => $barang['jenis_barang'] ?? null,
                        'unit_barang' => $barang['unit_barang'] ?? null,
                        'stok_kasir' => DB::raw("stok_kasir + {$data['qty']}"),
                        'status_kasir' => 'Tersedia',
                    ]
                );
            } else {
                $transfer->status = 'failed';
                $transfer->warehouse_response = $res->json();
            }

            $transfer->save();

            return response()->json([
                'status' => $transfer->status,
                'message' => $transfer->status === 'committed'
                    ? 'Berhasil ambil stok dari gudang'
                    : 'Gagal ambil stok dari gudang',
                'data' => $transfer,
            ]);
        });
    }

    public function index()
    {

        $produk = \App\Models\Product::orderBy('nama_barang')->get();


        $damageTotals = \App\Models\ProductDamageLog::query()
            ->selectRaw('kode_barang, SUM(qty) AS damaged_total')
            ->groupBy('kode_barang')
            ->pluck('damaged_total', 'kode_barang');


        $rows = \DB::table('product_damage_logs AS d')
            ->leftJoin('sale_returns AS r', 'r.id', '=', 'd.sale_return_id')
            ->leftJoin('sales AS s', 's.id', '=', 'r.sale_id')
            ->selectRaw('d.kode_barang, d.qty, d.notes, r.mode, s.code AS sale_code, r.created_at AS at')
            ->orderByDesc('d.id')
            ->limit(200)
            ->get()
            ->groupBy('kode_barang');


        $produk->transform(function ($p) use ($damageTotals, $rows) {
            $p->damaged_total = (int) ($damageTotals[$p->kode_barang] ?? 0);
            $list = collect($rows[$p->kode_barang] ?? [])->map(function ($r) {
                return [
                    'sale_code' => $r->sale_code ?? '-',
                    'mode' => $r->mode ?? '-',
                    'qty' => (int) $r->qty,
                    'notes' => $r->notes,
                    'at' => $r->at ? \Carbon\Carbon::parse($r->at)->format('d M Y H:i') : null,
                ];
            });

            $p->damage_logs = $list->take(5)->values();
            return $p;
        });

        return view('admin.stock.index', compact('produk'));
    }

}

