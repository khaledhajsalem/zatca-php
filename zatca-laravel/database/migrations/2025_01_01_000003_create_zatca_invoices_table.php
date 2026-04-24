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
        Schema::connection($this->getConnection())->create('zatca_invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('device_id')->nullable()->constrained('zatca_devices')->nullOnDelete();
            $table->string('invoice_number');
            $table->string('invoice_type_code', 10)->nullable();
            $table->string('invoice_type_name', 20)->nullable();
            $table->date('issue_date')->nullable();
            $table->time('issue_time')->nullable();
            $table->string('currency_code', 3)->default('SAR');
            $table->string('seller_name')->nullable();
            $table->string('seller_vat', 15)->nullable();
            $table->string('buyer_name')->nullable();
            $table->string('buyer_vat', 15)->nullable();
            $table->decimal('tax_exclusive_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('tax_inclusive_amount', 15, 2)->default(0);
            $table->decimal('payable_amount', 15, 2)->default(0);
            $table->unsignedInteger('icv')->nullable();
            $table->string('pih')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->text('qr_code')->nullable();
            $table->longText('signed_xml')->nullable();
            $table->longText('cleared_xml')->nullable();
            $table->enum('submission_type', ['clearance', 'reporting'])->nullable();
            $table->enum('status', ['draft', 'pending', 'submitted', 'cleared', 'reported', 'rejected', 'warning'])->default('draft');
            $table->string('zatca_status', 50)->nullable();
            $table->json('zatca_warnings')->nullable();
            $table->json('zatca_errors')->nullable();
            $table->json('zatca_response')->nullable();
            $table->string('billing_reference_id')->nullable();
            $table->string('billing_reference_uuid')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('invoice_number');
            $table->index('issue_date');
            $table->index('submitted_at');
            $table->index('seller_vat');
            $table->index('buyer_vat');
            $table->index('zatca_status');
            $table->index('invoice_type_code');
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('zatca_invoices');
    }
};
