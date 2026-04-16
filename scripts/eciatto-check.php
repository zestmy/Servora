<?php

// Run via: php artisan tinker < scripts/eciatto-check.php

$s = \App\Models\Supplier::withoutGlobalScopes()->where('name', 'like', '%ECIATTO%')->first();
if (! $s) {
    echo "Supplier not found\n";
    exit;
}
echo "Supplier: {$s->name} (ID: {$s->id})\n\n";

$ings = \App\Models\Ingredient::withoutGlobalScopes()
    ->whereHas('suppliers', fn($q) => $q->where('suppliers.id', $s->id))
    ->with(['baseUom', 'recipeUom', 'uomConversions.fromUom', 'uomConversions.toUom'])
    ->orderBy('name')
    ->get();

foreach ($ings as $ing) {
    $convs = $ing->uomConversions->map(fn($c) => $c->fromUom->abbreviation . '->' . $c->toUom->abbreviation . ' x' . $c->factor)->join(', ');
    echo sprintf("%-4s | %-45s | base=%-5s recipe=%-5s pack=%-8s | convs: %s\n",
        $ing->id,
        $ing->name,
        $ing->baseUom->abbreviation ?? '?',
        $ing->recipeUom->abbreviation ?? '?',
        $ing->pack_size,
        $convs ?: 'NONE'
    );
}
echo "\nTotal: " . $ings->count() . "\n";
