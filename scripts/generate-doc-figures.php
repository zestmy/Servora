<?php

/**
 * Draws the help centre's reference figures.
 *
 *   php scripts/generate-doc-figures.php
 *
 * SVG rather than screenshots, and generated rather than drawn by hand, for
 * three reasons that all bit at once:
 *
 *   1. A screenshot of a real screen needs real data behind it. A demo tenant
 *      with one recipe in it photographs as an empty state, which teaches the
 *      reader nothing about the screen.
 *   2. A screenshot ages the day the UI moves and nobody notices until a
 *      customer does. These are 40 lines of PHP each — a figure is re-rendered,
 *      not re-taken.
 *   3. They are ~6 KB apiece and stay sharp on a phone, a laptop and in print.
 *
 * They are DIAGRAMS OF THE UI, not pixel copies of it: the palette, the
 * shapes and the proportions come from the real design tokens
 * (tailwind.config.js), and the labels are the real ones from NavMenu, so
 * what the reader recognises is the structure. Admins can replace any of them
 * with a real screenshot from /admin/docs at any time — the article body just
 * points at a URL.
 */

const OUT = __DIR__ . '/../public/images/docs';

// ── Palette — mirrors tailwind.config.js. ──────────────────────────────────
const C = [
    'brand50'  => '#eefbf9', 'brand100' => '#d5f5f1', 'brand200' => '#aeeae4',
    'brand400' => '#43bdb8', 'brand500' => '#22a19d', 'brand600' => '#0b7677',
    'brand700' => '#0d5f61',
    'ink'      => '#111827', 'ink700' => '#374151', 'ink600' => '#4b5563',
    'ink500'   => '#6b7280', 'ink400'  => '#9ca3af', 'ink300' => '#d1d5db',
    'ink200'   => '#e5e7eb', 'ink100'  => '#f3f4f6', 'ink50'  => '#f9fafb',
    'white'    => '#ffffff',
    'success'  => '#059669', 'success50' => '#ecfdf5',
    'warning'  => '#d97706', 'warning50' => '#fffbeb',
    'danger'   => '#dc2626', 'danger50'  => '#fef2f2',
    'info'     => '#0284c7', 'info50'    => '#f0f9ff',
    'kitchen'  => '#7c3aed', 'kitchen50' => '#f5f3ff',
];

function c(string $key): string
{
    return C[$key];
}

function esc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/** A text run. Figtree with a system fallback — the SVG is used outside the app too. */
function t(float $x, float $y, string $text, string $fill = 'ink700', float $size = 12, string $weight = '400', string $anchor = 'start'): string
{
    return sprintf(
        '<text x="%s" y="%s" font-family="Figtree, ui-sans-serif, system-ui, sans-serif" font-size="%s" font-weight="%s" fill="%s" text-anchor="%s">%s</text>',
        $x, $y, $size, $weight, c($fill), $anchor, esc($text)
    );
}

function rect(float $x, float $y, float $w, float $h, string $fill, float $r = 0, ?string $stroke = null): string
{
    $s = $stroke ? sprintf(' stroke="%s" stroke-width="1"', c($stroke)) : '';

    return sprintf('<rect x="%s" y="%s" width="%s" height="%s" rx="%s" fill="%s"%s/>', $x, $y, $w, $h, $r, c($fill), $s);
}

function line(float $x1, float $y1, float $x2, float $y2, string $stroke = 'ink200', float $w = 1): string
{
    return sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s"/>', $x1, $y1, $x2, $y2, c($stroke), $w);
}

/** A pill badge: rounded fill plus centred label. */
function badge(float $x, float $y, string $label, string $fill, string $text): string
{
    $w = max(38, strlen($label) * 6.2 + 16);

    return rect($x, $y, $w, 18, $fill, 9) . t($x + $w / 2, $y + 12.5, $label, $text, 9.5, '600', 'middle');
}

/** A filled primary button. */
function button(float $x, float $y, string $label, string $fill = 'brand600', string $text = 'white'): string
{
    $w = strlen($label) * 6.6 + 24;

    return rect($x, $y, $w, 26, $fill, 7) . t($x + $w / 2, $y + 17, $label, $text, 11, '600', 'middle');
}

/**
 * The app frame every screen figure sits in: window chrome, dark sidebar with
 * the real nav labels, and an empty content area for the caller to fill.
 *
 * @param  array<int, string>  $nav      sidebar labels
 * @param  int                 $active   index of the highlighted one
 */
function frame(string $title, string $eyebrow, array $nav, int $active, string $body, string $workspace = 'outlet', string $action = ''): string
{
    $W = 1180;
    $H = 720;
    $sidebar = 210;
    $accent  = $workspace === 'kitchen' ? 'kitchen' : 'brand400';

    $svg  = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" role="img">', $W, $H, $W, $H);
    $svg .= '<title>' . esc($title) . ' — Servora</title>';

    // Window
    $svg .= rect(0, 0, $W, $H, 'ink200', 14);
    $svg .= rect(1, 1, $W - 2, $H - 2, 'ink50', 13);
    $svg .= rect(1, 1, $W - 2, 34, 'white', 13);
    $svg .= rect(1, 24, $W - 2, 11, 'white');
    $svg .= line(1, 35, $W - 1, 35, 'ink200');
    foreach ([['#f87171', 20], ['#fbbf24', 38], ['#34d399', 56]] as [$dot, $cx]) {
        $svg .= sprintf('<circle cx="%d" cy="18" r="4.5" fill="%s"/>', $cx, $dot);
    }
    $svg .= rect(84, 9, 300, 18, 'ink100', 9);
    $svg .= t(94, 22, 'app.servora.com.my', 'ink500', 10);

    // Sidebar
    $svg .= rect(1, 35, $sidebar, $H - 36, 'ink');
    $svg .= rect(1, 35, 13, $H - 36, 'ink');
    $svg .= t(20, 66, 'Servora', 'white', 15, '700');
    if ($workspace === 'kitchen') {
        $svg .= badge(90, 53, 'KITCHEN', 'kitchen', 'white');
    }
    $svg .= line(14, 82, $sidebar - 8, 82, 'ink700');

    $y = 104;
    foreach ($nav as $i => $label) {
        if (str_starts_with($label, '#')) {
            $svg .= t(20, $y + 4, strtoupper(substr($label, 1)), 'ink500', 8.5, '700');
            $y += 22;

            continue;
        }

        if ($i === $active) {
            $svg .= rect(10, $y - 12, $sidebar - 20, 30, 'ink700', 7);
            $svg .= rect(10, $y - 12, 3, 30, $accent, 2);
        }
        $svg .= rect(22, $y - 5, 12, 12, $i === $active ? $accent : 'ink500', 3);
        $svg .= t(44, $y + 5, $label, $i === $active ? 'white' : 'ink300', 11.5, $i === $active ? '600' : '400');
        $y += 32;
    }

    // Page header
    $cx = $sidebar + 25;
    $svg .= t($cx, 74, strtoupper($eyebrow), 'ink500', 9, '700');
    $svg .= t($cx, 98, $title, 'ink', 21, '700');
    if ($action !== '') {
        $svg .= button($W - 30 - (strlen($action) * 6.6 + 24), 78, $action);
    }

    $svg .= $body;
    $svg .= '</svg>';

    return $svg;
}

