<?php

return [
    'slug'    => 'for-administrators',
    'title'   => 'For administrators',
    'summary' => 'Platform administration: companies, subscriptions, raising invoices, and editing this manual.',
    'icon'    => 'shield',
    'sort'    => 120,
    'articles' => [

        [
            'slug'     => 'system-admin-overview',
            'title'    => 'What a System Admin can do',
            'excerpt'  => 'The platform side of Servora, and how it differs from being a Company Admin.',
            'keywords' => 'system admin, platform, super admin, admin dashboard, impersonate',
            'body' => <<<'HTML'
<p>There are two kinds of administrator and they are not the same job.</p>

<ul>
  <li>A <strong>Company Admin</strong> runs one business inside Servora — its users, its settings, its data.</li>
  <li>A <strong>System Admin</strong> runs the Servora platform itself — every company on it, the plans they are on, and the money.</li>
</ul>

<p>System roles are held by the Servora team, not by customers. If you are reading this as a customer, the section you want is <a href="/help/account-and-billing/plans-and-limits">Account &amp; billing</a>.</p>

<h2>The admin area</h2>

<table>
  <thead><tr><th>Screen</th><th>For</th></tr></thead>
  <tbody>
    <tr><td>Users</td><td>Every user across every company.</td></tr>
    <tr><td>Companies</td><td>Every tenant, its plan, its health.</td></tr>
    <tr><td>Role Templates</td><td>The role definitions new companies start from.</td></tr>
    <tr><td>Plans</td><td>What is sold: prices, limits, feature flags.</td></tr>
    <tr><td>Subscriptions</td><td>Who is on what, and in what state.</td></tr>
    <tr><td>Invoices</td><td>The subscription billing ledger.</td></tr>
    <tr><td>Billing Settings</td><td>The seller details printed on invoices.</td></tr>
    <tr><td>Coupons</td><td>Promotional codes.</td></tr>
    <tr><td>Trials</td><td>Trials in flight, and converting them.</td></tr>
    <tr><td>Referrals</td><td>Programmes, commissions, payouts.</td></tr>
    <tr><td>Health</td><td>Which companies are actually using the product.</td></tr>
    <tr><td>Announcements</td><td>Banners shown across the platform.</td></tr>
    <tr><td>Pages</td><td>Marketing CMS pages.</td></tr>
    <tr><td>Documentation</td><td>This help centre.</td></tr>
  </tbody>
</table>

<h2>A system role does not get a tenant's product</h2>

<p>Holding a system role does not hand you somebody's kitchen. The business navigation collapses to the dashboard; the admin screens are your tools. To see a customer's product as they see it, use impersonation from the Companies screen — it is logged, and it is the honest way to reproduce a support question.</p>
HTML,
        ],

        [
            'slug'     => 'manage-subscriptions',
            'title'    => 'Managing companies and subscriptions',
            'excerpt'  => 'Plans, trials, activation and cancellation, and what each state actually does.',
            'keywords' => 'subscription, trial, activate, cancel, past due, grandfathered, plan change',
            'body' => <<<'HTML'
<h2>Subscription states</h2>

<table>
  <thead><tr><th>State</th><th>Means</th></tr></thead>
  <tbody>
    <tr><td>Trialing</td><td>In a trial. Full access until the trial ends.</td></tr>
    <tr><td>Active</td><td>Paid and current.</td></tr>
    <tr><td>Past due</td><td>Payment expected and not received. Still working — chase before you cut off.</td></tr>
    <tr><td>Cancelled</td><td>Ended deliberately.</td></tr>
    <tr><td>Expired</td><td>Ran out and was not renewed.</td></tr>
  </tbody>
</table>

<h2>One live subscription per company</h2>

<p>A company can only have one subscription in a live state at a time. To change what somebody is on, edit the existing one rather than adding a second — a second live row is the fastest way to bill someone twice.</p>

<h2>Extending a trial</h2>

<p>From the Trials screen or the subscription itself. Extending moves both the trial end and the current period end, so the company's own banner agrees with the record.</p>

<h2>Activating</h2>

<p>Activating a subscription starts a paid period from today and sets its end date from the billing cycle. Do it when payment is confirmed, not when it is promised.</p>

<h2>Companies with no subscription</h2>

<p>A company with no subscription row at all is unlimited — grandfathered. That is deliberate for a handful of legacy accounts, and it means deleting a subscription to "fix" something quietly gives that company everything. Cancel it instead.</p>

<h2>Deleted companies</h2>

<p>Companies are soft-deleted, so their subscriptions and invoices outlive them. Admin lists show those rows with the company marked as deleted rather than hiding them — the money still happened.</p>
HTML,
        ],

        [
            'slug'     => 'raise-an-invoice',
            'title'    => 'Raise and manage subscription invoices',
            'excerpt'  => 'The billing ledger: automatic invoices, manual ones, issuing, settling and voiding.',
            'keywords' => 'invoice, billing, raise invoice, draft, issue, void, mark paid, bank transfer',
            'body' => <<<'HTML'
<p><strong>Admin → Invoices</strong> is the subscription billing ledger — every invoice Servora has raised against a customer company.</p>

<figure><img src="/images/docs/admin-invoices.svg" alt="The admin invoices screen showing outstanding, overdue, paid this month and draft totals, a table of invoices with company, plan, dates, total and status, and an explanation of the four invoice states"><figcaption>The ledger, and the four states an invoice can be in.</figcaption></figure>

<h2>Where invoices come from</h2>

<ul>
  <li><strong>Automatically</strong> — a successful card payment raises a paid invoice as soon as the gateway confirms it. Nothing to do.</li>
  <li><strong>By hand</strong> — for a bank transfer, an agreed upgrade, an annual deal, or a credit. Both share one numbering sequence.</li>
</ul>

<h2>Raising one</h2>

<ol>
  <li><strong>New invoice.</strong> Pick the company; its live subscription is offered and prefills the line and the service period from the plan price.</li>
  <li>Adjust the lines. Anything can be billed — a line is a description, a quantity and a unit price.</li>
  <li>Set the invoice date, the due date and the service period.</li>
  <li>Set the tax rate if it applies. The default comes from Billing Settings.</li>
  <li>Save as a draft, or tick <strong>Issue immediately</strong>.</li>
</ol>

<h2>The four states</h2>

<table>
  <thead><tr><th>State</th><th>What it means</th><th>What you can do</th></tr></thead>
  <tbody>
    <tr><td>Draft</td><td>Your working copy. The customer cannot see it.</td><td>Edit, issue, or delete.</td></tr>
    <tr><td>Issued</td><td>Sent. It appears on the customer's billing page.</td><td>Mark paid, or void.</td></tr>
    <tr><td>Paid</td><td>Settled, with a payment recorded against it.</td><td>Download the PDF.</td></tr>
    <tr><td>Void</td><td>Cancelled. Not payable, and not deleted.</td><td>Download the PDF.</td></tr>
  </tbody>
</table>

<h2>Why an issued invoice cannot be edited</h2>

<p>Because the customer is holding a document with those numbers on it. Changing the row would leave two different versions of one invoice number in the world. The correct fix is to void it — with a reason — and raise a replacement.</p>

<h2>Why nothing past draft can be deleted</h2>

<p>An invoice number is a sequence. Deleting one leaves a gap, and a gap is exactly what an audit asks about. Voiding keeps the number and records why it is not payable.</p>

<h2>Recording a payment</h2>

<p><strong>Mark paid</strong> asks for the date, the method and a reference. It writes a completed payment against the company as well as closing the invoice, so the invoice, the payment history and the revenue figures all agree about what was received.</p>

<h2>The numbers at the top</h2>

<p>Outstanding, overdue, paid this month and draft count are deliberately <em>unfiltered</em> — they are the platform's position, not the current search's. A figure that moves when you type in a search box is a figure nobody trusts.</p>

<h2>Seller details</h2>

<p>What prints on the top-left of every invoice — legal name, registration number, tax number, address, bank details — is set under <strong>Admin → Billing Settings</strong>, not in a template. Changing them affects new invoices; ones already raised keep the details they were issued with, because a reissued PDF must not silently change the address on a document already sent.</p>
HTML,
        ],

        [
            'slug'     => 'edit-this-manual',
            'title'    => 'Edit this manual',
            'excerpt'  => 'Every article you are reading is editable in the app. Here is how, and how to add screenshots.',
            'keywords' => 'documentation, help centre, edit docs, write article, screenshot, figure, CMS',
            'body' => <<<'HTML'
<p>The help centre is content, not code. A System Admin edits it at <strong>Admin → Documentation</strong> and the change is live immediately — no deploy, no developer.</p>

<h2>How it is organised</h2>

<ul>
  <li><strong>Sections</strong> are the tiles on the help centre front page. Each has a title, a summary, an icon and a sort order.</li>
  <li><strong>Articles</strong> live in a section. Each has a title, a slug, a summary, search keywords and a body.</li>
</ul>

<p>Article slugs are unique across the whole manual, not per section — so moving an article to a different section does not break a link somebody has bookmarked. The old URL redirects to the new one.</p>

<h2>Writing an article</h2>

<ol>
  <li><strong>+ Article.</strong> Title it as the task, not the screen: "Receive a delivery", not "The GRN screen".</li>
  <li>Write a summary. It is what shows in search results and section lists, and an article without one gets its opening sentence instead.</li>
  <li>Add search keywords — the words a reader types when the article calls it something else. "GRN", "wastage", "SST".</li>
  <li>Write the body in HTML. The toolbar inserts headings, lists, tables and note blocks.</li>
  <li>Use <strong>Preview</strong> before you publish. It renders exactly as the public page will.</li>
</ol>

<h2>Adding a figure</h2>

<ol>
  <li>Save the article first — a figure needs an article to belong to.</li>
  <li>In the <strong>Figures</strong> panel, choose the image file.</li>
  <li>Write the <strong>alt text</strong>. It is required, and it is read aloud to anybody who cannot see the image — describe what the picture shows, not the word "screenshot".</li>
  <li>Add a caption if it earns its place, and <strong>Add figure</strong>.</li>
</ol>

<p>The figure is appended to the end of the body. Move the markup to the step it illustrates — a screenshot of step four belongs at step four, not at the bottom.</p>

<h2>The shipped diagrams</h2>

<p>The figures that came with Servora are SVG diagrams of the interface rather than photographs of it, drawn from the real design tokens. They are generated by <code>scripts/generate-doc-figures.php</code>, so when the UI moves they are re-rendered rather than re-taken. Replace any of them with a real screenshot by uploading one and pointing the article at it — the body is just HTML with an image URL.</p>

<h2>Publishing</h2>

<p>Articles and sections each have a published switch. An unpublished article is invisible to readers and reachable only from the admin list, which is what you want for something half-written. Hiding a whole section hides its articles with it.</p>

<h2>Reordering</h2>

<p>Use the arrows on the article list. Sort orders are spaced by ten, so a new article can be dropped between two existing ones without renumbering the section.</p>

<h2>Reseeding</h2>

<p>Running the docs seeder again adds articles that are missing and <em>leaves existing ones alone</em> — your edits are never overwritten. To restore a shipped article to its original text, delete it and reseed.</p>
HTML,
        ],
    ],
];
