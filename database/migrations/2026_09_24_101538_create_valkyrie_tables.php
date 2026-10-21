<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function down(): void
    {
        Schema::dropIfExists(config()->string('valkyrie.tables.permissions'));
        Schema::dropIfExists(config()->string('valkyrie.tables.assigned_roles'));
        Schema::dropIfExists(config()->string('valkyrie.tables.roles'));
        Schema::dropIfExists(config()->string('valkyrie.tables.abilities'));
    }

    public function up(): void
    {
        $abilities = config()->string('valkyrie.tables.abilities');
        $roles = config()->string('valkyrie.tables.roles');
        $assignedRoles = config()->string('valkyrie.tables.assigned_roles');
        $permissions = config()->string('valkyrie.tables.permissions');

        Schema::create($abilities, function (Blueprint $table) use ($abilities): void {
            $table->id();
            $table->string('name', 100);
            $table->string('title')->nullable();
            $table->nullableMorphs('entity');
            $table->boolean('only_owned')->default(false);
            $table->json('options')->nullable();
            $table->timestamps();
            $table->unique(['name', 'entity_type', 'entity_id', 'only_owned'], "{$abilities}_unique");
        });

        Schema::create($roles, function (Blueprint $table) use ($roles): void {
            $table->id();
            $table->string('name', 100);
            $table->string('title')->nullable();
            $table->nullableMorphs('scope');
            $table->timestamps();
            $table->unique(['name', 'scope_type', 'scope_id'], "{$roles}_unique");
        });

        Schema::create($assignedRoles, function (Blueprint $table) use ($assignedRoles, $roles): void {
            $table->id();
            $table->foreignId('role_id')->constrained($roles)->cascadeOnDelete();
            $table->morphs('entity');
            $table->nullableMorphs('scope');
            $table->unique(['role_id', 'entity_type', 'entity_id', 'scope_type', 'scope_id'], "{$assignedRoles}_unique");
        });

        Schema::create($permissions, function (Blueprint $table) use ($permissions, $abilities): void {
            $table->id();
            $table->foreignId('ability_id')->constrained($abilities)->cascadeOnDelete();
            $table->morphs('entity');
            $table->boolean('forbidden')->default(false);
            $table->json('except')->nullable();
            $table->nullableMorphs('scope');
            $table->unique(['ability_id', 'entity_type', 'entity_id', 'forbidden', 'scope_type', 'scope_id'], "{$permissions}_unique");
        });
    }
};
