<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
  protected $fillable = [
    'sale_return_id','sale_item_id','kode_barang','nama_barang','harga_jual',
    'qty_refund','condition','line_subtotal','auto_share','voucher_share',
    'dpp_refund','tax_refund','refund_amount'
  ];

  public function return(){ return $this->belongsTo(SaleReturn::class,'sale_return_id'); }
  public function saleItem(){ return $this->belongsTo(SaleItem::class); }
}