/** A stat card. */
function statCard(float $x, float $y, float $w, string $label, string $value, string $meta, string $valueFill = 'ink'): string
{
    return rect($x, $y, $w, 84, 'white', 12, 'ink200')
        . t($x + 16, $y + 26, strtoupper($label), 'ink500', 8.5, '700')
        . t($x + 16, $y + 54, $value, $valueFill, 22, '700')
        . t($x + 16, $y + 71, $meta, 'ink500', 9.5);
}

/**
 * A table card.
 *
 * @param  array<int, array{0: string, 1: float}>  $columns  label and x offset
 * @param  array<int, array<int, string>>          $rows
 * @param  array<int, array{0: string, 1: string}> $badges   row index => [label, tone]
 */
function table(float $x, float $y, float $w, array $columns, array $rows, array $badges = [], float $rowH = 34): string
{
    $h = 42 + count($rows) * $rowH;
    $svg = rect($x, $y, $w, $h, 'white', 12, 'ink200');
    $svg .= rect($x, $y, $w, 34, 'ink50', 12);
    $svg .= rect($x, $y + 22, $w, 12, 'ink50');
    $svg .= line($x, $y + 34, $x + $w, $y + 34, 'ink200');

    foreach ($columns as [$label, $dx]) {
        $svg .= t($x + $dx, $y + 22, strtoupper($label), 'ink500', 8.5, '700');
    }

    $ry = $y + 34;
    foreach ($rows as $i => $row) {
        if ($i > 0) {
            $svg .= line($x + 1, $ry, $x + $w - 1, $ry, 'ink100');
        }
        foreach ($row as $j => $cell) {
            if ($cell === '') {
                continue;
            }
            $dx = $columns[$j][1] ?? 16;
            $bold = $j === 0 ? '600' : '400';
            $svg .= t($x + $dx, $ry + 21, $cell, $j === 0 ? 'ink' : 'ink600', 11, $bold);
        }
        if (isset($badges[$i])) {
            [$label, $tone] = $badges[$i];
            $svg .= badge($x + $w - 100, $ry + 8, $label, $tone . '50', $tone);
        }
        $ry += $rowH;
    }

    return $svg;
}

/** A card with a heading and free content. */
function card(float $x, float $y, float $w, float $h, string $heading, string $inner = ''): string
{
    return rect($x, $y, $w, $h, 'white', 12, 'ink200')
        . t($x + 16, $y + 26, $heading, 'ink', 12.5, '700')
        . $inner;
}

/** A horizontal progress meter. */
function meter(float $x, float $y, float $w, float $pct, string $fill = 'brand500'): string
{
    return rect($x, $y, $w, 8, 'ink100', 4)
        . rect($x, $y, max(4, $w * $pct / 100), 8, $fill, 4);
}

/** A flow-diagram node. */
function node(float $x, float $y, float $w, string $title, string $sub, string $tone = 'brand'): string
{
    $fill   = $tone === 'brand' ? 'brand50' : ($tone . '50');
    $stroke = $tone === 'brand' ? 'brand200' : 'ink200';

    return rect($x, $y, $w, 62, $fill, 10, $stroke)
        . t($x + $w / 2, $y + 27, $title, 'ink', 12, '700', 'middle')
        . t($x + $w / 2, $y + 45, $sub, 'ink600', 9.5, '400', 'middle');
}

function arrow(float $x1, float $y, float $x2, string $stroke = 'ink400'): string
{
    return sprintf(
        '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="1.5"/>'
        . '<path d="M%s %s l-6 -4 v8 z" fill="%s"/>',
        $x1, $y, $x2 - 6, $y, c($stroke), $x2, $y, c($stroke)
    );
}

function write(string $name, string $svg): void
{
    if (! is_dir(OUT)) {
        mkdir(OUT, 0755, true);
    }

    file_put_contents(OUT . "/{$name}.svg", $svg . "\n");
    printf("  %-34s %6.1f KB\n", "{$name}.svg", strlen($svg) / 1024);
}

// The outlet workspace sidebar, verbatim from NavMenu::outlet().
$outletNav = ['Dashboard', 'Procurement', 'Inventory & Recipes', 'Labels', 'Sales', 'HR', 'Learning', 'Business Intelligence', 'Settings'];

echo "Drawing help centre figures into public/images/docs\n";

// ── 1. Dashboard ───────────────────────────────────────────────────────────
$body  = statCard(235, 120, 222, 'Food cost %', '31.4%', 'Target 32.0% — on track', 'success');
$body .= statCard(472, 120, 222, 'Sales, month to date', 'RM 184,320', '+8.2% vs last month');
$body .= statCard(709, 120, 222, 'Purchases', 'RM 57,940', '14 orders, 3 awaiting approval');
$body .= statCard(946, 120, 204, 'Stock value', 'RM 22,180', 'Counted 2 days ago');

$body .= card(235, 226, 560, 210, 'Needs your attention');
$alerts = [
    ['Chicken thigh is 18% above its 90-day average', 'warning', 'Price'],
    ['PO-2026-0184 has been awaiting approval for 3 days', 'danger', 'Approval'],
    ['6 labelled items expire before service tomorrow', 'warning', 'Labels'],
    ['Stock take for Main Kitchen is 11 days overdue', 'info', 'Inventory'],
];
$ay = 262;
foreach ($alerts as [$text, $tone, $tag]) {
    $body .= rect(251, $ay, 528, 36, $tone . '50', 8);
    $body .= rect(251, $ay, 3, 36, $tone, 2);
    $body .= t(266, $ay + 22, $text, 'ink700', 11);
    $body .= badge(700, $ay + 9, $tag, 'white', $tone);
    $ay += 42;
}

