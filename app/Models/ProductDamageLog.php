<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDamageLog extends Model
{
  protected $fillable = ['kode_barang','qty','sale_return_id','notes','created_by'];

   public function saleReturn()
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function sale()
    {
        return $this->saleReturn?->sale();
    }
}
