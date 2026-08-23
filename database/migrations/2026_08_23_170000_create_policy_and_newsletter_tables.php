<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_pages', function (Blueprint $table): void { $table->id(); $table->string('policy_key')->unique(); $table->string('title'); $table->string('slug')->unique(); $table->text('review_notice'); $table->unsignedBigInteger('current_version_id')->nullable(); $table->timestamps(); });
        Schema::create('policy_versions', function (Blueprint $table): void { $table->id(); $table->foreignId('policy_page_id')->constrained()->cascadeOnDelete(); $table->unsignedInteger('version_number'); $table->longText('body'); $table->string('publication_state')->default('draft'); $table->timestamp('published_at')->nullable(); $table->timestamps(); $table->unique(['policy_page_id', 'version_number']); });
        Schema::create('policy_consents', function (Blueprint $table): void { $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('policy_version_id')->constrained()->restrictOnDelete(); $table->string('action'); $table->timestamp('accepted_at'); $table->timestamp('withdrawn_at')->nullable(); $table->timestamps(); $table->unique(['user_id', 'policy_version_id', 'action']); });
        Schema::create('email_templates', function (Blueprint $table): void { $table->id(); $table->string('template_key')->unique(); $table->string('subject'); $table->text('intro_text'); $table->string('action_label')->nullable(); $table->text('closing_text')->nullable(); $table->timestamps(); });
        Schema::create('newsletters', function (Blueprint $table): void { $table->id(); $table->string('title'); $table->string('subject'); $table->longText('body'); $table->string('status')->default('draft')->index(); $table->timestamp('scheduled_for')->nullable()->index(); $table->timestamp('sent_at')->nullable(); $table->json('audience_roles'); $table->json('audience_account_statuses'); $table->boolean('consent_required')->default(true); $table->foreignId('consent_policy_version_id')->nullable()->constrained('policy_versions')->restrictOnDelete(); $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete(); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('newsletters'); Schema::dropIfExists('email_templates'); Schema::dropIfExists('policy_consents'); Schema::dropIfExists('policy_versions'); Schema::dropIfExists('policy_pages'); }
};
