<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('sale_returns', function (Blueprint $t) {
      $t->id();
      $t->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
      $t->foreignId('processed_by')->constrained('users');
      $t->enum('mode', ['refund','exchange']);       // uang atau tukar
      $t->unsignedBigInteger('subtotal_refund');      // G
      $t->unsignedBigInteger('auto_share');           // DA_ret
      $t->unsignedBigInteger('voucher_share');        // DV_ret
      $t->unsignedBigInteger('dpp_refund');           // DPP_ret
      $t->decimal('tax_rate',5,2)->default(0);        // rate saat transaksi
      $t->unsignedBigInteger('tax_refund')->default(0);
      $t->unsignedBigInteger('refund_total');         // DPP_ret + tax_refund
      $t->unsignedBigInteger('replacement_sale_id')->nullable(); // kalau exchange
      $t->string('notes')->nullable();
      $t->timestamps();
    });

    Schema::create('sale_return_items', function (Blueprint $t) {
      $t->id();
      $t->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
      $t->foreignId('sale_item_id')->constrained('sale_items')->cascadeOnDelete(); // referensi item asli
      // snapshot minimal:
      $t->string('kode_barang');
      $t->string('nama_barang');
      $t->unsignedBigInteger('harga_jual');
      $t->unsignedInteger('qty_refund');
      $t->enum('condition', ['normal','damaged'])->default('normal');

      $t->unsignedBigInteger('line_subtotal');  // g_i
      $t->unsignedBigInteger('auto_share');     // da_i
      $t->unsignedBigInteger('voucher_share');  // dv_i
      $t->unsignedBigInteger('dpp_refund');     // dpp_i
      $t->unsignedBigInteger('tax_refund');     // tax_i
      $t->unsignedBigInteger('refund_amount');  // dpp_i + tax_i
      $t->timestamps();
    });

    Schema::create('product_damage_logs', function (Blueprint $t) {
      $t->id();
      $t->string('kode_barang');
      $t->unsignedInteger('qty');
      $t->foreignId('sale_return_id')->nullable()->constrained('sale_returns')->nullOnDelete();
      $t->string('notes')->nullable();
      $t->foreignId('created_by')->constrained('users');
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::dropIfExists('product_damage_logs');
    Schema::dropIfExists('sale_return_items');
    Schema::dropIfExists('sale_returns');
  }
};
