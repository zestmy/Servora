<?php

return [
    'slug'    => 'sales',
    'title'   => 'Sales',
    'summary' => 'Recording daily sales, syncing from your POS, sales categories and targets.',
    'icon'    => 'chart',
    'sort'    => 60,
    'articles' => [

        [
            'slug'     => 'record-sales',
            'title'    => 'Record your sales',
            'excerpt'  => 'Enter the day, and every cost figure in Servora has something to be a percentage of.',
            'keywords' => 'sales, daily sales, revenue, takings, closing, sales record',
            'body' => <<<'HTML'
<p>Servora is not a till. It needs the sales figure so it can turn cost into <em>cost percentage</em>, deduct stock through recipes, and compare labour to revenue. Without sales, half the product has nothing to divide by.</p>

<h2>Three ways to get sales in</h2>

<ol>
  <li><strong>Daily totals</strong> — the fastest. One figure per sales category per day, typed at close.</li>
  <li><strong>Item level</strong> — quantity sold per menu item. More work, and the only way stock deducts through recipes.</li>
  <li><strong>POS sync</strong> — item level, automatically. See <a href="/help/sales/pos-sync">POS sync</a>.</li>
</ol>

<h2>Entering a day</h2>

<ol>
  <li><strong>Sales → Sales Records → New record.</strong></li>
  <li>Confirm the outlet and the business date. The business date is the trading day, not the calendar date the shift ended — a 2am close belongs to the night before.</li>
  <li>Enter figures by sales category, or add lines per item.</li>
  <li>Attach the Z-reading or a photo of it. When a figure is questioned three months later, the attachment settles it.</li>
  <li>Save.</li>
</ol>

<h2>Closing a period</h2>

<p>A closed period is locked against edits. Close each month once you are satisfied with it — that is what stops a corrected figure quietly rewriting a report somebody has already sent.</p>

<h2>Enter daily, not weekly</h2>

<p>A week entered on Monday is a week of stock that did not deduct, dashboards that were wrong, and variance you cannot attribute to a day. It also takes longer in total, because nobody remembers Wednesday.</p>
HTML,
        ],

        [
            'slug'     => 'pos-sync',
            'title'    => 'POS sync',
            'excerpt'  => 'Pull item-level sales from your point-of-sale system instead of typing them.',
            'keywords' => 'POS, point of sale, sync, import, zeoniq, integration, agent',
            'body' => <<<'HTML'
<p>If your POS can be read, Servora reads it. Item-level sales arrive automatically, stock deducts through recipes, and nobody types a number at close.</p>

<h2>How it connects</h2>

<p>A small sync agent runs on the POS machine or a PC on the same network, reads the day's sales, and posts them to Servora. It is the same idea as the label print agent: the POS is inside the outlet's network, and the agent is what reaches it.</p>

<ol>
  <li>Download the POS sync agent from the <strong>Downloads</strong> page.</li>
  <li>Install it on a machine that can see the POS database.</li>
  <li>Pair it with a code generated under <strong>Sales → POS Sync</strong>.</li>
  <li>Map the POS's departments and item codes onto your Servora sales categories and recipes.</li>
</ol>

<h2>Mapping is the real work</h2>

<p>The POS calls a dish <em>"NSI LMK AYM"</em> and item code 4412. Servora calls it <em>"Nasi Lemak Ayam Berempah"</em>. Mapping ties the two together, once. Anything unmapped shows up as unmatched on the sync screen rather than being silently dropped — check that list after the first few days.</p>

<h2>What syncs and what does not</h2>

<ul>
  <li><strong>Does</strong> — items sold, quantities, gross value, by business date and outlet.</li>
  <li><strong>Does not</strong> — individual customer transactions, payment types, or anything that is your POS's job. Servora wants the day's shape, not its receipts.</li>
</ul>

<h2>Checking it worked</h2>

<p>The POS Sync screen shows each batch with its date, the number of lines, and anything unmatched. If a day looks light, look here before you look at the sales report.</p>
HTML,
        ],

        [
            'slug'     => 'categories-and-targets',
            'title'    => 'Sales categories and targets',
            'excerpt'  => 'Group revenue the way you think about it, and set the number each outlet is aiming at.',
            'keywords' => 'sales category, target, budget, forecast, revenue mix',
            'body' => <<<'HTML'
<h2>Sales categories</h2>

<p>A sales category is how you group revenue: Food, Beverage, Alcohol, Delivery, Catering. Every sales figure belongs to one, and they are how the reports break revenue down.</p>

<p>Keep them few and meaningful. The useful test: would you make a different decision if this category moved? If not, it does not need to be its own category.</p>

<p>Beverage is almost always worth separating from food — the margins are completely different and blending them hides both.</p>

<h2>Targets</h2>

<p>Under <strong>Sales → Sales Targets</strong>, set a target per outlet per period. Once set, the dashboard and the sales reports show actual against target rather than a number with no context.</p>

<p>Targets can be set per sales category too, which is how you find out that total revenue hit target while food missed and delivery covered it.</p>

<h2>Setting a number worth having</h2>

<p>Base it on the same period last year plus what you actually know is changing — a price rise, a new platform, a competitor opening. A target invented at the start of the year and never revisited stops being read by February.</p>
HTML,
        ],
    ],
];
