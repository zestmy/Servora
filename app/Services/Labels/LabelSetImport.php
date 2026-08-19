<?php

namespace App\Services\Labels;

use App\Models\LabelSet;
use App\Models\LabelSetLine;
use App\Scopes\CompanyScope;

/**
 * Copies a print set from one outlet into another.
 *
 * Sets are outlet-owned on purpose — but the outlets of one company mostly
 * prep the same food, so "build Chiller 1 again from scratch at the new
 * branch" is transcription, and transcription is where items go missing.
 * Same reasoning as the Form Template import, one outlet further along.
 *
 * Called from two places with DIFFERENT auth: the manager screen (a signed-in
 * web user, CompanyScope live) and the staff PWA (a PIN session, no user, so
 * CompanyScope resolves to nothing — or to the subdomain company, depending
 * on environment). Every query in here therefore drops the scope and matches
 * company_id by hand; the CALLER is responsible for having resolved $source
 * inside the right company, and the target outlet is checked against the
 * source's company here as a last line.
 */
class LabelSetImport
{
    /**
     * Clone $source into $outletId as a new set, lines and storage settings
     * included. Returns [set, added, missing].
     *
     * @return array{0: LabelSet, 1: int, 2: int}
     */
    public function copyToOutlet(LabelSet $source, int $outletId, ?string $name = null, ?int $createdBy = null): array
    {
        $set = LabelSet::create([
            'company_id'     => $source->company_id,
            'outlet_id'      => $outletId,
            'name'           => $name !== null && trim($name) !== '' ? $name : $source->name,
            'description'    => $source->description,
            'created_by'     => $createdBy,
            'show_storage'   => (bool) $source->show_storage,
            'storage_states' => $source->storage_states,
        ]);

        [$added, , $missing] = $this->mergeLines($source, $set);

        return [$set, $added, $missing];
    }

    /**
     * Append $source's lines to $target, skipping what is already there so a
     * second import tops the set up rather than doubling it. Linked items
     * dedupe on what they point at; freeform items on their normalised name.
     *
     * Order carries over — it is physical, the order labels peel off the roll
     * — with imported lines slotted after whatever the target already holds.
     *
     * QUANTITIES AND UNITS DO CARRY, unlike the Form Template import: both
     * sides here are print sets, so the number means the same thing in both
     * ("how much is in the container being labelled") and comes with its unit
     * attached.
     *
     * @return array{0: int, 1: int, 2: int} [added, skipped, missing]
     */
    public function mergeLines(LabelSet $source, LabelSet $target): array
    {
        $existing = $target->lines()
            ->get()
            ->map(fn ($l) => $this->lineKey($l))
            ->filter()
            ->all();

        $next = (int) $target->lines()->max('sort_order') + 1;

        $added = 0;
        $skipped = 0;
        $missing = 0;

        foreach ($source->lines()->get() as $line) {
            // A line whose item has since been deleted would print a blank
            // label at the new outlet. The source set is equally broken, but
            // copying the breakage helps nobody — skip it and say so.
            if ($line->labelable_type && ! $this->itemExists($line)) {
                $missing++;
                continue;
            }

            $key = $this->lineKey($line);

            if ($key !== null && in_array($key, $existing, true)) {
                $skipped++;
                continue;
            }

            LabelSetLine::create([
                'label_set_id'     => $target->id,
                'sort_order'       => $next++,
                'labelable_type'   => $line->labelable_type,
                'labelable_id'     => $line->labelable_id,
                'custom_name'      => $line->custom_name,
                'label_type'       => $line->label_type,
                'storage_state'    => $line->storage_state,
                // The line's own shelf life carries: it is part of what the
                // line MEANS, and freeform items have nowhere else to keep it.
                'shelf_life_value' => $line->shelf_life_value,
                'shelf_life_unit'  => $line->shelf_life_unit,
                'copies'           => $line->copies,
                'quantity'         => $line->quantity,
                'uom_id'           => $line->uom_id,
                'template_id'      => $line->template_id,
            ]);

            if ($key !== null) {
                $existing[] = $key;
            }

            $added++;
        }

        return [$added, $skipped, $missing];
    }

    /** One sentence covering every line, including the ones that did nothing. */
    public function summary(LabelSet $source, LabelSet $target, int $added, int $skipped, int $missing): string
    {
        if ($added === 0 && $skipped === 0 && $missing === 0) {
            return '“' . $source->name . '” has no items to import.';
        }

        $from = '“' . $source->name . '”'
            . ($source->outlet?->name ? ' at ' . $source->outlet->name : '');

        $parts = [sprintf('%d item%s added to “%s” from %s', $added, $added === 1 ? '' : 's', $target->name, $from)];

        if ($skipped) {
            $parts[] = sprintf('%d already there', $skipped);
        }

        if ($missing) {
            $parts[] = sprintf('%d skipped — the item no longer exists', $missing);
        }

        return implode('. ', $parts) . '.';
    }

    /**
     * What makes a line "the same item" as another. Null for a freeform line
     * whose name normalises to nothing — those can't be meaningfully deduped.
     */
    private function lineKey(LabelSetLine $line): ?string
    {
        if ($line->labelable_type && $line->labelable_id) {
            return $line->labelable_type . ':' . $line->labelable_id;
        }

        $name = LabelName::normalise((string) $line->custom_name);

        return $name === '' ? null : 'custom:' . $name;
    }

    private function itemExists(LabelSetLine $line): bool
    {
        if (! $line->labelable_id) {
            return false;
        }

        // The morph class, minus CompanyScope — see the class note. Soft
        // deletes stay in force: a deleted ingredient is exactly the case
        // this check exists for.
        return $line->labelable_type::query()
            ->withoutGlobalScope(CompanyScope::class)
            ->whereKey($line->labelable_id)
            ->exists();
    }
}
