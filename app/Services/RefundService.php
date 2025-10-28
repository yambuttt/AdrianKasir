<?php
namespace App\Services;

use App\Models\Sale;
use App\Models\SaleItem;

class RefundService
{
  /** Bagi integer $total sesuai bobot $weights dan pastikan jumlahnya == $total */
  private function allocate(array $weights, int $total): array
  {
    $sum = array_sum($weights);
    if ($sum <= 0 || $total <= 0) return array_fill(0, count($weights), 0);

    $raw = [];
    $floors = [];
    $remainders = [];
    $acc = 0;

    foreach ($weights as $i => $w) {
      $val = ($w * $total) / $sum;
      $raw[$i] = $val;
      $floors[$i] = (int) floor($val);
      $remainders[$i] = $val - $floors[$i];
      $acc += $floors[$i];
    }

    $left = $total - $acc;
    arsort($remainders); // largest remainder
    foreach (array_keys($remainders) as $i) {
      if ($left <= 0) break;
      $floors[$i] += 1;
      $left--;
    }
    return $floors;
  }

  /**
   * @param Sale $sale  transaksi asli (sudah punya subtotal/discount/tax_rate)
   * @param array $rows array [ ['sale_item'=>SaleItem, 'qty_refund'=>int], ... ]
   * @return array ['summary'=>..., 'lines'=>...]
   */
  public function compute(Sale $sale, array $rows): array
  {
    // 1) kumpulkan g_i
    $g = []; $lineMeta = []; $G = 0;
    foreach ($rows as $row) {
      /** @var SaleItem $it */
      $it = $row['sale_item'];
      $qty = (int)$row['qty_refund'];
      if ($qty <= 0) { $g[] = 0; $lineMeta[] = null; continue; }

      $lineSubtotal = (int)$it->harga_jual * $qty;
      $g[] = $lineSubtotal;
      $lineMeta[] = [
        'sale_item_id' => $it->id,
        'kode_barang'  => $it->kode_barang,
        'nama_barang'  => $it->nama_barang,
        'harga_jual'   => (int)$it->harga_jual,
        'qty_refund'   => $qty,
      ];
      $G += $lineSubtotal;
    }

    $S  = (int)$sale->subtotal;
    $DA = (int)$sale->auto_discount;
    $DV = (int)$sale->voucher_discount;
    $rate = (float)$sale->tax_rate;

    // 2) total share pro-rata terhadap S (bukan terhadap G) agar konsisten bila retur sebagian
    $DA_total = (int) round(($G * $DA) / max(1, $S));
    $DV_total = (int) round(($G * $DV) / max(1, $S));

    // 3) bagi DA/DV ke level baris → pakai g_i sebagai bobot
    $da_i = $this->allocate($g, $DA_total);
    $dv_i = $this->allocate($g, $DV_total);

    // 4) dpp_i, tax_i (tax_total dihitung dari Σdpp_i lalu dialokasikan kembali → konsisten pembulatan)
    $dpp_i = [];
    $sum_dpp = 0;
    foreach ($g as $i => $gi) {
      $dpp = max(0, $gi - $da_i[$i] - $dv_i[$i]);
      $dpp_i[$i] = $dpp;
      $sum_dpp += $dpp;
    }
    $tax_total = (int) floor($sum_dpp * ($rate / 100.0)); // sama dgn TaxService
    $tax_i = $this->allocate($dpp_i, $tax_total);

    // 5) rangkum per baris
    $lines = [];
    foreach ($g as $i => $gi) {
      if ($lineMeta[$i] === null) continue;
      $refund = $dpp_i[$i] + $tax_i[$i];
      $lines[] = array_merge($lineMeta[$i], [
        'line_subtotal' => $gi,
        'auto_share'    => $da_i[$i],
        'voucher_share' => $dv_i[$i],
        'dpp_refund'    => $dpp_i[$i],
        'tax_refund'    => $tax_i[$i],
        'refund_amount' => $refund,
      ]);
    }

    return [
      'summary' => [
        'subtotal_refund' => $G,
        'auto_share'      => array_sum($da_i),
        'voucher_share'   => array_sum($dv_i),
        'dpp_refund'      => $sum_dpp,
        'tax_rate'        => $rate,
        'tax_refund'      => $tax_total,
        'refund_total'    => $sum_dpp + $tax_total,
      ],
      'lines' => $lines,
    ];
  }
}
