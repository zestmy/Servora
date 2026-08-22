<?php

return [
    'slug'    => 'inventory',
    'title'   => 'Inventory',
    'summary' => 'Stock on hand, stock takes and variance, transfers between outlets, wastage and par levels.',
    'icon'    => 'clipboard',
    'sort'    => 40,
    'articles' => [

        [
            'slug'     => 'stock-on-hand',
            'title'    => 'How stock on hand is worked out',
            'excerpt'  => 'What moves your stock up and down, and why the number drifts if the team skips a step.',
            'keywords' => 'stock, inventory, on hand, stock level, closing stock',
            'body' => <<<'HTML'
<p>Servora keeps a running figure for every ingredient at every outlet. It is arithmetic, not magic, and it is only right if every movement gets recorded.</p>

<h2>What moves it up</h2>
<ul>
  <li><strong>Goods received</strong> — the main one.</li>
  <li><strong>Transfers in</strong> from another outlet or the central kitchen, once received.</li>
  <li><strong>Production</strong> of a prep item at that outlet.</li>
  <li><strong>A stock take</strong>, which does not move the figure so much as replace it.</li>
</ul>

<h2>What moves it down</h2>
<ul>
  <li><strong>Sales</strong> — through the recipe. Sell forty nasi lemak and Servora deducts forty portions' worth of each ingredient.</li>
  <li><strong>Wastage</strong> you record.</li>
  <li><strong>Staff meals</strong> you record.</li>
  <li><strong>Transfers out</strong>.</li>
  <li><strong>Consumption by production</strong>, if the outlet makes its own prep.</li>
</ul>

<h2>Where the drift comes from</h2>

<p>If counted stock is consistently below system stock, one of these is happening and it is almost always the first:</p>

<ol>
  <li><strong>Wastage is not being recorded.</strong> Trim, spoilage and mistakes leave the store with no document.</li>
  <li><strong>Yield percentages are optimistic.</strong> If the system thinks 100% of a chicken is usable, every portion quietly eats more than it is charged for.</li>
  <li><strong>Transfers were sent but never received</strong> at the other end.</li>
  <li><strong>Sales are not synced</strong>, so nothing has been deducted at all.</li>
</ol>

<p>Chase it in that order. A stock take tells you the size of the gap; only one of those four tells you the cause.</p>
HTML,
        ],

        [
            'slug'     => 'run-a-stock-take',
            'title'    => 'Run a stock take',
            'excerpt'  => 'Count what is actually there, see the variance against what the system expected, and act on it.',
            'keywords' => 'stock take, stocktake, count, variance, closing stock, inventory count',
            'body' => <<<'HTML'
<p>A stock take replaces the system's figure with a counted one, and tells you how far apart they were. Until you have done one, your food cost is an estimate.</p>

<figure><img src="/images/docs/stock-take.svg" alt="A stock take screen showing 184 of 312 items counted, a variance value of minus RM 1,842, and a table of items with expected quantity, counted quantity, variance and value, with rows flagged Match, Check or Investigate"><figcaption>A stock take in progress. The variance column is the whole reason for doing it.</figcaption></figure>

<h2>Before you start</h2>

<ul>
  <li>Receive every delivery that has arrived.</li>
  <li>Post every transfer, both ends.</li>
  <li>Enter the day's sales.</li>
  <li>Count when nothing is moving — before service or after close.</li>
</ul>

<p>Counting mid-service produces a variance that measures nothing except how busy you were.</p>

<h2>Counting</h2>

<ol>
  <li><strong>Stock Management → New stock take.</strong> Pick the outlet, the date, and whether you are counting in detail (line by line) or by category summary.</li>
  <li>Work through the list in the order you walk the store — not alphabetically. Set your product categories up to match your physical layout and this takes half the time.</li>
  <li>Enter what you count. Leave an item blank rather than guessing; blank is honest and zero is a claim.</li>
  <li>Save as you go. A part-finished count survives being closed.</li>
  <li><strong>Post</strong> when it is complete.</li>
</ol>

<h2>Reading the variance</h2>

<table>
  <thead><tr><th>Pattern</th><th>Usually means</th></tr></thead>
  <tbody>
    <tr><td>One item badly short, the rest fine</td><td>Theft, or a transfer nobody recorded.</td></tr>
    <tr><td>Everything a little short</td><td>Yield percentages are too generous.</td></tr>
    <tr><td>Everything a little over</td><td>Portioning is under spec, or sales are not fully synced.</td></tr>
    <tr><td>High-value items short</td><td>Worth investigating today, not at month end.</td></tr>
  </tbody>
</table>

<h2>How often</h2>

<p>Full count monthly, at minimum — you cannot close a month's food cost without one. A weekly count of your top twenty items by value catches most problems while they are still small.</p>
HTML,
        ],

        [
            'slug'     => 'transfers-between-outlets',
            'title'    => 'Transfers between outlets',
            'excerpt'  => 'Move stock from one location to another, with both ends confirming.',
            'keywords' => 'transfer, move stock, between outlets, in transit, central kitchen delivery',
            'body' => <<<'HTML'
<p>A transfer moves stock and its cost from one outlet to another. Both ends have to act — that is the design, not an inconvenience.</p>

<h2>Sending</h2>

<ol>
  <li><strong>Stock Management → New transfer.</strong></li>
  <li>Choose the destination outlet.</li>
  <li>Add lines: ingredient, quantity. The cost travels with it automatically.</li>
  <li><strong>Send.</strong> The stock leaves the sending outlet and the transfer becomes <em>in transit</em>.</li>
</ol>

<h2>Receiving</h2>

<p>At the other end, the transfer appears waiting to be received. Confirm the quantities that actually arrived — if a crate is short, receive the short quantity. The stock lands and the transfer closes.</p>

<h2>In transit is a real state</h2>

<p>Between send and receive, the stock belongs to neither outlet. This is deliberate: it makes a transfer that was sent and never received visible as a gap, rather than silently inflating the receiving outlet's count. If your variance report keeps showing a shortfall at one branch, look here first.</p>

<h2>Cancelling</h2>

<p>Either end can cancel before it is received; the stock returns to the sender. After receipt, raise a transfer the other way rather than cancelling — the movement happened and the record should say so.</p>
HTML,
        ],

        [
            'slug'     => 'wastage-and-staff-meals',
            'title'    => 'Wastage and staff meals',
            'excerpt'  => 'The two habits that decide whether your stock figures mean anything.',
            'keywords' => 'wastage, waste, spoilage, staff meal, family meal, throw away',
            'body' => <<<'HTML'
<p>These are the least glamorous screens in Servora and the two that most affect whether the rest of it works.</p>

<h2>Wastage</h2>

<p>Anything that leaves stock without being sold: spoilage, burnt, dropped, expired, over-produced. Record it under <strong>Stock Management → Wastage</strong> with the ingredient, the quantity and a reason.</p>

<p>Recording wastage does not increase your food cost — the food was already gone. What it does is move that cost from "mysterious variance" into a line you can read. A month with RM 2,000 of unexplained variance and a month with RM 2,000 of recorded wastage cost the same and only one of them can be fixed.</p>

<h2>Make the reasons few and real</h2>

<p>Four or five reasons, matched to what actually happens: <em>Spoilage</em>, <em>Prep error</em>, <em>Over-production</em>, <em>Expired</em>, <em>Customer return</em>. Twelve reasons produce a report where everything is "Other".</p>

<h2>Staff meals</h2>

<p>Staff meals are real food from real stock and, at fifteen staff eating daily, real money. Record them under <strong>Staff Meals</strong> — per employee, per meal. They deduct from stock, they appear as their own cost line rather than inflating food cost, and where you charge staff for meals it feeds the payroll deduction.</p>

<h2>The habit</h2>

<p>Both of these are only useful if they happen at the moment, on the floor. Assign it to whoever closes the kitchen and make it part of the close-down checklist. A wastage log written from memory on Friday is a work of fiction.</p>
HTML,
        ],

        [
            'slug'     => 'par-levels',
            'title'    => 'Par levels',
            'excerpt'  => 'Set the level each item should not fall below, and let the system flag the ones that have.',
            'keywords' => 'par level, reorder, minimum stock, low stock, order guide',
            'body' => <<<'HTML'
<p>A par level is the quantity of an item an outlet should hold. Set them and ordering becomes a matter of topping up to par rather than walking the store guessing.</p>

<h2>Setting them</h2>

<p>Under <strong>Stock Management → Par Levels</strong>, set a par per ingredient per outlet. They differ by outlet, so a busy branch and a quiet one do not have to carry the same.</p>

<h2>How to choose a number</h2>

<blockquote><p>Par = average daily usage × days between deliveries × 1.3</p></blockquote>

<p>The 1.3 is the safety margin for a busy weekend or a late van. Tighten it for expensive or fast-spoiling items and loosen it for dry goods. Look at a month of usage in the reports before you guess.</p>

<h2>What they do for you</h2>

<ul>
  <li>Items below par are flagged on the stock screen and on the dashboard.</li>
  <li>A purchase request can be prefilled with the quantities needed to bring everything back to par.</li>
</ul>

<h2>Review them twice a year</h2>

<p>Pars set for last year's volumes are how outlets end up over-ordering through a quiet season. Menu changes and seasonal swings both move them.</p>
HTML,
        ],
    ],
];
