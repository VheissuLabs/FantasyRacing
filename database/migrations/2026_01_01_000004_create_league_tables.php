<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('franchise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('commissioner_id')->constrained('users')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->integer('max_teams')->nullable();
            $table->jsonb('rules');
            $table->string('invite_code')->nullable()->unique();
            $table->enum('visibility', ['public', 'private'])->default('private');
            $table->enum('join_policy', ['open', 'request', 'invite_only'])->default('invite_only');
            $table->boolean('is_active')->default(true);
            $table->timestamp('draft_completed_at')->nullable();
            $table->timestamps();
            $table->index(['franchise_id', 'season_id', 'visibility']);
        });

        Schema::create('league_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['commissioner', 'member'])->default('member');
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['league_id', 'user_id']);
        });

        Schema::create('league_join_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['league_id', 'user_id']);
            $table->index(['league_id', 'status']);
        });

        Schema::create('league_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('token')->unique();
            $table->enum('status', ['pending', 'accepted', 'expired', 'cancelled'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('league_id');
            $table->unique(['league_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_invites');
        Schema::dropIfExists('league_join_requests');
        Schema::dropIfExists('league_members');
        Schema::dropIfExists('leagues');
    }
};
