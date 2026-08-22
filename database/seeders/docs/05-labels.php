<?php

return [
    'slug'    => 'labels',
    'title'   => 'Labels',
    'summary' => 'HACCP food-safety labels: printing, print sets, shelf-life rules, expiring stock and printers.',
    'icon'    => 'tag',
    'sort'    => 50,
    'articles' => [

        [
            'slug'     => 'what-the-label-system-does',
            'title'    => 'What the label system does',
            'excerpt'  => 'Prints a food-safety label whose use-by date is calculated, not typed.',
            'keywords' => 'labels, HACCP, food safety, use by, date label, sticker',
            'body' => <<<'HTML'
<p>Every prepped item in a professional kitchen needs a label saying what it is, when it was made and when it must be thrown away. Handwriting them is slow, illegible by day three, and the date is whatever the person with the marker believed at the time.</p>

<p>Servora prints them, and — this is the part that matters — <strong>calculates the use-by date</strong> from a rule you set once, rather than asking the person at the bench.</p>

<figure><img src="/images/docs/label-printing.svg" alt="The label print screen: a queue of items with their shelf lives on the left, a preview of the printed label showing item name, prepared date, use-by date and staff name on the right, and below an Expiring list with remaining-life meters"><figcaption>The print queue, the label it produces, and the Expiring screen that watches them.</figcaption></figure>

<h2>What is on a label</h2>

<ul>
  <li>The item name, large enough to read across a chiller.</li>
  <li><strong>Prepared</strong> — the date and time it was printed.</li>
  <li><strong>Use by</strong> — calculated from the shelf-life rule for that item in that storage state.</li>
  <li>Who prepared it, and which outlet or station.</li>
  <li>Optionally a QR code, allergen text, or your own fields — templates are configurable.</li>
</ul>

<h2>The four screens</h2>

<table>
  <thead><tr><th>Screen</th><th>For</th></tr></thead>
  <tbody>
    <tr><td><strong>Print Labels</strong></td><td>Printing, one item at a time.</td></tr>
    <tr><td><strong>Print Sets</strong></td><td>Printing a whole station's labels in one go.</td></tr>
    <tr><td><strong>Expiring</strong></td><td>What is running out of life, right now.</td></tr>
    <tr><td><strong>Print Log</strong></td><td>Everything printed, with who and when — your audit trail.</td></tr>
  </tbody>
</table>

<h2>Built for wet hands</h2>

<p>The staff-facing label app is a separate, simplified screen designed for a tablet at the bench: big targets, no small text, sign in with a PIN rather than a password. It is used with gloves on, and that constraint shaped the whole design.</p>
HTML,
        ],

        [
            'slug'     => 'print-a-label',
            'title'    => 'Print a label',
            'excerpt'  => 'Pick the item, confirm the storage state, print. Thirty seconds including the walk to the printer.',
            'keywords' => 'print label, printing, prepared by, storage state, chill, freeze',
            'body' => <<<'HTML'
<h2>Printing</h2>

<ol>
  <li>Open <strong>Labels → Print Labels</strong>.</li>
  <li>Find the item. Recipes, prep items and ingredients are all printable.</li>
  <li>Confirm the <strong>storage state</strong> — chilled, frozen, ambient, thawed. This changes the shelf life, so it is asked rather than assumed.</li>
  <li>Set the quantity of labels.</li>
  <li>Check <strong>Prepared by</strong>. It is mandatory: a food-safety label with nobody's name on it does not do the job it exists for.</li>
  <li><strong>Print.</strong></li>
</ol>

<h2>The use-by date</h2>

<p>You do not type it. It comes from the shelf-life rule that applies to that item in that state — see <a href="/help/labels/shelf-life-rules">Shelf-life rules</a>. If no rule can be found, Servora says so and asks you to enter a date by hand, and flags the label as manually dated so it stands out in the log.</p>

<h2>Reprinting</h2>

<p>A label that jams or smudges can be reprinted from the <strong>Print Log</strong> — and it reprints with the <em>original</em> prepared time and use-by date, not a fresh one. Reprinting a damaged label must never quietly extend the food's life.</p>

<h2>If nothing comes out</h2>

<ol>
  <li>Check the job in the <strong>Print Log</strong>. If it says <em>failed</em>, the message says why.</li>
  <li>Check the <strong>Print Agent</strong> for that outlet is online — see <a href="/help/labels/printers-and-print-agents">Printers and print agents</a>.</li>
  <li>Check the printer has labels in it and no error light. A spooled job that never printed is nearly always paper or a lid.</li>
</ol>
HTML,
        ],

        [
            'slug'     => 'print-sets',
            'title'    => 'Print sets',
            'excerpt'  => 'One button for a whole station — every label the grill needs at open, in one press.',
            'keywords' => 'print set, batch print, station, opening, bulk labels',
            'body' => <<<'HTML'
<p>At open, a station needs the same fifteen labels it needed yesterday. A print set is that list, saved.</p>

<h2>Building one</h2>

<ol>
  <li><strong>Labels → Print Sets → New set.</strong></li>
  <li>Name it after the physical place: <em>Chiller 1</em>, <em>Sandwich Station</em>, <em>Grill</em>. Staff pick by where they are standing.</li>
  <li>Add a line per item, with its storage state and how many labels.</li>
</ol>

<p>Sets belong to an outlet — each branch has its own, because each branch's chiller holds different things.</p>

<h2>Printing a set</h2>

<p>Open the set, confirm who is preparing, review the list, print. The review step matters: a set is a default, not a promise. If you are not making sambal today, uncheck it rather than printing a label for something that does not exist.</p>

<h2>Each line carries its own shelf life</h2>

<p>A set can mix a 12-hour item and a 30-day item. Each line resolves its own rule, so every label in the batch gets its own correct use-by date. The dates on one print run will not match, and that is right.</p>

<h2>Why there is no expiry meter here</h2>

<p>Elsewhere in the labels module a meter shows how much life an item has left. There is none in the print queue, because everything in it was made thirty seconds ago and is 100% fresh by definition. A row of full meters teaches nothing. The meter belongs on <strong>Expiring</strong>.</p>
HTML,
        ],

        [
            'slug'     => 'shelf-life-rules',
            'title'    => 'Shelf-life rules',
            'excerpt'  => 'Where use-by dates come from, and the order Servora looks for one in.',
            'keywords' => 'shelf life, use by, expiry, HACCP, storage state, chilled, frozen',
            'body' => <<<'HTML'
<p>A shelf-life rule says: <em>this item, in this storage state, lasts this long</em>. Set them once and every label prints the right date without anybody deciding.</p>

<h2>Storage state is part of the rule</h2>

<p>The same item lasts different lengths of time depending on how it is kept, so a rule is always for a state — chilled, frozen, ambient or thawed. A three-day chilled life says nothing about frozen, and Servora will not guess: if you have not set a frozen rule, it asks rather than inventing one.</p>

<h2>Setting them at the right level</h2>

<p>Rules can be set on a <strong>category</strong> or on an <strong>individual item</strong>. Do the category first — "all prepared sauces last 5 days chilled" covers forty items in one row — and then override the handful that differ.</p>

<h2>The order Servora looks</h2>

<ol>
  <li>A rule on the item itself.</li>
  <li>For a prep item, the rule on the recipe behind it.</li>
  <li>A rule on the item's category.</li>
  <li>A shelf life recorded on the recipe from before rules existed — used only when the state being asked about matches the item's own storage instruction.</li>
  <li>Nothing found: staff enter the date by hand and the label is flagged as manually dated.</li>
</ol>

<p>The screen shows which level a life came from, so you can see whether an item is inherited or set directly.</p>

<h2>Getting the numbers right</h2>

<p>Shelf lives are a food-safety matter, not a system setting. Take them from your HACCP plan or your food-safety consultant, not from what the last kitchen did. Servora will print whatever you configure, correctly and consistently — including a wrong number.</p>
HTML,
        ],

        [
            'slug'     => 'expiring-and-the-print-log',
            'title'    => 'Expiring stock and the print log',
            'excerpt'  => 'See what is about to run out of life, and prove what was labelled when.',
            'keywords' => 'expiring, expiry, about to expire, print log, audit, food safety audit',
            'body' => <<<'HTML'
<h2>Expiring</h2>

<p><strong>Labels → Expiring</strong> lists everything currently labelled at that outlet, sorted by how little life is left. Each row carries a meter showing the proportion of its own life remaining.</p>

<p>The meter turns amber in the last quarter of that item's own window, capped at three days. Windows here run from twelve hours to a month, so a flat "warn under 24 hours" would make every same-day prep item amber the moment it was printed — the warning has to be proportional to the item to mean anything.</p>

<p>Walk this screen before service. It is the difference between finding out at 7pm and finding out at 11am.</p>

<h2>The print log</h2>

<p>Every label ever printed, with the item, the storage state, the prepared time, the use-by, who printed it and from which printer. Filter by date, outlet, item or person.</p>

<p>Two things it is for:</p>

<ul>
  <li><strong>An audit.</strong> An inspector asking to see your date-labelling records gets a filtered, dated, named list rather than a shoebox.</li>
  <li><strong>An investigation.</strong> When something goes wrong, the log says exactly what was labelled, when, and by whom.</li>
</ul>

<p>Labels flagged as <em>manually dated</em> stand out here — they are the ones where no rule was found and a human typed the date, and a lot of them means a gap in your rules worth closing.</p>
HTML,
        ],

        [
            'slug'     => 'printers-and-print-agents',
            'title'    => 'Printers and print agents',
            'excerpt'  => 'How a label gets from the browser to a thermal printer in the kitchen, and what to do when it does not.',
            'keywords' => 'printer, print agent, thermal printer, not printing, offline, paper size, setup',
            'body' => <<<'HTML'
<p>Servora runs in a browser and the printer is a physical device in a kitchen. The <strong>print agent</strong> is the small Windows program that joins the two.</p>

<h2>How it works</h2>

<ol>
  <li>Someone presses Print in Servora. A print job is queued on the server.</li>
  <li>The print agent on the outlet PC polls for jobs every few seconds.</li>
  <li>It downloads the PDF and sends it to the named Windows printer at exactly 100% scale.</li>
  <li>It reports back <em>done</em> or <em>error</em>, and the print log shows which.</li>
</ol>

<h2>Setting one up</h2>

<ol>
  <li>Download the installer from the <strong>Downloads</strong> page.</li>
  <li>Run it on the outlet PC the printer is attached to. It installs as a Windows service, so it survives a reboot and needs nobody logged in.</li>
  <li>It asks for a <strong>pairing code</strong>. Generate one in Servora under <strong>Labels → Print Agents</strong>.</li>
  <li>Once paired, the agent reports the printers it can see. Match each to a Servora printer under <strong>Labels → Label Printers</strong>, and set the paper form.</li>
</ol>

<h2>Paper size is the thing that goes wrong</h2>

<p>Label printers default to A4 or Letter, and a 50 × 30 mm label rendered onto A4 comes out as a rotated postage stamp in the corner of a big sheet. Two rules:</p>

<ul>
  <li>Create the label size as a named form in Windows <em>Print Server Properties</em>, and set it as the printer's default preference.</li>
  <li>Name that form on the Servora printer too, so the job specifies it rather than trusting the driver.</li>
</ul>

<p>Never set the driver to scale-to-fit. The templates are drawn at real size and a scaled label is the wrong size.</p>

<h2>When nothing prints</h2>

<table>
  <thead><tr><th>Symptom</th><th>Usually</th></tr></thead>
  <tbody>
    <tr><td>Agent shows offline</td><td>The outlet PC is off, asleep, or off the network. Check the machine before anything else.</td></tr>
    <tr><td>Job stuck at queued</td><td>Agent is not running. Restart the Servora Print Agent service.</td></tr>
    <tr><td>Job says done, nothing came out</td><td>It reached the Windows spooler. Look at the printer: labels, lid, error light.</td></tr>
    <tr><td>Prints wrong size or rotated</td><td>Paper form. See above.</td></tr>
    <tr><td>Job says error</td><td>The message on the job says what the agent hit.</td></tr>
  </tbody>
</table>

<blockquote><p><em>Done</em> honestly means "handed to the Windows spooler and the printer program exited cleanly". It does not mean a label physically emerged — no software can promise that.</p></blockquote>
HTML,
        ],
    ],
];
