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
        Schema::connection($this->getConnection())->create('zatca_invoice_chains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('zatca_devices')->cascadeOnDelete();
            $table->string('invoice_type_name', 20);
            $table->unsignedInteger('last_icv')->default(0);
            $table->string('last_pih')->default('NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==');
            $table->foreignId('last_invoice_id')->nullable()->constrained('zatca_invoices')->nullOnDelete();
            $table->timestamp('updated_at')->nullable();

            $table->unique(['device_id', 'invoice_type_name']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('zatca_invoice_chains');
    }
};
