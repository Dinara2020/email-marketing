<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_smtp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index(); // For SaaS multi-tenancy
            $table->string('host')->nullable();
            $table->integer('port')->default(587);
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted
            $table->string('encryption')->default('tls'); // tls, ssl, null
            $table->string('from_address')->nullable();
            $table->string('from_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_smtp_settings');
    }
};
