<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('finance_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 32)->unique();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->date('issue_date');
            $table->date('due_date');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('paid_total', 12, 2)->default(0);
            $table->string('status', 32)->default('open')->index();
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['due_date', 'status']);
        });

        Schema::connection('tenant')->create('finance_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->cascadeOnDelete();
            $table->string('title', 160);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('tenant')->create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_invoice_id')->constrained('finance_invoices')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 32);
            $table->timestamp('paid_at');
            $table->string('reference', 128)->nullable()->index();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by_central_user_id')->nullable()->index();
            $table->timestamps();

            $table->index(['finance_invoice_id', 'paid_at']);
        });

        Schema::connection('tenant')->create('finance_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('students')->cascadeOnDelete();
            $table->string('title', 160);
            $table->decimal('amount', 12, 2);
            $table->string('type', 32)->default('fixed');
            $table->string('status', 32)->default('active')->index();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('finance_discounts');
        Schema::connection('tenant')->dropIfExists('finance_payments');
        Schema::connection('tenant')->dropIfExists('finance_invoice_lines');
        Schema::connection('tenant')->dropIfExists('finance_invoices');
    }
};
