<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who to call if something happens at work, and the employee's photograph.
 *
 * The emergency contact is the one piece of employee data whose whole value is
 * being reachable in a hurry, so it is stored on the employee row rather than
 * as a related record — a manager looking it up at 2am should not depend on a
 * join, and nobody has ever needed a second one badly enough to justify the
 * table.
 *
 * The relationship is a MANAGED LIST (HrOption type `relationship`) like the
 * other particulars, so "Spouse" is spelt one way across the company.
 *
 * `photo_path` points at the PRIVATE disk. A staff photograph is personal data:
 * a public URL would put a company's faces one guessed path away from anyone on
 * the internet, which is the same reasoning that keeps clock-in selfies behind
 * ClockImageController.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('emergency_contact_name', 120)->nullable()->after('education_level');
            $table->string('emergency_contact_relationship', 60)->nullable()->after('emergency_contact_name');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_relationship');
            // The second number is what makes the first one useful — the one
            // person listed is often at work themselves when the call comes.
            $table->string('emergency_contact_phone_alt', 50)->nullable()->after('emergency_contact_phone');
            $table->string('emergency_contact_address', 255)->nullable()->after('emergency_contact_phone_alt');

            $table->string('photo_path', 255)->nullable()->after('emergency_contact_address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'emergency_contact_name', 'emergency_contact_relationship',
                'emergency_contact_phone', 'emergency_contact_phone_alt',
                'emergency_contact_address', 'photo_path',
            ]);
        });
    }
};
