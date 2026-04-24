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
        Schema::connection($this->getConnection())->create('zatca_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('zatca_devices')->cascadeOnDelete();
            $table->enum('type', ['compliance', 'production']);
            $table->text('certificate_pem');
            $table->text('private_key_pem');
            $table->text('secret');
            $table->string('request_id')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('issuer', 500)->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->enum('status', ['active', 'expired', 'revoked', 'pending'])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('valid_until');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('zatca_certificates');
    }
};
