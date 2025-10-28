<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDamageLog extends Model
{
  protected $fillable = ['kode_barang','qty','sale_return_id','notes','created_by'];
}
