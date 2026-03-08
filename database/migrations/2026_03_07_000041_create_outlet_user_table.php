<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outlet_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'outlet_id']);
        });

        // Migrate existing user.outlet_id data into pivot
        $users = DB::table('users')->whereNotNull('outlet_id')->get();
        foreach ($users as $user) {
            DB::table('outlet_user')->insert([
                'user_id'    => $user->id,
                'outlet_id'  => $user->outlet_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('outlet_user');
    }
};
