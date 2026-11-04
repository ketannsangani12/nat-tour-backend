<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per mass cancellation run. The bookings are cancelled by a chain
     * of queued jobs (one per booking) which update `results` as they go, so
     * the page can show live progress and resume after a reload.
     */
    public function up(): void
    {
        Schema::create('mass_cancellations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tour_package_id');
            $table->date('date');
            $table->time('time_from');
            $table->string('refund_type', 50);
            $table->text('comment')->nullable();
            $table->boolean('send_cancel_mail')->default(false);
            $table->json('booking_ids');
            // Per booking: {id, status: queued|processing|cancelled|failed|not_processed, message}
            $table->json('results');
            // queued | processing | completed | stopped
            $table->string('status', 20)->default('queued');
            $table->text('message')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_by_guard', 50)->nullable();
            $table->string('created_by_user_type', 50)->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tour_package_id', 'date', 'time_from']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mass_cancellations');
    }
};
