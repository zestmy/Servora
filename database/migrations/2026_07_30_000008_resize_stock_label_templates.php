<?php

use App\Services\Labels\DefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Re-cut the stock templates for 70 × 40 mm label stock.
 *
 * The originals were seeded at 50 × 25, an explicit placeholder until the
 * physical roll was confirmed. It now is: 70 × 40.
 *
 * This ONLY touches templates that still carry the exact original layout.
 * Comparing the decoded field list rather than just name and size means a
 * template someone has edited keeps their work, even if they left the name
 * and dimensions alone. A false negative here is harmless — the company
 * keeps a 50 × 25 template and can resize it in the designer — whereas a
 * false positive silently destroys someone's layout.
 */
return new class extends Migration
{
    /** The 50 × 25 layout as originally seeded, for identity comparison. */
    private const ORIGINAL_FIELDS = [
        ['token' => 'item.name', 'x' => 2, 'y' => 1.5, 'w' => 46, 'h' => 5, 'font_size' => 9, 'weight' => 'bold', 'align' => 'left'],
        ['token' => 'label.caption', 'x' => 2, 'y' => 7, 'w' => 24, 'h' => 3.5, 'font_size' => 6, 'align' => 'left'],
        ['token' => 'date.end', 'x' => 2, 'y' => 10.5, 'w' => 46, 'h' => 5.5, 'font_size' => 11, 'weight' => 'bold', 'align' => 'left'],
        ['token' => 'date.start', 'x' => 2, 'y' => 16.5, 'w' => 30, 'h' => 3.5, 'font_size' => 6, 'align' => 'left'],
        ['token' => 'staff.name', 'x' => 2, 'y' => 20, 'w' => 24, 'h' => 3.5, 'font_size' => 6, 'align' => 'left'],
        ['token' => 'storage.instruction', 'x' => 26, 'y' => 20, 'w' => 22, 'h' => 3.5, 'font_size' => 6, 'align' => 'right'],
    ];

    public function up(): void
    {
        $newLayout = json_encode(['fields' => DefaultTemplates::FIELDS]);

        DB::table('label_templates')
            ->where('is_default', true)
            ->where('width_mm', 50)
            ->where('height_mm', 25)
            ->orderBy('id')
            ->each(function ($template) use ($newLayout) {
                if (! $this->isUntouched($template->layout)) {
                    return;
                }

                DB::table('label_templates')->where('id', $template->id)->update([
                    'width_mm'   => DefaultTemplates::WIDTH_MM,
                    'height_mm'  => DefaultTemplates::HEIGHT_MM,
                    'layout'     => $newLayout,
                    'updated_at' => now(),
                ]);
            });

        // Printers still recording the placeholder size are almost certainly
        // untouched defaults too — but a printer size is a statement about
        // physical stock loaded in a machine, so it is left alone. A wrong
        // size there clips every label, and that is the operator's call.
    }

    private function isUntouched(?string $layout): bool
    {
        $decoded = json_decode((string) $layout, true);

        return is_array($decoded)
            && ($decoded['fields'] ?? null) == self::ORIGINAL_FIELDS;
    }

    public function down(): void
    {
        $oldLayout = json_encode(['fields' => self::ORIGINAL_FIELDS]);
        $newFields = DefaultTemplates::FIELDS;

        DB::table('label_templates')
            ->where('is_default', true)
            ->where('width_mm', 70)
            ->where('height_mm', 40)
            ->orderBy('id')
            ->each(function ($template) use ($oldLayout, $newFields) {
                $decoded = json_decode((string) $template->layout, true);

                if (! is_array($decoded) || ($decoded['fields'] ?? null) != $newFields) {
                    return;
                }

                DB::table('label_templates')->where('id', $template->id)->update([
                    'width_mm'   => 50,
                    'height_mm'  => 25,
                    'layout'     => $oldLayout,
                    'updated_at' => now(),
                ]);
            });
    }
};
