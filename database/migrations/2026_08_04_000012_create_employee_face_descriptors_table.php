<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enrolled face templates — biometric data, and treated as such.
     *
     * What is stored is a descriptor: 128 floats produced by the recognition
     * model, not a photograph. A descriptor cannot be rendered back into a
     * face, which is the whole reason the matching was put on the device.
     * The reference photo is optional and exists only so a manager can see
     * WHO they enrolled; deleting it does not break matching.
     *
     * Several rows per employee on purpose. One head-on capture fails the
     * moment somebody grows a beard, wears a cap, or clocks in under a
     * different light; three or four captures at enrolment make the daily
     * punch work without loosening the threshold for everyone.
     *
     * model_version pins which network produced the vector. Descriptors from
     * different models are not comparable, so a future model swap has to
     * re-enrol rather than quietly compare across incompatible vectors.
     */
    public function up(): void
    {
        Schema::create('employee_face_descriptors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            // The 128 floats, JSON-encoded. Never indexed or queried on —
            // comparison is arithmetic done in PHP over the employee's own
            // handful of rows.
            $table->json('descriptor');

            $table->string('model_version', 32)->default('face-api-1.7');
            $table->string('photo_path')->nullable();

            $table->foreignId('enrolled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_face_descriptors');
    }
};