$body .= card(811, 226, 339, 210, 'Today');
$today = [['Deliveries expected', '4'], ['Production orders open', '7'], ['Staff clocked in', '12 of 15'], ['Labels printed', '86']];
$ty = 268;
foreach ($today as [$label, $value]) {
    $body .= t(827, $ty, $label, 'ink600', 11);
    $body .= t(1134, $ty, $value, 'ink', 11.5, '700', 'end');
    $body .= line(827, $ty + 12, 1134, $ty + 12, 'ink100');
    $ty += 36;
}

$body .= t(235, 470, 'Recent activity', 'ink', 13, '700');
$body .= table(235, 484, 915, [
    ['Document', 16], ['Outlet', 300], ['Value', 470], ['Raised by', 590], ['Status', 780],
], [
    ['GRN-2026-0421', 'Bangsar', 'RM 3,410.50', 'Faiz (Chef)', ''],
    ['PO-2026-0184',  'Bangsar', 'RM 8,220.00', 'Nurul (Manager)', ''],
    ['INV-S-002911',  'Damansara', 'RM 1,845.90', 'AI invoice scan', ''],
    ['STO-2026-0067', 'Central Kitchen', 'RM 960.00', 'Hakim (CK)', ''],
], [
    0 => ['Received', 'success'],
    1 => ['Pending', 'warning'],
    2 => ['Matched', 'info'],
    3 => ['In transit', 'info'],
]);

write('dashboard-overview', frame('Dashboard', 'Operations', $outletNav, 0, $body));

// ── 2. Navigation map ──────────────────────────────────────────────────────
$groups = [
    ['Procurement', 'cart', ['Orders & Requests', 'Suppliers', 'Product Mapping', 'Form Templates', 'Price Alerts']],
    ['Inventory & Recipes', 'cube', ['Market List', 'Recipes', 'Prep Items', 'Stock Management', 'Par Levels']],
    ['Labels', 'tag', ['Print Labels', 'Print Sets', 'Expiring', 'Print Log', 'Shelf Life', 'Templates']],
    ['Sales', 'chart', ['Sales Records', 'POS Sync', 'Sales Categories', 'Sales Targets']],
    ['HR', 'users', ['Employees', 'Duty Roster', 'Attendance', 'Leave', 'Payroll', 'EA Forms']],
    ['Learning', 'academic', ['Courses', 'Quizzes', 'Learning Paths', 'Live Sessions', 'Certificates']],
    ['Business Intelligence', 'trending', ['Reports', 'AI Analysis', 'Audit Logs', 'Calendar Events']],
    ['Settings & Billing', 'cog', ['All Settings', 'Billing', 'Refer & Earn', 'Help & Guides']],
];

$W = 1180;
$svg  = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d 640" width="%d" height="640" role="img">', $W, $W);
$svg .= '<title>What is in the Servora sidebar</title>';
$svg .= rect(0, 0, $W, 640, 'ink50', 14);
$svg .= t(40, 52, 'Everything in Servora, by sidebar group', 'ink', 20, '700');
$svg .= t(40, 76, 'You only see a group if your role can open something inside it.', 'ink600', 12);

$gx = 40;
$gy = 104;
foreach ($groups as $i => [$title, $icon, $items]) {
    $col = $i % 4;
    $row = intdiv($i, 4);
    $x   = $gx + $col * 278;
    $y   = $gy + $row * 258;

    $svg .= rect($x, $y, 258, 236, 'white', 12, 'ink200');
    $svg .= rect($x + 16, $y + 18, 26, 26, 'brand50', 7);
    $svg .= rect($x + 24, $y + 26, 10, 10, 'brand600', 2);
    $svg .= t($x + 52, $y + 36, $title, 'ink', 12.5, '700');
    $svg .= line($x + 16, $y + 56, $x + 242, $y + 56, 'ink100');

    $iy = $y + 78;
    foreach ($items as $item) {
        $svg .= sprintf('<circle cx="%d" cy="%d" r="2.5" fill="%s"/>', $x + 22, $iy - 4, c('brand400'));
        $svg .= t($x + 34, $iy, $item, 'ink700', 11);
        $iy += 26;
    }
}
$svg .= '</svg>';
write('navigation-map', $svg);

// ── 3. Purchasing flow ─────────────────────────────────────────────────────
$svg  = sprintf('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d 400" width="%d" height="400" role="img">', $W, $W);
$svg .= '<title>How a purchase moves through Servora</title>';
$svg .= rect(0, 0, $W, 400, 'white', 14);
$svg .= rect(0, 0, $W, 400, 'white', 14, 'ink200');
$svg .= t(40, 48, 'How one purchase moves through Servora', 'ink', 19, '700');
$svg .= t(40, 72, 'Each step creates a document. Nothing is retyped — every step is filled from the one before it.', 'ink600', 12);

$steps = [
    ['Purchase Request', 'What the outlet needs'],
    ['Purchase Order', 'What the supplier is told'],
    ['Delivery Order', 'What is on the van'],
    ['Goods Received', 'What actually arrived'],
    ['Invoice', 'What you are charged'],
];
$x = 40;
foreach ($steps as $i => [$title, $sub]) {
    $svg .= node($x, 118, 190, $title, $sub);
    $svg .= sprintf('<circle cx="%d" cy="%d" r="11" fill="%s"/>', $x + 16, 118, c('brand600'));
    $svg .= t($x + 16, 122, (string) ($i + 1), 'white', 11, '700', 'middle');
    if ($i < count($steps) - 1) {
        $svg .= arrow($x + 194, 149, $x + 218);
    }
    $x += 222;
}

