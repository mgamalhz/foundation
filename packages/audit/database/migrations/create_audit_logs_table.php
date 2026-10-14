<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('audit.table', 'audit_logs'), function (Blueprint $table) {
            $table->id();
            $table->string('action', 120);
            // Nullable morphs: a system job has no actor; a login attempt may have no subject.
            $table->nullableMorphs('actor');
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('audit.table', 'audit_logs'));
    }
};
