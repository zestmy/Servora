<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Entitlements (docs/pricing-model.md).
 *
 * plans.modules: the modules a plan includes. NULL means a legacy plan
 * (Starter / Professional / Enterprise) that predates modules and grants
 * everything — nobody on an existing subscription loses a screen on deploy.
 *
 * subscription_addons: what a company bought on top of its suite. quantity is
 * employees for HR, kitchens for Central Kitchen, 1 for a flat add-on.
 * unit_price is what THIS company pays, not the list price.
 *
 * The Free / Basic / Full plans go in private (is_public = false): their
 * price is per outlet and checkout cannot charge that yet, so they must not
 * appear on /pricing until it can.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('modules')->nullable()->after('feature_flags');
        });

        Schema::create('subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('module', 40);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'module']);
        });

        $now = now();
        $plans = [
            ['free', 'Free', 'Recipe costing for one outlet.', 0, 0, 1, 2, 30, 150, 0,
                [], ['lms' => false, 'reports' => false, 'analytics' => false, 'ai_analysis' => false], 0],
            ['basic', 'Basic', 'Per outlet: costing, purchasing, inventory, sales and reports.', 180, 1800, null, null, null, null, null,
                ['basic'], ['lms' => false, 'reports' => true, 'analytics' => false, 'ai_analysis' => false], 10],
            ['full', 'Full', 'Per outlet: Basic plus Labels, Learn SOP, Audits, Assets, POS Sync and AI Insights.', 400, 4000, null, null, null, null, null,
                ['basic', 'labels', 'learn', 'audits', 'assets', 'pos_sync', 'ai_insights'],
                ['lms' => true, 'reports' => true, 'analytics' => true, 'ai_analysis' => true], 11],
        ];

        foreach ($plans as [$slug, $name, $desc, $m, $y, $outlets, $users, $recipes, $ingredients, $lms, $modules, $flags, $sort]) {
            if (DB::table('plans')->where('slug', $slug)->exists()) {
                continue;
            }

            DB::table('plans')->insert([
                'name' => $name, 'slug' => $slug, 'description' => $desc,
                'price_monthly' => $m, 'price_yearly' => $y, 'currency' => 'MYR',
                'max_outlets' => $outlets, 'max_users' => $users, 'max_recipes' => $recipes,
                'max_ingredients' => $ingredients, 'max_lms_users' => $lms,
                'feature_flags' => json_encode($flags), 'modules' => json_encode($modules),
                'is_active' => true, 'is_public' => false, 'sort_order' => $sort, 'trial_days' => 14,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('plans')->whereIn('slug', ['free', 'basic', 'full'])
            ->whereNotExists(fn ($q) => $q->from('subscriptions')->whereColumn('subscriptions.plan_id', 'plans.id'))
            ->delete();

        Schema::dropIfExists('subscription_addons');

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('modules');
        });
    }
};