$notes = [
    ['Approval', 'A PR over the approval threshold waits for a PO Approver before it can become an order.', 'warning'],
    ['Short delivery', 'Receive what came. Servora keeps the shortfall against the order rather than quietly closing it.', 'info'],
    ['Price change', 'A unit price above the alert threshold raises a price alert and updates the ingredient cost.', 'danger'],
];
$nx = 40;
foreach ($notes as [$title, $text, $tone]) {
    $svg .= rect($nx, 226, 360, 130, $tone . '50', 10);
    $svg .= rect($nx, 226, 3, 130, $tone, 2);
    $svg .= t($nx + 18, 254, $title, 'ink', 12, '700');
    // Naive wrap at ~44 characters — enough for these fixed strings.
    $wy = 278;
    foreach (explode("\n", wordwrap($text, 44, "\n", true)) as $ln) {
        $svg .= t($nx + 18, $wy, $ln, 'ink700', 11);
        $wy += 18;
    }
    $nx += 380;
}
$svg .= '</svg>';
write('purchasing-flow', $svg);

// ── 4. Market list ─────────────────────────────────────────────────────────
$body  = rect(235, 120, 915, 52, 'white', 10, 'ink200');
$body .= rect(251, 133, 240, 26, 'ink50', 7, 'ink200');
$body .= t(263, 150, 'Search ingredients…', 'ink500', 10.5);
foreach ([['All categories', 505], ['All suppliers', 640], ['In stock', 775]] as [$label, $fx]) {
    $body .= rect($fx, 133, 122, 26, 'white', 7, 'ink300');
    $body .= t($fx + 12, 150, $label, 'ink700', 10.5);
}
$body .= badge(920, 137, '312 ITEMS', 'brand50', 'brand700');

$body .= table(235, 186, 915, [
    ['Ingredient', 16], ['Category', 250], ['Pack', 400], ['Cost / base', 530], ['Supplier', 680], ['Trend', 830],
], [
    ['Chicken thigh, boneless', 'Poultry',  '10 kg carton', 'RM 14.80 / kg', 'Ayam Segar',   '+18%'],
    ['Cooking oil, palm',       'Dry goods', '17 kg tin',    'RM  6.10 / kg', 'Sri Makmur',   '+2%'],
    ['Yellow onion',            'Produce',   '20 kg sack',   'RM  3.95 / kg', 'Pasar Borong', '-4%'],
    ['Full cream milk',         'Dairy',     '12 × 1 L',     'RM  5.20 / L',  'Dutch Lady',   '0%'],
    ['Basmati rice',            'Dry goods', '20 kg sack',   'RM  7.45 / kg', 'Sri Makmur',   '+1%'],
    ['Prawn 31/40',             'Seafood',   '1 kg block',   'RM 38.00 / kg', 'Ocean Fresh',  '+9%'],
], [], 38);

$body .= card(235, 470, 915, 200, 'Where the cost per base unit comes from');
$formula = [
    ['Purchase price', 'RM 148.00', 'What the supplier charges for the pack'],
    ['÷ Pack size', '10 kg', 'How much is in it'],
    ['÷ Yield %', '92%', 'What survives trimming, thawing and peeling'],
    ['= Cost per kg', 'RM 16.09', 'The number every recipe is costed on'],
];
$fy = 512;
foreach ($formula as $i => [$label, $value, $why]) {
    $fill = $i === 3 ? 'brand50' : 'ink50';
    $body .= rect(251, $fy, 883, 34, $fill, 7);
    $body .= t(267, $fy + 22, $label, 'ink', 11.5, $i === 3 ? '700' : '600');
    $body .= t(420, $fy + 22, $value, $i === 3 ? 'brand700' : 'ink700', 11.5, '700');
    $body .= t(540, $fy + 22, $why, 'ink600', 11);
    $fy += 38;
}

write('market-list', frame('Market List', 'Inventory & Recipes', $outletNav, 2, $body, 'outlet', '+ Ingredient'));

// ── 5. Recipe costing ──────────────────────────────────────────────────────
$body  = card(235, 120, 560, 330, 'Nasi Lemak Ayam Berempah — 1 portion');
$lines = [
    ['Basmati rice',        '180 g', 'RM 1.34'],
    ['Coconut milk',        '60 ml', 'RM 0.72'],
    ['Chicken thigh',       '140 g', 'RM 2.25'],
    ['Rempah paste (prep)', '35 g',  'RM 0.88'],
    ['Sambal (prep)',       '40 g',  'RM 0.64'],
    ['Anchovies & peanuts', '25 g',  'RM 0.95'],
    ['Cucumber, egg',       '1 set', 'RM 0.61'],
];
$ly = 164;
foreach ($lines as $i => [$name, $qty, $cost]) {
    if ($i > 0) {
        $body .= line(251, $ly - 12, 779, $ly - 12, 'ink100');
    }
    $body .= t(251, $ly + 4, $name, 'ink700', 11.5);
    $body .= t(600, $ly + 4, $qty, 'ink600', 11.5);
    $body .= t(779, $ly + 4, $cost, 'ink', 11.5, '600', 'end');
    $ly += 30;
}
$body .= rect(251, 386, 528, 46, 'brand50', 8);
$body .= t(267, 407, 'Total food cost', 'ink', 12, '700');
$body .= t(267, 423, 'Sum of every line, at today\'s ingredient costs', 'ink600', 9.5);
$body .= t(763, 415, 'RM 7.39', 'brand700', 17, '700', 'end');

$body .= card(811, 120, 339, 330, 'Selling price');
$body .= t(827, 178, 'Menu price (dine-in)', 'ink600', 11);
$body .= t(1134, 178, 'RM 22.90', 'ink', 13, '700', 'end');
$body .= t(827, 208, 'Food cost %', 'ink600', 11);
$body .= t(1134, 208, '32.3%', 'ink', 13, '700', 'end');
$body .= meter(827, 220, 307, 32.3);
$body .= t(827, 248, 'Target 32.0%', 'ink500', 9.5);
$body .= t(1134, 248, 'Over by 0.3pt', 'warning', 9.5, '600', 'end');

$body .= line(827, 274, 1134, 274, 'ink200');
$body .= t(827, 302, 'Gross margin', 'ink600', 11);
$body .= t(1134, 302, 'RM 15.51', 'success', 13, '700', 'end');
$body .= t(827, 332, 'Price class: Delivery (+18%)', 'ink600', 11);
$body .= t(1134, 332, 'RM 27.02', 'ink', 13, '700', 'end');
$body .= rect(827, 352, 307, 78, 'info50', 8);
$body .= t(843, 376, 'Prep items carry their own cost', 'ink', 11, '700');
$body .= t(843, 396, 'Rempah and sambal are recipes too. Change', 'ink700', 10.5);
$body .= t(843, 412, 'one and every dish using it re-costs.', 'ink700', 10.5);

