<?php

return [
    'slug'    => 'reports-and-analysis',
    'title'   => 'Reports & analysis',
    'summary' => 'The report library, AI-written commentary, scheduled email reports and audit logs.',
    'icon'    => 'trending-up',
    'sort'    => 90,
    'articles' => [

        [
            'slug'     => 'the-reports-hub',
            'title'    => 'The reports hub',
            'excerpt'  => 'Every report, what each one answers, and how to get it out as a PDF or a spreadsheet.',
            'keywords' => 'reports, export, PDF, CSV, cost report, sales report, analysis',
            'body' => <<<'HTML'
<p><strong>Business Intelligence → Reports</strong> is the library. Every report takes a date range and an outlet, and exports to PDF and CSV.</p>

<figure><img src="/images/docs/reports-hub.svg" alt="The reports hub showing nine report tiles including cost analysis, recipe costing, price history, sales, purchase summary, wastage, labour cost, attendance and stock movement, with a panel about AI Analysis"><figcaption>The report library. Anything here can be scheduled to arrive by email.</figcaption></figure>

<h2>Which report answers which question</h2>

<table>
  <thead><tr><th>Question</th><th>Report</th></tr></thead>
  <tbody>
    <tr><td>Why did food cost move?</td><td>Cost analysis, then Price history</td></tr>
    <tr><td>Which dishes are killing us?</td><td>Recipe costing</td></tr>
    <tr><td>Who are we spending with?</td><td>Purchase summary</td></tr>
    <tr><td>Where is stock disappearing?</td><td>Stock movement, then Wastage</td></tr>
    <tr><td>Is labour in line with trade?</td><td>Labour cost</td></tr>
    <tr><td>What did we actually sell?</td><td>Sales report</td></tr>
    <tr><td>Who worked, and when?</td><td>Attendance</td></tr>
  </tbody>
</table>

<h2>Reading a cost report honestly</h2>

<p>Food cost percentage is <em>cost of goods ÷ sales</em>, and cost of goods needs an opening and a closing stock figure. Without a stock take at each end, the report uses purchases as a proxy — which is fine for a trend and wrong for a single month, because a big delivery on the 30th lands entirely in that month's cost.</p>

<p>If a month looks wildly off, check whether it has a stock take at both ends before you go looking for a problem in the kitchen.</p>

<h2>Scheduling</h2>

<p>Any report can be set to run on a schedule and email itself to a list — the weekly cost report to the operations manager on Monday morning, the month-end pack to the owner. Set it up once under the report's own subscription option.</p>

<h2>Big exports</h2>

<p>Very large PDFs are generated in the background rather than making you wait on a spinning page. You will see progress and a download when it is ready; leaving the screen does not cancel it.</p>
HTML,
        ],

        [
            'slug'     => 'ai-analysis',
            'title'    => 'AI analysis',
            'excerpt'  => 'Servora reads your own numbers and writes what changed, in words the outlet team can act on.',
            'keywords' => 'AI, analysis, insights, weekly review, commentary, what changed',
            'body' => <<<'HTML'
<p>Reports give you numbers. <strong>AI Analysis</strong> reads them and writes the paragraph you would otherwise have to write yourself.</p>

<h2>What it produces</h2>

<p>A short written review of a period: what moved, by how much, and what appears to have caused it — with the figure behind each claim, so it can be checked.</p>

<blockquote><p>"Food cost rose 1.4pt to 33.4% this week. Chicken thigh is the whole of it — up 18% since Tuesday, across 214 kg. At last month's price the week would have closed at 31.9%."</p></blockquote>

<p>It is written for the outlet team, not for a board pack: what happened, what it cost, what to do about it.</p>

<h2>Running one</h2>

<ol>
  <li><strong>Business Intelligence → AI Analysis.</strong></li>
  <li>Choose the period and the outlet.</li>
  <li>Run it. It takes a moment — it is reading real data, not a template.</li>
</ol>

<p>Past analyses are kept, so you can read back through the weeks.</p>

<h2>What it uses and what it does not</h2>

<p>It reads your sales, purchases, costs, wastage and labour for the period you asked about. It does not see other companies' data, and nothing about your business is used to train anything.</p>

<h2>Treat it as a first draft</h2>

<p>It is very good at spotting which number moved and finding the line item behind it. It cannot know that the price rise was a one-off because of a festival, or that the wastage spike was a broken chiller. Read it, check the figures it quotes, and add the context only you have.</p>

<h2>Availability</h2>

<p>AI Analysis is included on some plans and not others. If the menu item is missing, check your plan under <a href="/help/account-and-billing/plans-and-limits">Plans and limits</a>.</p>
HTML,
        ],

        [
            'slug'     => 'audit-logs',
            'title'    => 'Audit logs',
            'excerpt'  => 'Who changed what, and when. The screen you hope never to need.',
            'keywords' => 'audit, log, history, who changed, trail, security',
            'body' => <<<'HTML'
<p><strong>Business Intelligence → Audit Logs</strong> records changes to the records that matter: prices, orders, stock, payroll, users and permissions. Each entry has the user, the time, the record and what changed.</p>

<h2>What it is for</h2>

<ul>
  <li>A price that changed and nobody admits to changing.</li>
  <li>A purchase order somebody says they never approved.</li>
  <li>A stock figure that moved between two reports.</li>
  <li>A permission that got granted.</li>
</ul>

<h2>Using it</h2>

<p>Filter by date, user, or the kind of record. Most questions are answered faster from the record itself — many screens show their own activity history — and the audit log is where you go when you do not yet know which record to look at.</p>

<h2>Who can see it</h2>

<p>Audit access is its own permission and is normally held by Company Admins and senior managers only. It contains enough to reconstruct people's actions, which is exactly why it should not be given out widely.</p>
HTML,
        ],
    ],
];
