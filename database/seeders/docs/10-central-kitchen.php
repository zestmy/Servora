<?php

return [
    'slug'    => 'central-kitchen',
    'title'   => 'Central Kitchen',
    'summary' => 'The production workspace: orders, yields, transfers to outlets and central purchasing.',
    'icon'    => 'building',
    'sort'    => 100,
    'articles' => [

        [
            'slug'     => 'the-kitchen-workspace',
            'title'    => 'The kitchen workspace',
            'excerpt'  => 'A second workspace for the people who make the food rather than sell it.',
            'keywords' => 'central kitchen, CK, commissary, production, workspace, purple',
            'body' => <<<'HTML'
<p>If your business makes prep centrally and ships it to outlets, the central kitchen is a different kind of operation from a restaurant — and it gets its own workspace.</p>

<figure><img src="/images/docs/central-kitchen.svg" alt="The Central Kitchen production screen in purple, showing orders today, yield against expected and transfers out, a table of production orders with planned and produced quantities, and a four-step flow from production order to outlet receiving"><figcaption>The kitchen workspace. Purple says where you are standing, not that something is wrong.</figcaption></figure>

<h2>Switching in and out</h2>

<p>Users attached to a central kitchen get a workspace switcher at the top of the sidebar. The outlet workspace is teal and asks "what are we selling?"; the kitchen workspace is purple and asks "what are we making?".</p>

<p>The colour is doing a job: it is the fastest way to know which set of numbers you are looking at when both are open in two tabs. It is not a status — nothing is wrong because a screen is purple.</p>

<h2>What is in it</h2>

<ul>
  <li><strong>Production</strong> — production orders and the recipes behind them.</li>
  <li><strong>Inventory</strong> — the kitchen's own stock, and what it needs to buy.</li>
  <li><strong>Operations</strong> — transfers out to outlets.</li>
  <li><strong>Labels</strong> — the same label system; a central kitchen prints more labels than anywhere else.</li>
  <li><strong>Insights</strong> — production history and yield analysis.</li>
</ul>

<h2>Production recipes</h2>

<p>A production recipe is a batch recipe: it makes 40 kg of sambal, not one portion. It has its own categories and its own price classes, because what the kitchen charges an outlet is a different question from what an outlet charges a customer.</p>
HTML,
        ],

        [
            'slug'     => 'production-orders',
            'title'    => 'Production orders',
            'excerpt'  => 'Plan what to make, record what was actually made, and see the yield.',
            'keywords' => 'production order, batch, make, produce, yield, production log',
            'body' => <<<'HTML'
<h2>The four steps</h2>

<ol>
  <li><strong>Production order</strong> — what to make, how much, by when, and which outlet it is for.</li>
  <li><strong>Production log</strong> — what was actually made, and what it consumed.</li>
  <li><strong>Stock transfer</strong> — sent to the outlet.</li>
  <li><strong>Outlet receives</strong> — the stock lands and the cost follows it.</li>
</ol>

<h2>Raising an order</h2>

<ol>
  <li><strong>Production → New production order.</strong></li>
  <li>Set the production date and when it is needed by.</li>
  <li>Add a line per recipe: what to make, how much, and the destination outlet.</li>
  <li>Save. If your kitchen requires approval, it waits for a kitchen manager.</li>
</ol>

<p>Orders can also come from the outlets — an outlet raises a prep request and the kitchen turns approved requests into production orders.</p>

<h2>Executing</h2>

<p>When the batch is made, record it against the order: the quantity actually produced, and the ingredients actually consumed. Both are asked for, because the difference between them is the yield, and yield is the only reason to track any of this.</p>

<h2>Yield analysis</h2>

<p>A 40 kg batch of sambal that produced 38.6 kg ran at 96.5%. One batch means nothing; the same recipe at 96% for three months and 88% last week means something, and <strong>Insights → Yield Analysis</strong> is where that shows up.</p>

<p>Sustained low yield is usually one of three things: the recipe's stated yield is optimistic, the ingredients changed, or the method drifted. All three are worth finding.</p>

<h2>Costing</h2>

<p>The cost of a batch is what it consumed, at today's ingredient prices, divided by what it produced. That per-kilo cost is what transfers to the outlet — so an outlet's food cost includes the kitchen's real cost, not a standard price somebody set last year.</p>
HTML,
        ],

        [
            'slug'     => 'central-purchasing',
            'title'    => 'Central purchasing',
            'excerpt'  => 'One buyer, one order per supplier, distributed to the outlets that asked.',
            'keywords' => 'CPU, central purchasing, consolidate, group buying, distribution',
            'body' => <<<'HTML'
<p>Central purchasing mode changes who orders. Instead of each outlet ordering for itself, outlets raise requests and a central purchasing unit consolidates them.</p>

<h2>How it runs</h2>

<ol>
  <li>Outlets raise purchase requests as normal.</li>
  <li>The buyer opens <strong>Consolidate</strong>, selects approved requests, and Servora groups the lines by supplier — merging the same ingredient across outlets into one line.</li>
  <li>One purchase order per supplier goes out.</li>
  <li>Goods are received centrally.</li>
  <li>Stock is transferred out to the outlets that asked for it.</li>
</ol>

<h2>Why bother</h2>

<p>Volume. Four outlets each ordering 20 kg of onions get four small-order prices; one order for 80 kg gets one better price. It also means one relationship per supplier instead of four, and one place where a price rise is noticed.</p>

<h2>Turning it on</h2>

<p>It is a company-level setting under <strong>Settings → Procurement</strong> — ordering mode, outlet or CPU. Switch it deliberately: it changes who raises orders, and outlet managers need to know that their request now goes to a buyer rather than a supplier.</p>

<h2>Keeping the outlets honest</h2>

<p>Consolidated buying only works if outlets request what they actually need. Par levels help — a request generated from par is a calculation, and a request typed from memory on a Friday afternoon is not.</p>
HTML,
        ],
    ],
];