$body .= t(235, 486, 'What moves this number', 'ink', 13, '700');
$body .= table(235, 500, 915, [
    ['Change', 16], ['Effect on this recipe', 340], ['Where it comes from', 640],
], [
    ['Chicken thigh +18%',   'Food cost +RM 0.40 / portion', 'Purchase invoice, 3 days ago'],
    ['Yield 92% → 88%',      'Food cost +RM 0.10 / portion', 'Market List, yield %'],
    ['Sambal batch re-costed', 'Food cost -RM 0.06 / portion', 'Prep Items'],
], [], 34);

write('recipe-costing', frame('Recipe costing', 'Inventory & Recipes', $outletNav, 2, $body, 'outlet', 'Export SOP'));

// ── 6. Labels ──────────────────────────────────────────────────────────────
$body  = card(235, 120, 560, 300, 'Print queue');
$queue = [
    ['Sambal, prepared', '3 days', '4 labels'],
    ['Chicken marinade', '48 hours', '2 labels'],
    ['Cut fruit platter', '12 hours', '6 labels'],
    ['Stock, chicken',   '5 days', '2 labels'],
];
$qy = 160;
foreach ($queue as $i => [$name, $life, $count]) {
    $body .= rect(251, $qy, 528, 42, $i === 0 ? 'brand50' : 'white', 8, $i === 0 ? 'brand200' : 'ink200');
    $body .= t(267, $qy + 19, $name, 'ink', 11.5, '600');
    $body .= t(267, $qy + 34, 'Shelf life ' . $life, 'ink600', 9.5);
    $body .= badge(690, $qy + 12, $count, 'ink100', 'ink700');
    $qy += 50;
}
$body .= t(251, 400, 'Everything here is 100% fresh — no meter is shown in a print queue.', 'ink500', 10);

// The printed label itself.
$body .= card(811, 120, 339, 300, 'What prints');
$body .= rect(843, 160, 275, 170, 'white', 6, 'ink');
$body .= t(857, 186, 'SAMBAL, PREPARED', 'ink', 13, '700');
$body .= line(857, 196, 1104, 196, 'ink300');
$body .= t(857, 218, 'PREPARED', 'ink500', 8, '700');
$body .= t(857, 234, '22 Aug 2026  14:30', 'ink', 11.5, '600');
$body .= t(857, 258, 'USE BY', 'danger', 8, '700');
$body .= t(857, 274, '25 Aug 2026  14:30', 'danger', 13, '700');
$body .= t(857, 298, 'Faiz · Main Kitchen', 'ink600', 9.5);
$body .= rect(1050, 250, 54, 54, 'ink100', 4);
$body .= t(1077, 282, 'QR', 'ink500', 10, '700', 'middle');
$body .= t(843, 350, 'The use-by date is calculated, never typed. It comes', 'ink600', 10.5);
$body .= t(843, 366, 'from the shelf-life rule for that item.', 'ink600', 10.5);

$body .= t(235, 456, 'Expiring — where the meter belongs', 'ink', 13, '700');
$body .= rect(235, 470, 915, 200, 'white', 12, 'ink200');
$expiring = [
    ['Cut fruit platter', 'Expires in 2 hours', 8,  'danger'],
    ['Chicken marinade',  'Expires in 9 hours', 22, 'warning'],
    ['Sambal, prepared',  'Expires in 2 days',  61, 'success'],
    ['Stock, chicken',    'Expires in 4 days',  82, 'success'],
];
$ey = 500;
foreach ($expiring as [$name, $when, $pct, $tone]) {
    $body .= t(251, $ey + 12, $name, 'ink', 11.5, '600');
    $body .= t(470, $ey + 12, $when, $tone, 11);
    $body .= meter(650, $ey + 4, 380, $pct, $tone);
    $body .= t(1134, $ey + 12, $pct . '%', 'ink600', 10.5, '600', 'end');
    $ey += 42;
}

write('label-printing', frame('Print Labels', 'Labels', $outletNav, 3, $body, 'outlet', 'Print set'));

// ── 7. Stock take ──────────────────────────────────────────────────────────
$body  = statCard(235, 120, 296, 'Counted', '184 of 312', 'Items with a figure entered', 'brand700');
$body .= statCard(546, 120, 296, 'Variance value', '-RM 1,842', 'Counted against expected', 'danger');
$body .= statCard(857, 120, 293, 'Started', '2 hours ago', 'By Nurul (Branch Manager)');

$body .= table(235, 226, 915, [
    ['Item', 16], ['Expected', 360], ['Counted', 480], ['Variance', 600], ['Value', 730],
], [
    ['Chicken thigh, boneless', '42.0 kg', '38.5 kg', '-3.5 kg',  '-RM 51.80'],
    ['Cooking oil, palm',       '68.0 kg', '68.0 kg', '0.0 kg',   'RM 0.00'],
    ['Yellow onion',            '95.0 kg', '88.0 kg', '-7.0 kg',  '-RM 27.65'],
    ['Basmati rice',            '120.0 kg', '124.0 kg', '+4.0 kg', '+RM 29.80'],
    ['Prawn 31/40',             '18.0 kg', '12.0 kg', '-6.0 kg',  '-RM 228.00'],
], [
    0 => ['Check', 'warning'],
    1 => ['Match', 'success'],
    2 => ['Check', 'warning'],
    3 => ['Match', 'success'],
    4 => ['Investigate', 'danger'],
], 38);

$body .= card(235, 480, 915, 190, 'What a variance usually means');
$reasons = [
    ['Wastage not recorded', 'Trim, spoilage and staff meals leave the store without a document.'],
    ['Transfer not received', 'A stock transfer sent from Central Kitchen that nobody accepted at this end.'],
    ['Recipe yield is wrong', 'If the yield % is optimistic, every portion quietly consumes more than the system thinks.'],
];
$ry2 = 522;
foreach ($reasons as [$title, $text]) {
    $body .= rect(251, $ry2, 883, 44, 'ink50', 8);
    $body .= t(267, $ry2 + 20, $title, 'ink', 11.5, '700');
    $body .= t(267, $ry2 + 36, $text, 'ink600', 10.5);
    $ry2 += 50;
}

