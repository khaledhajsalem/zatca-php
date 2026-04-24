<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('zatca.storage.database.connection');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('zatca_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('solution_name')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('org_identifier', 15)->nullable();
            $table->enum('environment', ['sandbox', 'simulation', 'production'])->default('sandbox');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('zatca_devices');
    }
};
