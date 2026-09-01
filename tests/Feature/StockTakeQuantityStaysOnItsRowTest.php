<?php

namespace Tests\Feature;

use App\Livewire\Inventory\StockTakeForm;
use App\Models\Company;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A counted quantity belongs to the row it was typed into.
 *
 * Staff reported that keying a quantity put the number on a line below. It was
 * the tab order. Each row ends with a remove button in a cell styled
 * `opacity-0 group-hover:opacity-100` — invisible until the mouse is over the
 * row, and fully focusable the whole time. So tabbing down a count sheet went
 *
 *     actual qty (row N) -> invisible X (row N) -> SYSTEM qty (row N+1)
 *
 * and the next number typed landed in the row below, in the expected-stock
 * column, which is the one figure a stock take must not have typed into: it
 * silently rewrites the variance the count exists to measure.
 *
 * Taking the hidden control out of the tab order restores the rhythm the form
 * was built for — two tabs from one row's Actual Qty to the next row's.
 */
class StockTakeQuantityStaysOnItsRowTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;
    private UnitOfMeasure $kg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Row Co', 'slug' => Str::slug('Row Co') . '-' . uniqid(),
            'currency' => 'MYR', 'is_active' => true,
        ]);

        $outlet = Outlet::create([
            'company_id' => $this->company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id, 'outlet_id' => $outlet->id, 'can_view_all_outlets' => true,
        ]);
        $this->user->companies()->syncWithoutDetaching([$this->company->id]);
        $this->user->outlets()->syncWithoutDetaching([$outlet->id]);

        setPermissionsTeamId($this->company->id);
        $this->user->givePermissionTo([
            Permission::findOrCreate('inventory.view', 'web'),
            Permission::findOrCreate('inventory.stock_takes.record', 'web'),
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->kg = UnitOfMeasure::create(['name' => 'Kilogram', 'abbreviation' => 'kg', 'type' => 'weight']);

        $this->actingAs($this->user);
    }

    private function ingredient(string $name, ?int $categoryId = null): Ingredient
    {
        return Ingredient::create([
            'company_id' => $this->company->id, 'name' => $name,
            'base_uom_id' => $this->kg->id, 'recipe_uom_id' => $this->kg->id,
            'ingredient_category_id' => $categoryId,
            'current_cost' => 5.00, 'is_active' => true,
        ]);
    }

    /** A form holding three ingredients, split across two categories. */
    private function screenWithThreeRows()
    {
        $dairy   = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Dairy']);
        $produce = IngredientCategory::create(['company_id' => $this->company->id, 'name' => 'Produce']);

        // Groups render alphabetically, so screen order is not array order —
        // which is the shape the reported bug showed up in.
        $a = $this->ingredient('Apple',  $produce->id);
        $b = $this->ingredient('Butter', $dairy->id);
        $c = $this->ingredient('Carrot', $produce->id);

        return Livewire::actingAs($this->user)->test(StockTakeForm::class)
            ->call('addIngredient', $a->id)
            ->call('addIngredient', $b->id)
            ->call('addIngredient', $c->id);
    }

    /**
     * Every control a keyboard can land on, in document order. Anything parked
     * at tabindex="-1" is skipped, which is exactly what the browser does.
     */
    private function tabStops(string $html): array
    {
        preg_match_all('/<(?:input|button|select|textarea|a)\b[^>]*>/i', $html, $m);

        $stops = [];
        foreach ($m[0] as $tag) {
            if (str_contains($tag, 'tabindex="-1"')) {
                continue;
            }
            if (preg_match('/wire:model(?:\.[a-z0-9.]+)?="(lines\.\d+\.[a-z_]+)"/i', $tag, $bind)) {
                $stops[] = $bind[1];
                continue;
            }
            if (stripos($tag, '<button') === 0) {
                $stops[] = 'button';
            }
        }

        return $stops;
    }

    // ── The reported bug ─────────────────────────────────────────────────

    public function test_nothing_focusable_sits_between_one_rows_quantity_and_the_next(): void
    {
        $stops = $this->tabStops($this->screenWithThreeRows()->html());

        $actualAt = array_keys(array_filter($stops, fn ($s) => str_ends_with($s, '.actual_quantity')));
        $this->assertCount(3, $actualAt, 'Expected one Actual Qty field per row.');

        for ($i = 0; $i < count($actualAt) - 1; $i++) {
            $between = array_slice($stops, $actualAt[$i] + 1, $actualAt[$i + 1] - $actualAt[$i] - 1);

            foreach ($between as $stop) {
                $this->assertStringEndsWith(
                    '.system_quantity',
                    $stop,
                    "Tabbing off a count lands on '{$stop}' before reaching the next row. A hidden "
                    . 'control in the tab order is how a typed number ends up on the line below.'
                );
            }
        }
    }

    public function test_the_row_remove_button_is_not_a_hidden_tab_stop(): void
    {
        $html = $this->screenWithThreeRows()->html();

        // It is invisible until the row is hovered, so it must not be tabbable —
        // otherwise a keyboard user lands on an unseen control that deletes a line.
        preg_match_all('/<button[^>]*wire:click="removeLine\([^"]*\)"[^>]*>/i', $html, $m);

        $this->assertNotEmpty($m[0], 'The per-row remove button should still exist.');

        foreach ($m[0] as $button) {
            $this->assertStringContainsString('tabindex="-1"', $button);
            $this->assertStringContainsString('aria-label=', $button, 'An icon-only button needs a name.');
        }
    }

    public function test_a_quantity_is_committed_on_leaving_the_field(): void
    {
        $html = $this->screenWithThreeRows()->html();

        // A debounce timer keeps the value client-side while the table re-renders
        // around it, and fast counting loses entries. Commit on blur instead.
        $this->assertStringContainsString('wire:model.blur="lines.0.actual_quantity"', $html);
        $this->assertStringNotContainsString('debounce.400ms="lines.0.actual_quantity"', $html);
    }

    // ── Drag to reorder ──────────────────────────────────────────────────

    public function test_a_row_key_carries_the_position_its_inputs_are_bound_to(): void
    {
        // The inputs bind by array position (lines.N.actual_quantity), so a row
        // that survives a reorder keeps its element while its binding path moves
        // underneath it. Livewire then holds two paths for one input: typing 44
        // into one row went out as lines.1, lines.3 AND lines.5 at once, and
        // three counts were overwritten. Naming the position in the key means a
        // reordered row is rebuilt rather than rebound.
        $html = $this->screenWithThreeRows()->html();

        preg_match_all('/wire:key="st-line-([^"]+)"/', $html, $keys);
        preg_match_all('/wire:model\.blur="lines\.(\d+)\.actual_quantity"/', $html, $binds);

        $this->assertCount(3, $keys[1]);
        $this->assertSame($keys[1], array_values(array_unique($keys[1])), 'Row keys must be distinct.');

        foreach ($binds[1] as $i => $index) {
            $this->assertStringStartsWith(
                $index . '-',
                $keys[1][$i],
                "Row {$i} binds to lines.{$index} but its key does not name that position."
            );
        }
    }

    public function test_reordering_refuses_a_list_that_is_not_a_permutation(): void
    {
        // The order arrives from the browser. Counting it was not enough: a list
        // naming one row twice and another not at all is the same length, so it
        // passed — storing one count under two items and losing a third.
        $component = $this->screenWithThreeRows()
            ->set('lines.0.actual_quantity', '5')
            ->set('lines.1.actual_quantity', '6')
            ->set('lines.2.actual_quantity', '7');

        $before = collect($component->get('lines'))
            ->map(fn ($l) => $l['ingredient_name'] . '=' . $l['actual_quantity'])->all();

        $component->call('reorderLines', ['0', '0', '2']);   // 1 missing, 0 twice

        $after = collect($component->get('lines'))
            ->map(fn ($l) => $l['ingredient_name'] . '=' . $l['actual_quantity'])->all();

        $this->assertSame($before, $after, 'A malformed order must leave the lines alone.');
    }

    public function test_a_genuine_reorder_moves_each_row_with_its_own_count(): void
    {
        $component = $this->screenWithThreeRows()
            ->set('lines.0.actual_quantity', '5')
            ->set('lines.1.actual_quantity', '6')
            ->set('lines.2.actual_quantity', '7');

        $component->call('reorderLines', ['2', '0', '1']);

        $this->assertSame(
            ['CARROT=7', 'APPLE=5', 'BUTTER=6'],
            collect($component->get('lines'))
                ->map(fn ($l) => $l['ingredient_name'] . '=' . floatval($l['actual_quantity']))->all()
        );
    }

    // ── The server half ──────────────────────────────────────────────────

    public function test_typing_a_quantity_leaves_the_other_rows_alone(): void
    {
        $lines = $this->screenWithThreeRows()
            ->set('lines.1.actual_quantity', '7')
            ->get('lines');

        $this->assertSame(7.0, (float) $lines[1]['actual_quantity'], 'The row typed into must take the value.');
        $this->assertSame(0.0, (float) $lines[0]['actual_quantity'], 'The row above must not follow.');
        $this->assertSame(0.0, (float) $lines[2]['actual_quantity'], 'The row below must not follow.');
    }

    public function test_a_counted_quantity_never_writes_the_expected_quantity(): void
    {
        $lines = $this->screenWithThreeRows()
            ->set('lines.2.actual_quantity', '4')
            ->get('lines');

        foreach ($lines as $line) {
            $this->assertSame(
                0.0,
                (float) $line['system_quantity'],
                'Counting must not move system qty — that is the figure the variance is measured against.'
            );
        }
    }

    public function test_every_row_carries_a_distinct_wire_key(): void
    {
        preg_match_all('/wire:key="st-line-([^"]+)"/', $this->screenWithThreeRows()->html(), $m);

        $this->assertCount(3, $m[1], 'Every line needs its own keyed row.');
        $this->assertSame(
            $m[1],
            array_values(array_unique($m[1])),
            'Duplicate wire:key values let the DOM reuse one row for another.'
        );
    }
}