write('stock-take', frame('Stock take', 'Inventory & Recipes', $outletNav, 2, $body, 'outlet', 'Post count'));

// ── 8. Duty roster ─────────────────────────────────────────────────────────
$days  = ['Mon 24', 'Tue 25', 'Wed 26', 'Thu 27', 'Fri 28', 'Sat 29', 'Sun 30'];
$staff = [
    ['Nurul B.',  'Branch Manager', ['M', 'M', 'OFF', 'M', 'M', 'M', 'OFF']],
    ['Faiz A.',   'Chef',           ['M', 'M', 'M', 'OFF', 'E', 'E', 'E']],
    ['Hakim R.',  'Cook',           ['E', 'E', 'E', 'E', 'OFF', 'M', 'M']],
    ['Siti K.',   'Service',        ['OFF', 'M', 'M', 'M', 'M', 'E', 'E']],
    ['Aiman Z.',  'Service',        ['E', 'OFF', 'E', 'E', 'E', 'M', 'M']],
    ['Mei Ling',  'Kitchen Help',   ['M', 'M', 'M', 'M', 'AL', 'AL', 'OFF']],
];

$body  = rect(235, 120, 915, 46, 'white', 10, 'ink200');
$body .= t(251, 148, 'Week of 24 – 30 August 2026', 'ink', 12.5, '700');
foreach ([['M  Morning', 640, 'brand'], ['E  Evening', 760, 'info'], ['OFF  Rest day', 880, 'ink'], ['AL  Leave', 1010, 'warning']] as [$label, $lx, $tone]) {
    $fill = $tone === 'ink' ? 'ink100' : $tone . '50';
    $text = $tone === 'ink' ? 'ink600' : $tone;
    $body .= badge($lx, 136, $label, $fill, $text === 'brand' ? 'brand700' : $text);
}

$gridX = 235;
$gridY = 180;
$colW  = 96;
$rowH  = 52;
$body .= rect($gridX, $gridY, 915, 42 + count($staff) * $rowH, 'white', 12, 'ink200');
$body .= rect($gridX, $gridY, 915, 40, 'ink50', 12);
$body .= rect($gridX, $gridY + 28, 915, 12, 'ink50');
$body .= t($gridX + 16, $gridY + 25, 'STAFF', 'ink500', 8.5, '700');
foreach ($days as $i => $day) {
    $body .= t($gridX + 250 + $i * $colW + $colW / 2, $gridY + 25, strtoupper($day), 'ink500', 8.5, '700', 'middle');
}

$sy = $gridY + 40;
foreach ($staff as $s => [$name, $role, $shifts]) {
    if ($s > 0) {
        $body .= line($gridX + 1, $sy, $gridX + 914, $sy, 'ink100');
    }
    $body .= t($gridX + 16, $sy + 24, $name, 'ink', 11.5, '600');
    $body .= t($gridX + 16, $sy + 39, $role, 'ink500', 9.5);

    foreach ($shifts as $i => $code) {
        $cx2 = $gridX + 250 + $i * $colW + $colW / 2;
        [$fill, $text] = match ($code) {
            'M'   => ['brand50', 'brand700'],
            'E'   => ['info50', 'info'],
            'AL'  => ['warning50', 'warning'],
            default => ['ink100', 'ink500'],
        };
        $body .= rect($cx2 - 30, $sy + 13, 60, 26, $fill, 7);
        $body .= t($cx2, $sy + 30, $code, $text, 10.5, '700', 'middle');
    }
    $sy += $rowH;
}

$body .= t(235, $sy + 66, 'The roster is what you PLAN. Attendance is what HAPPENED — they are separate records on purpose.', 'ink600', 11.5);

write('duty-roster', frame('Duty Roster', 'HR', $outletNav, 5, $body, 'outlet', 'Publish week'));

// ── 9. Payroll run ─────────────────────────────────────────────────────────
$body  = statCard(235, 120, 222, 'Gross', 'RM 48,920', '15 employees');
$body .= statCard(472, 120, 222, 'Statutory', 'RM 8,146', 'EPF, SOCSO, EIS, PCB');
$body .= statCard(709, 120, 222, 'Net payable', 'RM 40,774', 'To 15 bank accounts', 'success');
$body .= statCard(946, 120, 204, 'Service charge', 'RM 6,300', 'Distributed by points');

$body .= table(235, 226, 915, [
    ['Employee', 16], ['Basic', 300], ['OT + allow.', 400], ['Service chg', 520], ['Deductions', 650], ['Net', 800],
], [
    ['Nurul B.', 'RM 4,200', 'RM 180',   'RM 620', 'RM 742',  'RM 4,258'],
    ['Faiz A.',  'RM 3,600', 'RM 420',   'RM 620', 'RM 668',  'RM 3,972'],
    ['Hakim R.', 'RM 2,400', 'RM 310',   'RM 480', 'RM 452',  'RM 2,738'],
    ['Siti K.',  'RM 2,100', 'RM 95',    'RM 480', 'RM 384',  'RM 2,291'],
    ['Aiman Z.', 'RM 2,100', 'RM 250',   'RM 480', 'RM 392',  'RM 2,438'],
    ['Mei Ling', 'RM 1,900', 'RM 0',     'RM 420', 'RM 341',  'RM 1,979'],
], [], 36);

$body .= card(235, 484, 560, 186, 'Before you lock the run');
$checks = [
    ['Attendance approved for every employee', 'success'],
    ['Overtime claims approved or rejected', 'success'],
    ['Unpaid leave applied', 'success'],
    ['Service charge period closed', 'warning'],
];
$cy = 522;
foreach ($checks as [$label, $tone]) {
    $body .= sprintf('<circle cx="264" cy="%d" r="8" fill="%s"/>', $cy + 8, c($tone . '50'));
    $body .= sprintf('<path d="M260 %d l3 3 l6 -6" stroke="%s" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>', $cy + 8, c($tone));
    $body .= t(282, $cy + 12, $label, 'ink700', 11.5);
    $cy += 34;
}

