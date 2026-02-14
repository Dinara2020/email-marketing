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
        // Email Templates
        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('subject');
                $table->longText('body_html');
                $table->longText('body_text')->nullable();
                $table->json('variables')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Email Campaigns
        if (!Schema::hasTable('email_campaigns')) {
            Schema::create('email_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('template_id')->constrained('email_templates')->cascadeOnDelete();
                $table->enum('status', ['draft', 'sending', 'paused', 'completed'])->default('draft');
                $table->integer('total_recipients')->default(0);
                $table->integer('sent_count')->default(0);
                $table->integer('opened_count')->default(0);
                $table->integer('failed_count')->default(0);
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('created_at');
            });
        }

        // Email Sends
        if (!Schema::hasTable('email_sends')) {
            Schema::create('email_sends', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
                $table->unsignedBigInteger('hotel_id')->nullable();
                $table->string('email');
                $table->string('recipient_name')->nullable();
                $table->enum('status', ['pending', 'sent', 'opened', 'failed', 'bounced'])->default('pending');
                $table->uuid('tracking_id')->unique();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('opened_at')->nullable();
                $table->integer('open_count')->default(0);
                $table->string('opened_ip')->nullable();
                $table->text('opened_user_agent')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index('status');
                $table->index('tracking_id');
                $table->index(['campaign_id', 'status']);
            });
        }

        // Email Clicks
        if (!Schema::hasTable('email_clicks')) {
            Schema::create('email_clicks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('email_send_id')->constrained('email_sends')->cascadeOnDelete();
                $table->text('url');
                $table->string('ip')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index('email_send_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_clicks');
        Schema::dropIfExists('email_sends');
        Schema::dropIfExists('email_campaigns');
        Schema::dropIfExists('email_templates');
    }
};
