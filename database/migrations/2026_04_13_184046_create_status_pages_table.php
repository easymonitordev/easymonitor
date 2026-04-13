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
        Schema::create('status_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('footer_text')->nullable();

            // Visibility
            $table->string('visibility', 20)->default('public'); // public | unlisted | private
            $table->string('access_key', 64)->nullable();

            // Branding (Ship 2)
            $table->string('logo_path')->nullable();
            $table->string('theme')->default('business');
            $table->text('custom_css')->nullable();

            // Custom domain (Ship 3)
            $table->string('custom_domain')->nullable()->unique();
            $table->timestamp('domain_verified_at')->nullable();

            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['team_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_pages');
    }
};
