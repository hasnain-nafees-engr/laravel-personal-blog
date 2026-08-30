<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Audit trail feeding the admin dashboard's "recent activity" list.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60);   // e.g. post.published, comment.approved

            // why: morphs() creates subject_type + subject_id and indexes them
            // together. One table can then describe an action on a Post, a
            // Comment or anything we add later - that is what "polymorphic"
            // buys us over one FK column per model.
            $table->morphs('subject');

            // Log rows are written once and never edited, so updated_at would
            // always be a copy of created_at.
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
