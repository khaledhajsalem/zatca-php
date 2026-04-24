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
        Schema::connection($this->getConnection())->create('zatca_api_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('invoice_id')->nullable()->constrained('zatca_invoices')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('zatca_devices')->nullOnDelete();
            $table->string('endpoint');
            $table->string('method', 10)->default('POST');
            $table->string('environment', 20)->nullable();
            $table->json('request_headers')->nullable();
            $table->json('request_body')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_headers')->nullable();
            $table->json('response_body')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->enum('status', ['success', 'error', 'warning', 'timeout'])->default('success');
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index('endpoint');
            $table->index('status');
            $table->index('created_at');
            $table->index('response_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('zatca_api_logs');
    }
};