$body .= card(811, 484, 339, 186, 'What locking does');
$body .= t(827, 538, 'A locked run stops recalculating. Payslips', 'ink700', 11);
$body .= t(827, 556, 'and the bank file are generated from the', 'ink700', 11);
$body .= t(827, 574, 'locked figures, so a later change to an', 'ink700', 11);
$body .= t(827, 592, 'employee\'s salary cannot silently rewrite', 'ink700', 11);
$body .= t(827, 610, 'a payslip somebody has already been sent.', 'ink700', 11);
$body .= badge(827, 630, 'IRREVERSIBLE', 'warning50', 'warning');

write('payroll-run', frame('Payroll run — August 2026', 'HR', $outletNav, 5, $body, 'outlet', 'Lock run'));

// ── 10. Reports hub ────────────────────────────────────────────────────────
$reports = [
    ['Cost analysis', 'Food cost by category, against target', 'chart'],
    ['Recipe costing', 'Every recipe, its cost and its margin', 'cube'],
    ['Price history', 'What each ingredient has cost over time', 'trending'],
    ['Sales report', 'Sales by day, category and outlet', 'chart'],
    ['Purchase summary', 'Spend by supplier and by outlet', 'cart'],
    ['Wastage', 'What was thrown away and what it cost', 'trash'],
    ['Labour cost', 'Payroll against sales, by outlet', 'users'],
    ['Attendance', 'Who worked, when, and for how long', 'clock'],
    ['Stock movement', 'What came in, what went out', 'cube'],
];

$body = t(235, 130, 'Every report exports to PDF and CSV, and can be scheduled to arrive by email.', 'ink600', 12);

$rx = 235;
$ry3 = 152;
foreach ($reports as $i => [$title, $sub, $icon]) {
    $col = $i % 3;
    $row = intdiv($i, 3);
    $x   = $rx + $col * 310;
    $y   = $ry3 + $row * 118;

    $body .= rect($x, $y, 295, 100, 'white', 12, 'ink200');
    $body .= rect($x + 16, $y + 18, 30, 30, 'brand50', 8);
    $body .= rect($x + 25, $y + 27, 12, 12, 'brand600', 3);
    $body .= t($x + 58, $y + 32, $title, 'ink', 12.5, '700');
    foreach (explode("\n", wordwrap($sub, 34, "\n", true)) as $k => $ln) {
        $body .= t($x + 16, $y + 66 + $k * 16, $ln, 'ink600', 10.5);
    }
}

$body .= card(235, 512, 915, 158, 'AI Analysis writes the commentary');
$body .= t(251, 566, 'Reports give you the numbers. AI Analysis reads them and writes what changed and why — in the words an outlet', 'ink700', 11.5);
$body .= t(251, 586, 'team uses, not a boardroom summary. It runs on your own data and quotes the figure behind every claim.', 'ink700', 11.5);
$body .= rect(251, 604, 883, 48, 'brand50', 8);
$body .= t(267, 624, '"Food cost rose 1.4pt to 33.4% this week. Chicken thigh is the whole of it — up 18% since', 'brand700', 11);
$body .= t(267, 641, 'Tuesday, across 214 kg. At last month\'s price the week would have closed at 31.9%."', 'brand700', 11);

write('reports-hub', frame('Reports', 'Business Intelligence', $outletNav, 7, $body));

// ── 11. Central Kitchen ────────────────────────────────────────────────────
$kitchenNav = ['Dashboard', 'Production', 'Inventory', 'Operations', 'Labels', 'Insights', 'System'];

$body  = statCard(235, 120, 296, 'Orders today', '7', '3 in progress, 4 queued', 'kitchen');
$body .= statCard(546, 120, 296, 'Yield vs expected', '96.4%', 'Across 12 production runs', 'success');
$body .= statCard(857, 120, 293, 'Transfers out', '4', 'To Bangsar, Damansara, KLCC');

$body .= table(235, 226, 915, [
    ['Production order', 16], ['Recipe', 220], ['Planned', 440], ['Produced', 550], ['Yield', 670],
], [
    ['PRD-2026-0311', 'Sambal, bulk',       '40.0 kg', '38.6 kg', '96.5%'],
    ['PRD-2026-0312', 'Rempah paste',       '25.0 kg', '24.4 kg', '97.6%'],
    ['PRD-2026-0313', 'Chicken stock',      '60.0 L',  '—',       '—'],
    ['PRD-2026-0314', 'Curry base, bulk',   '35.0 kg', '—',       '—'],
], [
    0 => ['Complete', 'success'],
    1 => ['Complete', 'success'],
    2 => ['In progress', 'info'],
    3 => ['Queued', 'warning'],
], 38);

$body .= t(235, 440, 'Central Kitchen makes it once, the outlets receive it', 'ink', 13, '700');
$svgFlow  = node(235, 462, 200, 'Production order', 'What to make, how much', 'kitchen');
$svgFlow .= arrow(439, 493, 465);
$svgFlow .= node(469, 462, 200, 'Production log', 'What was actually made', 'kitchen');
$svgFlow .= arrow(673, 493, 699);
$svgFlow .= node(703, 462, 200, 'Stock transfer', 'Sent to an outlet', 'kitchen');
$svgFlow .= arrow(907, 493, 933);
$svgFlow .= node(937, 462, 213, 'Outlet receives', 'Stock lands, cost follows', 'kitchen');
$body .= $svgFlow;

$body .= card(235, 552, 915, 118, 'Why the workspace is a different colour');
$body .= t(251, 606, 'Purple is not a status — it says WHERE YOU ARE STANDING. The Central Kitchen workspace shares the product with the', 'ink700', 11.5);
$body .= t(251, 626, 'outlet side but answers a different question: what are we making today, rather than what are we selling today.', 'ink700', 11.5);
$body .= t(251, 646, 'A user attached to a central kitchen switches between the two from the top of the sidebar.', 'ink700', 11.5);

write('central-kitchen', frame('Production', 'Central Kitchen', $kitchenNav, 1, $body, 'kitchen', '+ Production order'));

// ── 12. Billing & invoices (admin) ─────────────────────────────────────────
$adminNav = ['Users', 'Companies', 'Plans', 'Subscriptions', 'Invoices', 'Billing Settings', 'Coupons', 'Documentation'];

