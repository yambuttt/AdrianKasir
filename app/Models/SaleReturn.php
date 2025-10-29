<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
  protected $fillable = [
    'sale_id',
    'processed_by',
    'mode',
    'subtotal_refund',
    'auto_share',
    'voucher_share',
    'dpp_refund',
    'tax_rate',
    'tax_refund',
    'refund_total',
    'replacement_sale_id',
    'notes'
  ];

  public function sale()
  {
    return $this->belongsTo(Sale::class);
  }
  public function items()
  {
    return $this->hasMany(SaleReturnItem::class);
  }
  public function processedBy()
  {
    return $this->belongsTo(User::class, 'processed_by');
  }
}
