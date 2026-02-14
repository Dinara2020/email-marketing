<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('email_sends') && !Schema::hasColumn('email_sends', 'attempts')) {
            Schema::table('email_sends', function (Blueprint $table) {
                $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('email_sends', 'attempts')) {
            Schema::table('email_sends', function (Blueprint $table) {
                $table->dropColumn('attempts');
            });
        }
    }
};
