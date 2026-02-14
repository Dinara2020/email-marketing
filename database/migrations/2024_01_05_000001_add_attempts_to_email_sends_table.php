<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check column existence without Schema::hasColumn (MySQL < 5.7.8 compatibility)
        $columns = DB::select("SHOW COLUMNS FROM email_sends LIKE 'attempts'");

        if (empty($columns)) {
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
        $columns = DB::select("SHOW COLUMNS FROM email_sends LIKE 'attempts'");

        if (!empty($columns)) {
            Schema::table('email_sends', function (Blueprint $table) {
                $table->dropColumn('attempts');
            });
        }
    }
};
