<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiQuoteRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('zoho_request_id')->unique();   // Creator record ID; unique = no duplicates
            $table->string('zoho_customer_id')->nullable();
            $table->string('crm_account_id')->nullable();
            $table->text('notes');
            $table->string('status')->default('received'); // received / processing / done / failed
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ai_quote_requests');
    }
}
