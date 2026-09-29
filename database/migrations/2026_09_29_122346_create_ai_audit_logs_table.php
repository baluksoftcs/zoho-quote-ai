<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiAuditLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ai_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_quote_request_id')->constrained()->cascadeOnDelete();
            $table->string('zoho_request_id');
            $table->text('notes');
            $table->string('model');
            $table->json('raw_ai_output')->nullable();     // exactly what Claude returned
            $table->json('validated_output')->nullable();  // what survived validation
            $table->json('resolution')->nullable();        // product matching results
            $table->json('pricing')->nullable();           // prices fetched + their source + totals
            $table->json('warnings')->nullable();
            $table->string('zoho_quote_id')->nullable();
            $table->string('status')->default('started');
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
        Schema::dropIfExists('ai_audit_logs');
    }
}
