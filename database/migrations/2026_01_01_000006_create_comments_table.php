<?php

use App\Enums\CommentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            // A comment cannot outlive its post.
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();

            // Self-referencing FK: a reply points at the comment it answers.
            // Deleting a parent removes its replies too.
            $table->foreignId('parent_id')->nullable()
                ->constrained('comments')->cascadeOnDelete();

            // Set when a logged-in user comments; guests leave it null and
            // supply name + email instead.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('author_name');
            $table->string('author_email');
            $table->text('body');
            $table->string('status', 20)->default(CommentStatus::Pending->value);
            $table->timestamps();

            // Serves "approved comments of this post", the only public query.
            $table->index(['post_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
