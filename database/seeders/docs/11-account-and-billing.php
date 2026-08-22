<?php

return [
    'slug'    => 'account-and-billing',
    'title'   => 'Account & billing',
    'summary' => 'Your plan and its limits, invoices, paying, coupons and the referral programme.',
    'icon'    => 'receipt',
    'sort'    => 110,
    'articles' => [

        [
            'slug'     => 'plans-and-limits',
            'title'    => 'Plans and limits',
            'excerpt'  => 'What your plan includes, what happens at a limit, and how to change plan.',
            'keywords' => 'plan, subscription, limits, upgrade, downgrade, trial, features',
            'body' => <<<'HTML'
<p>Your plan decides two things: which features are switched on, and how much of the product you can fill up.</p>

<h2>The limits</h2>

<table>
  <thead><tr><th>Limit</th><th>Counts</th></tr></thead>
  <tbody>
    <tr><td>Outlets</td><td>Active locations.</td></tr>
    <tr><td>Users</td><td>People with a Servora login. Training-portal PIN users do not count.</td></tr>
    <tr><td>Recipes</td><td>Recipes and prep items.</td></tr>
    <tr><td>Ingredients</td><td>Rows in the Market List.</td></tr>
    <tr><td>Training users</td><td>Staff with a training portal PIN.</td></tr>
  </tbody>
</table>

<p><strong>Settings → Billing</strong> shows each of these as a bar: what you are using against what you are allowed.</p>

<h2>What happens when you hit one</h2>

<p>Adding the next one is blocked, with a message saying which limit and what to do. Nothing already in the system stops working, nothing is deleted, and nobody is locked out. A limit stops you growing, not operating.</p>

<h2>Your trial</h2>

<p>A new company starts on a trial with everything switched on. The days remaining show on the billing page and in a banner near the end. When it expires the data stays exactly where it is — subscribe whenever you are ready and it is all still there.</p>

<h2>Changing plan</h2>

<p>Upgrade from <strong>Settings → Billing</strong>; the new limits apply immediately. Downgrading is worth a conversation first: if you are over the new plan's limits, you keep what you have but cannot add more until you are back under.</p>

<h2>Features, not just quantities</h2>

<p>Some modules — AI Analysis is the usual one — are on higher plans only. A menu item that a colleague at another company describes and you cannot find is either a permission or a plan; the billing page tells you which.</p>
HTML,
        ],

        [
            'slug'     => 'your-invoices',
            'title'    => 'Your invoices',
            'excerpt'  => 'Where to find them, what the statuses mean, and how to get the PDF for your accountant.',
            'keywords' => 'invoice, billing, receipt, PDF, payment, statement, tax invoice',
            'body' => <<<'HTML'
<p><strong>Settings → Billing</strong> lists every invoice raised against your company, newest first, each with a PDF you can download.</p>

<h2>What is on the list</h2>

<table>
  <thead><tr><th>Column</th><th>Means</th></tr></thead>
  <tbody>
    <tr><td>Invoice</td><td>The number. Quote it when you pay and when you ask about it.</td></tr>
    <tr><td>Issued</td><td>The invoice date.</td></tr>
    <tr><td>Period</td><td>The service period it covers — the month or year you are paying for.</td></tr>
    <tr><td>Total</td><td>What is payable, including tax.</td></tr>
    <tr><td>Status</td><td>Issued, Paid, Overdue or Void.</td></tr>
  </tbody>
</table>

<h2>The statuses</h2>

<ul>
  <li><strong>Issued</strong> — payable, not yet paid.</li>
  <li><strong>Overdue</strong> — issued, and past its due date.</li>
  <li><strong>Paid</strong> — settled. The PDF shows a zero balance.</li>
  <li><strong>Void</strong> — cancelled and not payable. The number stays on your list so your records have no gaps; if it was replaced, the note says by what.</li>
</ul>

<p>Invoices still being prepared are not shown. You only see one once it has been issued.</p>

<h2>The PDF</h2>

<p>Download it from the row. It carries both parties' registered details, the service period, the line items, any tax, and the payment reference — everything your accountant needs, and nothing they have to ask you for.</p>

<h2>Paying</h2>

<p>Card payments go through the checkout on the billing page and the invoice marks itself paid within a minute or two. For bank transfer, use the details on the invoice PDF and quote the invoice number; it is marked paid once the transfer is confirmed.</p>

<h2>If something looks wrong</h2>

<p>Get in touch with the invoice number rather than editing anything at your end — a wrong invoice is corrected by voiding it and issuing a replacement, so both documents exist and the trail is intact.</p>
HTML,
        ],

        [
            'slug'     => 'coupons-and-referrals',
            'title'    => 'Coupons and Refer & Earn',
            'excerpt'  => 'Redeem a code, or earn commission by introducing someone.',
            'keywords' => 'coupon, promo code, discount, referral, refer a friend, commission, affiliate',
            'body' => <<<'HTML'
<h2>Coupons</h2>

<p>A coupon code grants free access — a number of extra days, or a period on a particular plan. Redeem one under <strong>Settings → Billing</strong>: enter the code and it applies to your subscription immediately.</p>

<p>Codes can be limited by how many times they are used in total, by a date, or to one use per company. If a code is refused, the message says which of those it hit.</p>

<h2>Refer &amp; Earn</h2>

<p>Under <strong>Settings → Refer &amp; Earn</strong> you get a personal referral link. Anyone who signs up through it is tracked to you, and when they become a paying customer you earn a commission at the rate of whichever referral programme is running.</p>

<h2>How a referral is tracked</h2>

<ol>
  <li>Someone follows your link. The referral is recorded on their browser.</li>
  <li>They register a company. The referral attaches to it.</li>
  <li>They subscribe. A commission is raised against your account.</li>
  <li>Commissions are reviewed, approved and paid out.</li>
</ol>

<p>Your dashboard shows each referral's stage — signed up, trialing, paying — and what has been earned, approved and paid.</p>

<h2>The rules worth knowing</h2>

<ul>
  <li>A company that already exists cannot be retro-attributed to a referrer.</li>
  <li>Commission is on real payments; a trial that never converts earns nothing.</li>
  <li>You cannot refer yourself into your own company.</li>
</ul>
HTML,
        ],
    ],
];
