<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddZohoQuoteIdToAiQuoteRequests extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ai_quote_requests', function (Blueprint $table) {
            $table->string('zoho_quote_id')->nullable()->after('crm_account_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ai_quote_requests', function (Blueprint $table) {
            $table->dropColumn('zoho_quote_id');
        });
    }
}
