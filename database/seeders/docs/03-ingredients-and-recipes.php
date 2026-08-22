<?php

return [
    'slug'    => 'ingredients-and-recipes',
    'title'   => 'Ingredients & recipes',
    'summary' => 'The Market List, cost per unit, yield, recipe costing, prep items and menu pricing.',
    'icon'    => 'cube',
    'sort'    => 30,
    'articles' => [

        [
            'slug'     => 'the-market-list',
            'title'    => 'The Market List',
            'excerpt'  => 'Every ingredient you buy, what it costs, and the four fields that decide whether your recipe costs are right.',
            'keywords' => 'ingredients, market list, items, products, cost, pack size',
            'body' => <<<'HTML'
<p>The Market List is every ingredient your business buys. It is the foundation of every cost in Servora, which means the four fields below are worth getting right once rather than fixing later across two hundred recipes.</p>

<figure><img src="/images/docs/market-list.svg" alt="The Market List showing ingredients with their category, pack size, cost per base unit, supplier and price trend, and a breakdown of how cost per base unit is calculated"><figcaption>The Market List, and the arithmetic behind the cost column.</figcaption></figure>

<h2>The four fields that matter</h2>

<table>
  <thead><tr><th>Field</th><th>What it is</th><th>Example</th></tr></thead>
  <tbody>
    <tr><td><strong>Base unit</strong></td><td>The unit you buy and count in.</td><td>kg</td></tr>
    <tr><td><strong>Pack size</strong></td><td>How much of that unit is in one purchase pack.</td><td>10 (a 10 kg carton)</td></tr>
    <tr><td><strong>Purchase price</strong></td><td>What the supplier charges for one pack.</td><td>RM 148.00</td></tr>
    <tr><td><strong>Yield %</strong></td><td>How much survives trimming, thawing and peeling.</td><td>92%</td></tr>
  </tbody>
</table>

<p>From those, Servora computes the number everything else uses:</p>

<blockquote><p><strong>Cost per base unit</strong> = purchase price ÷ pack size ÷ (yield ÷ 100)</p><p>RM 148.00 ÷ 10 kg ÷ 0.92 = <strong>RM 16.09 per usable kg</strong></p></blockquote>

<h2>Why yield is not optional</h2>

<p>You paid RM 14.80 a kilo. You can only cook with RM 16.09 of it, because 8% went in the bin as trim. A recipe costed at RM 14.80 is understated by 8% on every portion, forever, and nothing downstream will ever tell you. Yield is the single field most often left at 100% and most often wrong.</p>

<p>If you do not know a yield, weigh one delivery before and after prep. Do it once per ingredient and never again.</p>

<h2>Recipe unit</h2>

<p>You buy in kilos and cook in grams. Set the recipe unit and Servora converts, including the odd ones — a case of 12 × 1 L, a tin measured by weight but used by volume. Where the conversion is not standard, add it on the ingredient itself.</p>

<h2>Keeping prices current</h2>

<p>You should almost never edit a price by hand. It updates itself when you <a href="/help/purchasing/receive-goods">receive a delivery</a>, or when you import a supplier price list under <strong>Review Documents</strong>. Both leave a dated record; a manual edit is a number with no story behind it.</p>

<h2>Categories</h2>

<p>Every ingredient belongs to a product category, and categories are how every cost report groups. Keep to around a dozen — Produce, Poultry, Seafood, Meat, Dairy, Dry goods, Beverage, Packaging, Cleaning. Forty categories make a report nobody reads.</p>
HTML,
        ],

        [
            'slug'     => 'cost-a-recipe',
            'title'    => 'Cost a recipe',
            'excerpt'  => 'Build a dish from ingredients and prep items, see its food cost, and price it against a target.',
            'keywords' => 'recipe, costing, food cost, margin, menu price, gross profit',
            'body' => <<<'HTML'
<p>A recipe is a list of ingredients with quantities. Servora prices each line at today's cost and adds them up — so a recipe costed once stays costed.</p>

<figure><img src="/images/docs/recipe-costing.svg" alt="A recipe costing screen showing ingredient lines with quantities and costs totalling RM 7.39, alongside a selling price panel showing menu price RM 22.90, food cost 32.3 percent against a 32 percent target, and gross margin RM 15.51"><figcaption>A costed recipe: the lines on the left, what it means for the menu price on the right.</figcaption></figure>

<h2>Building one</h2>

<ol>
  <li><strong>Recipes → New recipe.</strong> Give it a name, a category and a yield — how many portions one batch makes.</li>
  <li>Add a line per component. Ingredients and prep items both appear in the same picker.</li>
  <li>Enter the quantity in whatever unit suits the line — 180 g, 60 ml, 1 each.</li>
  <li>Add a waste percentage on any line that is trimmed <em>during cooking</em> rather than at prep.</li>
</ol>

<h2>Reading the cost</h2>

<p>The total is the food cost of one portion at today's ingredient prices. Set a selling price and Servora shows:</p>

<ul>
  <li><strong>Food cost %</strong> — cost ÷ selling price. Most restaurant targets sit between 28% and 35%.</li>
  <li><strong>Gross margin</strong> — what is left before labour and rent.</li>
  <li>Both against your target, so you can see at a glance which dishes are drifting.</li>
</ul>

<h2>The cost moves on its own</h2>

<p>This is the point of the whole exercise. When chicken goes up 18%, you do not re-cost anything: every recipe containing chicken shows a higher cost the moment the delivery is received, and the dishes that broke their target appear in the cost report.</p>

<h2>Extra costs</h2>

<p>A recipe can carry costs that are not ingredients — packaging for a delivery item, a portion of gas, a service charge. Add them as extra cost lines so the food cost percentage is honest about what the dish actually costs to put out.</p>

<h2>Photos and method</h2>

<p>A recipe can hold photographs and step-by-step method, and export as an SOP PDF for the kitchen wall — the same document the kitchen cooks from and the training module can point at. That way the costed recipe and the cooked recipe are the same recipe.</p>
HTML,
        ],

        [
            'slug'     => 'prep-items',
            'title'    => 'Prep items',
            'excerpt'  => 'Sambal, stocks, sauces and marinades are recipes that behave like ingredients.',
            'keywords' => 'prep, sub recipe, batch, sauce, stock, mise en place, semi finished',
            'body' => <<<'HTML'
<p>A prep item is something you make in order to make something else. It is a recipe — it has ingredients and a cost — but other recipes consume it the way they consume an ingredient.</p>

<h2>Why they matter</h2>

<p>Half the cost of a menu is usually hidden in prep. If sambal is entered as a flat "RM 0.60 per portion" guess, then every dish using sambal is guessed, and chilli going up 40% changes nothing on any report. Make it a prep item and one batch re-costs six dishes.</p>

<h2>Creating one</h2>

<ol>
  <li>Create a recipe as normal, and mark it as a <strong>prep item</strong>.</li>
  <li>Set the batch yield — how much one batch produces, in the unit other recipes will use it in (2.5 kg of sambal, 20 L of stock).</li>
  <li>Save. Servora creates a matching entry in your ingredient list automatically.</li>
</ol>

<p>From then on, "Sambal" appears in the ingredient picker of every other recipe, priced per gram off its own batch cost.</p>

<h2>Prep inside prep</h2>

<p>A prep item can contain another prep item — a curry base inside a curry, a stock inside a sauce inside a dish. Costs chain all the way down. Changing the price of one onion moves the final dish by a fraction of a sen, correctly.</p>

<h2>Prep and stock</h2>

<p>Prep items are counted in stock takes like anything else, because a 20 L stockpot in the walk-in is real inventory with real money in it. If a central kitchen makes your prep, see <a href="/help/central-kitchen/production-orders">Production orders</a> — the batch is recorded there and transferred to the outlet.</p>

<h2>Labels</h2>

<p>Prep is also what the label printer is for. Every batch gets a label with what it is, when it was made and when it must be used by — see <a href="/help/labels/print-a-label">Print a label</a>.</p>
HTML,
        ],

        [
            'slug'     => 'price-classes',
            'title'    => 'Price classes',
            'excerpt'  => 'One dish, several prices — dine-in, takeaway, delivery platform — each with its own margin.',
            'keywords' => 'price class, pricing, delivery, grab, foodpanda, takeaway, commission',
            'body' => <<<'HTML'
<p>The same nasi lemak sells at three prices: RM 22.90 in the dining room, RM 21.50 for takeaway, RM 27.02 on a delivery platform that takes 30%. All three have different margins, and only one of them is on your menu.</p>

<h2>How they work</h2>

<p>Define your price classes once under <strong>Settings → Price Classes</strong> — typically Dine-in, Takeaway, and one per delivery platform. Each recipe can then carry a price for each class, and Servora shows the food cost percentage and margin for each.</p>

<h2>What this shows you</h2>

<p>Usually something uncomfortable. A dish at 31% food cost dine-in can be at 44% on a platform after commission, and the volume that platform brings is losing money on precisely the dishes the platform promotes.</p>

<p>Price classes are how you find that out before the quarter ends rather than after.</p>

<h2>Setting a class price</h2>

<p>Open a recipe, go to its pricing section, and enter a price per class. You can also set a class to be a percentage uplift on the base price — useful for a platform where you mark everything up by the same amount, and it keeps itself in step when the base price changes.</p>
HTML,
        ],
    ],
];
