<?php

use App\Enums\PostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();

            // why: restrictOnDelete - you must not be able to delete an author
            // and silently take their articles with them.
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Nullable + nullOnDelete: deleting a category leaves its posts
            // in place, merely uncategorised.
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->text('body');                       // Markdown source, not HTML
            $table->string('cover_image')->nullable();  // path on the "public" disk
            $table->string('status', 20)->default(PostStatus::Draft->value);
            $table->timestampTz('published_at')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->softDeletes();                      // trash/restore for editors
            $table->timestamps();

            // why: every public page filters on exactly this pair
            // (status = published AND published_at <= now), so one composite
            // index serves the whole front end.
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
