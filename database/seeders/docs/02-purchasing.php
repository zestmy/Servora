<?php

return [
    'slug'    => 'purchasing',
    'title'   => 'Purchasing',
    'summary' => 'Requests, orders, deliveries, goods received, supplier invoices and credit notes.',
    'icon'    => 'cart',
    'sort'    => 20,
    'articles' => [

        [
            'slug'     => 'how-a-purchase-moves',
            'title'    => 'How a purchase moves through Servora',
            'excerpt'  => 'Five documents, each filled from the one before it. Which ones you use depends on how your company is set up.',
            'keywords' => 'procurement, PR, PO, DO, GRN, invoice, flow, process',
            'body' => <<<'HTML'
<p>A purchase leaves a paper trail, and each step in Servora creates the next one rather than asking you to retype it.</p>

<figure><img src="/images/docs/purchasing-flow.svg" alt="A five step flow: Purchase Request, Purchase Order, Delivery Order, Goods Received, Invoice — with notes about approval thresholds, short deliveries and price changes"><figcaption>The full chain. Most companies use a shorter version of it.</figcaption></figure>

<h2>The five documents</h2>

<ol>
  <li><strong>Purchase Request (PR)</strong> — what an outlet says it needs. Raised by a chef or a manager.</li>
  <li><strong>Purchase Order (PO)</strong> — what the supplier is actually told to send, at agreed prices.</li>
  <li><strong>Delivery Order (DO)</strong> — what is on the van. Often the supplier's own paperwork.</li>
  <li><strong>Goods Received Note (GRN)</strong> — what actually arrived, in what condition. <em>This is the one that matters.</em></li>
  <li><strong>Invoice</strong> — what you are charged, checked against the GRN.</li>
</ol>

<h2>You probably do not need all five</h2>

<p>Under <strong>Settings → Procurement</strong> your company decides how much of the chain to use:</p>

<ul>
  <li><strong>Require PR approval</strong> — a request cannot become an order until an approver signs it.</li>
  <li><strong>Require PO approval</strong> — an order cannot be sent until an approver signs it.</li>
  <li><strong>Auto-generate DO</strong> — sending a PO creates the delivery order for you.</li>
  <li><strong>Direct supplier order</strong> — a supplier can send a delivery with no PO behind it, for the sort of buying that happens over the phone.</li>
  <li><strong>Ordering mode</strong> — each outlet orders for itself, or a Central Purchasing Unit consolidates and distributes.</li>
</ul>

<p>A small single-outlet operation might use only PO and GRN. A group with a central purchasing unit uses all five.</p>

<h2>Why the GRN is the important one</h2>

<p>The GRN is where reality enters the system. It sets stock on hand, it sets what you actually paid, and it is what the supplier's invoice is checked against. An order that was never received changes nothing; a GRN changes your ingredient costs, and through them every recipe.</p>
HTML,
        ],

        [
            'slug'     => 'raise-a-purchase-request',
            'title'    => 'Raise a purchase request',
            'excerpt'  => 'Tell head office what your outlet needs, without needing to know who supplies it or what it costs.',
            'keywords' => 'PR, purchase request, requisition, order request',
            'body' => <<<'HTML'
<p>A purchase request says <em>what we need</em>. It deliberately does not say who from or at what price — that is the buyer's job, and asking a chef for it is how you end up with three people ordering onions from three suppliers.</p>

<h2>Raising one</h2>

<ol>
  <li>Go to <strong>Procurement → Orders &amp; Requests</strong> and choose <strong>New request</strong>.</li>
  <li>Confirm the outlet and, if you use them, the department.</li>
  <li>Add a line per item: ingredient, quantity, unit. Start typing the ingredient name and pick it from the list.</li>
  <li>Add a note if the request needs context — an event, a promotion, an unusual quantity.</li>
  <li><strong>Submit</strong>. Saving it as a draft leaves it visible only to you.</li>
</ol>

<h2>What happens next</h2>

<p>If your company requires PR approval, the request waits for a PR Approver. Approvers see it on their own <strong>Orders &amp; Requests</strong> screen. If approval is not required, it is available for consolidation straight away.</p>

<p>A buyer then converts approved requests into purchase orders. Requests from several outlets for the same ingredient are merged into one line on one order to that supplier — which is the point of doing it this way rather than each outlet ordering separately.</p>

<h2>Statuses</h2>

<table>
  <thead><tr><th>Status</th><th>Means</th></tr></thead>
  <tbody>
    <tr><td>Draft</td><td>Yours. Nobody else is looking at it.</td></tr>
    <tr><td>Submitted</td><td>Waiting for an approver.</td></tr>
    <tr><td>Approved</td><td>Ready to be turned into an order.</td></tr>
    <tr><td>Converted</td><td>A purchase order has been raised from it.</td></tr>
    <tr><td>Rejected</td><td>Declined. The reason is on the record.</td></tr>
  </tbody>
</table>

<h2>If it is urgent</h2>

<p>Do not raise a second request. Find the first one and ask the approver — a duplicate request becomes a duplicate order becomes a duplicate delivery, and the stock arrives twice.</p>
HTML,
        ],

        [
            'slug'     => 'raise-and-send-a-purchase-order',
            'title'    => 'Raise and send a purchase order',
            'excerpt'  => 'Turn requests into orders, split them by supplier, and email them out as a PDF.',
            'keywords' => 'PO, purchase order, order, supplier order, send order, email PO',
            'body' => <<<'HTML'
<p>A purchase order is the instruction you send a supplier. It carries agreed prices, so it is also the thing an invoice gets checked against.</p>

<h2>Three ways to start one</h2>

<ul>
  <li><strong>From approved requests</strong> — <em>Consolidate</em> gathers approved PRs, groups the lines by supplier and offers one order per supplier. This is the normal route.</li>
  <li><strong>From scratch</strong> — <em>New order</em>, pick the supplier, add lines. For repeat buying nobody needed to request.</li>
  <li><strong>From a form template</strong> — a saved list of what you buy from a given supplier every week, so the order is a matter of filling in quantities.</li>
</ul>

<h2>Multiple suppliers on one list</h2>

<p>If you build an order with items from several suppliers, Servora splits it when you save: one purchase order per supplier, each with its own number. You do not have to sort the list yourself.</p>

<h2>Prices</h2>

<p>Each line's unit cost defaults to the last price you paid that supplier for that item. Change it if you have negotiated something different — the price on the PO is the price the invoice will be checked against.</p>

<h2>Approval and sending</h2>

<p>If your company requires PO approval, the order sits at <em>draft</em> until an approver signs it. Once approved, <strong>Send</strong> does two things:</p>

<ol>
  <li>Emails the order to the supplier's address as a PDF, if one is set up.</li>
  <li>Creates the delivery order automatically, if your company has that switch on.</li>
</ol>

<p>The PDF is also downloadable from the order at any time — useful when a supplier takes orders by WhatsApp.</p>

<h2>Changing an order after it has gone</h2>

<p>Do not edit a sent order to match what turned up. Receive what actually arrived on the GRN — Servora keeps the difference as a shortfall against the order rather than pretending you ordered less. That variance is the number that tells you which suppliers under-deliver.</p>
HTML,
        ],

        [
            'slug'     => 'receive-goods',
            'title'    => 'Receive a delivery',
            'excerpt'  => 'The GRN is the most important document in the system. What to check, and what it changes.',
            'keywords' => 'GRN, goods received, receiving, delivery, short delivery, damaged',
            'body' => <<<'HTML'
<p>Receiving is where the system finds out what really happened. Do it at the door, with the delivery in front of you — not from the invoice a week later.</p>

<h2>Receiving</h2>

<ol>
  <li>Open the delivery order (or the purchase order) and choose <strong>Receive</strong>.</li>
  <li>Go line by line. For each: the quantity that actually arrived, and its condition — <em>good</em>, <em>damaged</em> or <em>rejected</em>.</li>
  <li>If the unit price on the paperwork differs from the order, correct it here. This is the price that becomes your cost.</li>
  <li>Confirm. Servora generates the GRN number.</li>
</ol>

<h2>What this changes, immediately</h2>

<ul>
  <li><strong>Stock on hand</strong> at that outlet goes up by what you received.</li>
  <li><strong>The ingredient's cost</strong> updates if the price moved, and the change is written to its price history with your name on it.</li>
  <li><strong>Every recipe</strong> using that ingredient re-costs.</li>
  <li><strong>A price alert</strong> is raised if the change is bigger than your company's threshold.</li>
</ul>

<figure><img src="/images/docs/market-list.svg" alt="The Market List screen showing ingredients with category, pack size, cost per base unit, supplier and price trend, and a panel explaining that cost per base unit is purchase price divided by pack size divided by yield percent"><figcaption>Receiving at a new price updates the cost per base unit — which is the number everything downstream is costed on.</figcaption></figure>

<h2>Partial and short deliveries</h2>

<p>Receive what came. The order stays open for the balance and can be received again when the rest arrives. Never adjust the ordered quantity to match — the gap between ordered and received is a supplier performance measure, and closing it by hand deletes the evidence.</p>

<h2>Damaged and rejected</h2>

<p>Mark the condition rather than reducing the quantity. Damaged goods you accepted are in your stock and you will probably be credited for them; rejected goods went back on the van. Both need to appear somewhere, and both feed the credit note you are about to chase.</p>

<h2>Who can receive</h2>

<p>Receiving is its own capability, separate from ordering. It is common to let a shift supervisor receive without letting them raise orders — set that under <strong>Settings → Users</strong>.</p>
HTML,
        ],

        [
            'slug'     => 'supplier-invoices',
            'title'    => 'Supplier invoices and AI scanning',
            'excerpt'  => 'Record what you have been charged, let Servora read the invoice for you, and check it against what you received.',
            'keywords' => 'invoice, supplier invoice, AI scan, OCR, invoice matching, three way match',
            'body' => <<<'HTML'
<p>The supplier invoice is the last document in the chain and the one people most often skip. It is worth the five minutes: it is what catches being charged for goods you sent back.</p>

<h2>Two ways in</h2>

<ul>
  <li><strong>From a GRN</strong> — open the goods received note and raise the invoice from it. Every line is prefilled with what you received.</li>
  <li><strong>Scan it</strong> — <strong>Procurement → Invoices → Receive</strong>, then upload a photo or PDF. Servora reads the supplier, the invoice number, the date and the line items, and asks you to confirm.</li>
</ul>

<h2>What the scan gets right, and what to check</h2>

<p>AI extraction is good at printed invoices and reasonable at photographs of them. It is not infallible. Always check three things before you save:</p>

<ol>
  <li>The <strong>supplier</strong> it matched to.</li>
  <li>The <strong>total</strong> — if it matches the paper, the lines almost certainly do too.</li>
  <li>Any line it flagged as <strong>unmatched</strong> — an item it could not tie to one of your ingredients.</li>
</ol>

<p>Where a supplier calls something by a different name than you do, teach it once under <strong>Product Mapping</strong> and it will match from then on.</p>

<h2>Matching against the GRN</h2>

<p>When the invoice is linked to a goods received note, Servora compares them line by line and shows you where they disagree — a quantity billed that was never received, a unit price above what was ordered. This is the check that pays for itself.</p>

<h2>Statuses and payment</h2>

<table>
  <thead><tr><th>Status</th><th>Means</th></tr></thead>
  <tbody>
    <tr><td>Draft</td><td>Captured, not yet confirmed.</td></tr>
    <tr><td>Issued</td><td>Confirmed and payable.</td></tr>
    <tr><td>Paid</td><td>Settled — record part payments as they go and the balance updates.</td></tr>
    <tr><td>Overdue</td><td>Past its due date and still unpaid.</td></tr>
  </tbody>
</table>

<blockquote><p>These are your <em>suppliers'</em> invoices to you. Your own Servora subscription invoices are a different thing entirely, under <a href="/help/account-and-billing/your-invoices">Billing</a>.</p></blockquote>
HTML,
        ],

        [
            'slug'     => 'credit-notes',
            'title'    => 'Credit notes',
            'excerpt'  => 'Getting money back for what was short, damaged or overcharged — and having it show up in the right month.',
            'keywords' => 'credit note, refund, return, short delivery, overcharge, CN',
            'body' => <<<'HTML'
<p>A credit note is the correction to an invoice. Recording it in Servora rather than just deducting it from the next payment is what keeps your cost of goods honest for the month it belongs to.</p>

<h2>When you need one</h2>

<ul>
  <li>Goods you were billed for that never arrived.</li>
  <li>Goods you rejected at the door.</li>
  <li>Damaged stock the supplier has agreed to credit.</li>
  <li>A unit price higher than what was agreed on the order.</li>
</ul>

<h2>Raising one</h2>

<ol>
  <li>Open the invoice and choose <strong>Credit note</strong>, or start one from <strong>Procurement → Orders &amp; Requests</strong>.</li>
  <li>Pick the direction — a credit <em>from</em> the supplier is the normal case.</li>
  <li>Add the lines being credited, with the reason.</li>
  <li>Issue it. Once issued, it is applied against the invoice's outstanding balance.</li>
</ol>

<h2>What it does to the numbers</h2>

<p>An applied credit note reduces the invoice balance and reduces the cost recorded against those ingredients. If the credit is for goods that were physically returned, it also takes them back out of stock — so record the return rather than adjusting the stock count by hand.</p>

<h2>The one that catches people</h2>

<p>A credit agreed on the phone and deducted from the next payment leaves your books showing that you paid full price for goods you did not receive, and your food cost that month is overstated by exactly the amount you were credited. Write the note.</p>
HTML,
        ],

        [
            'slug'     => 'suppliers-and-price-alerts',
            'title'    => 'Suppliers, product mapping and price alerts',
            'excerpt'  => 'Set suppliers up once, teach Servora their product names, and get told when a price moves.',
            'keywords' => 'supplier, vendor, product mapping, aliases, price alert, price increase',
            'body' => <<<'HTML'
<h2>Suppliers</h2>

<p>Under <strong>Procurement → Suppliers</strong>, each supplier needs at minimum a name. Add the email and purchase orders can be sent from Servora; add payment terms and invoices calculate their own due dates.</p>

<p>Suppliers can also be given portal access — their own login where they see the orders you have sent them and can confirm deliveries. That is optional and off by default.</p>

<h2>Product mapping</h2>

<p>Your supplier calls it <em>"CHKN THIGH B/L 10KG CTN"</em>. You call it <em>"Chicken thigh, boneless"</em>. <strong>Product Mapping</strong> is where you tell Servora those are the same thing, once. After that, every scanned invoice and imported price list from that supplier matches automatically.</p>

<p>Mapping is per supplier, because two suppliers use two different codes for the same item — and that is the whole reason the problem exists.</p>

<h2>Form templates</h2>

<p>A form template is a saved order sheet for one supplier: the twenty things you buy from them every week, in the order you walk the store. Ordering becomes filling in quantities rather than searching for items.</p>

<h2>Price alerts</h2>

<p>Set a threshold under <strong>Settings → Procurement</strong> — say 10%. When a delivery is received at a unit price more than that above the last one, Servora raises a price alert. Alerts appear on the dashboard and under <strong>Price Alerts</strong>.</p>

<p>An alert is not a block. The delivery is received and the cost updates either way — the alert exists so that somebody notices before the month-end report tells them.</p>

<blockquote><p>Set the threshold to something you will actually act on. At 2% you will get an alert every week and stop reading them; at 25% you will only hear about disasters.</p></blockquote>

<h2>Price history</h2>

<p>Every cost change is recorded with its date, the old price, the new price and who or what caused it — a delivery, an imported price list, or a manual edit. Open any ingredient and look at its history, or run the <strong>Price history</strong> report across the whole list.</p>
HTML,
        ],
    ],
];