$body  = statCard(235, 120, 222, 'Outstanding', 'MYR 12,840', 'Draft and issued, unpaid');
$body .= statCard(472, 120, 222, 'Overdue', 'MYR 3,290', 'Past the due date', 'danger');
$body .= statCard(709, 120, 222, 'Paid this month', 'MYR 41,600', 'Settled since 1 Aug', 'success');
$body .= statCard(946, 120, 204, 'Drafts', '3', 'Not yet issued');

$body .= table(235, 226, 915, [
    ['Invoice', 16], ['Company', 160], ['Plan', 380], ['Issued', 490], ['Due', 590], ['Total', 690],
], [
    ['INV-2026-0142', 'Warung Pak Din Sdn Bhd', 'Growth',  '01 Aug', '15 Aug', 'MYR 1,290'],
    ['INV-2026-0141', 'Kopitiam Group',          'Scale',   '01 Aug', '15 Aug', 'MYR 3,290'],
    ['INV-2026-0140', 'Rasa Sayang F&B',         'Starter', '28 Jul', '11 Aug', 'MYR   490'],
    ['INV-2026-0139', 'Nasi Kandar Deen',        'Growth',  '25 Jul', '08 Aug', 'MYR 1,290'],
    ['INV-2026-0138', 'Bakery Co',               'Starter', '—',      '—',      'MYR   490'],
], [
    0 => ['Paid', 'success'],
    1 => ['Issued', 'info'],
    2 => ['Overdue', 'danger'],
    3 => ['Paid', 'success'],
    4 => ['Draft', 'warning'],
], 38);

$body .= t(235, 470, 'The states an invoice can be in', 'ink', 13, '700');
$states = [
    ['Draft', 'Your working copy. Editable, invisible to the customer.', 'warning'],
    ['Issued', 'Sent. The numbers are fixed — void it to change them.', 'info'],
    ['Paid', 'Settled. A payment row exists to match it.', 'success'],
    ['Void', 'Cancelled, but the number is kept so the sequence has no gaps.', 'ink'],
];
$sx = 235;
foreach ($states as [$name, $text, $tone]) {
    $fill = $tone === 'ink' ? 'ink50' : $tone . '50';
    $body .= rect($sx, 488, 220, 104, $fill, 10);
    $body .= rect($sx, 488, 3, 104, $tone === 'ink' ? 'ink400' : $tone, 2);
    $body .= t($sx + 16, 516, $name, 'ink', 12.5, '700');
    foreach (explode("\n", wordwrap($text, 27, "\n", true)) as $k => $ln) {
        $body .= t($sx + 16, 540 + $k * 17, $ln, 'ink700', 10.5);
    }
    $sx += 232;
}

$body .= card(235, 606, 915, 64, '');
$body .= t(251, 632, 'Paid subscriptions raise an invoice automatically when the gateway confirms the payment. You raise one by hand', 'ink700', 11.5);
$body .= t(251, 652, 'for a bank transfer, an agreed upgrade, or a credit — and the numbering sequence is shared by both.', 'ink700', 11.5);

write('admin-invoices', frame('Invoices', 'Admin', $adminNav, 4, $body, 'outlet', '+ New invoice'));

// ── 13. Training ───────────────────────────────────────────────────────────
$body  = statCard(235, 120, 296, 'Assigned', '68', 'Across 15 staff and 6 courses');
$body .= statCard(546, 120, 296, 'Completed', '54', '79% of what was assigned', 'success');
$body .= statCard(857, 120, 293, 'Certificates issued', '31', 'Verifiable by QR');

$body .= card(235, 226, 560, 250, 'Learning path — New kitchen hire');
$path = [
    ['Food safety basics', 'Course · 12 min', 'success', 'Done'],
    ['Kitchen hygiene quiz', 'Quiz · 10 questions', 'success', 'Passed 90%'],
    ['Knife skills & prep', 'Course · 18 min', 'info', 'In progress'],
    ['Station handover SOP', 'Course · 8 min', 'ink', 'Locked'],
];
$py = 268;
foreach ($path as $i => [$title, $sub, $tone, $state]) {
    $dot = $tone === 'ink' ? 'ink300' : $tone;
    $body .= sprintf('<circle cx="266" cy="%d" r="7" fill="%s"/>', $py + 16, c($dot));
    if ($i < count($path) - 1) {
        $body .= line(266, $py + 26, 266, $py + 52, $tone === 'ink' ? 'ink200' : 'brand200', 2);
    }
    $body .= t(288, $py + 14, $title, 'ink', 11.5, '600');
    $body .= t(288, $py + 30, $sub, 'ink600', 10);
    $body .= badge(660, $py + 6, $state, $tone === 'ink' ? 'ink100' : $tone . '50', $tone === 'ink' ? 'ink600' : $tone);
    $py += 52;
}

$body .= card(811, 226, 339, 250, 'Staff sign in with a PIN');
$body .= t(827, 282, 'The training portal is its own login. Floor', 'ink700', 11);
$body .= t(827, 300, 'staff do not need a Servora account —', 'ink700', 11);
$body .= t(827, 318, 'they use a 6-digit PIN on a shared tablet.', 'ink700', 11);
$pinX = 838;
foreach (['4', '9', '2', '•', '•', '•'] as $digit) {
    $body .= rect($pinX, 340, 44, 52, 'ink50', 8, 'ink200');
    $body .= t($pinX + 22, 374, $digit, 'ink', 18, '700', 'middle');
    $pinX += 50;
}
$body .= t(827, 420, 'A live session puts the same quiz on every', 'ink700', 11);
$body .= t(827, 438, 'phone at once, with a leaderboard — briefing', 'ink700', 11);
$body .= t(827, 456, 'a full shift takes about six minutes.', 'ink700', 11);

$body .= t(235, 510, 'Report cards show who has actually learned what', 'ink', 13, '700');
$body .= table(235, 524, 915, [
    ['Employee', 16], ['Assigned', 320], ['Completed', 440], ['Avg. score', 570], ['Certificates', 720],
], [
    ['Faiz A.',  '8', '8', '92%', '4'],
    ['Siti K.',  '6', '5', '84%', '2'],
    ['Aiman Z.', '6', '3', '71%', '1'],
], [], 34);

write('training', frame('Learning', 'Learning & Development', $outletNav, 6, $body, 'outlet', 'Assign course'));

echo "Done.\n";
