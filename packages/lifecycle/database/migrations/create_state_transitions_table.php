<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('lifecycle.table', 'state_transitions'), function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('attribute', 64);
            $table->string('from', 64)->nullable();
            $table->string('to', 64);
            $table->nullableMorphs('actor');
            $table->string('reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['subject_type', 'to', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('lifecycle.table', 'state_transitions'));
    }
};
