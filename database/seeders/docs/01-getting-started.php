<?php

/**
 * Getting started. Read by people who have never opened the product, so it
 * explains the shape of it before it explains any one screen.
 */

return [
    'slug'    => 'getting-started',
    'title'   => 'Getting started',
    'summary' => 'What Servora is, how it is laid out, and what to do in your first week.',
    'icon'    => 'home',
    'sort'    => 10,
    'articles' => [

        [
            'slug'     => 'what-servora-does',
            'title'    => 'What Servora does',
            'excerpt'  => 'One system for what you buy, what you cook, what you sell and who works the shift — so the food cost number is a fact rather than a guess.',
            'keywords' => 'overview, introduction, what is servora, modules',
            'body' => <<<'HTML'
<p>Servora is operations software for food and beverage businesses. It joins up the four things that decide whether an outlet makes money, and keeps them in one place so they agree with each other:</p>

<ul>
  <li><strong>What you buy</strong> — purchase requests, orders, deliveries, goods received, supplier invoices and credit notes.</li>
  <li><strong>What you cook</strong> — recipes and prep items costed off live ingredient prices, with yield and waste built in.</li>
  <li><strong>What you sell</strong> — sales entered by hand or pulled from your POS, against targets and against the cost of making it.</li>
  <li><strong>Who works the shift</strong> — rosters, attendance, leave, overtime, payroll and training.</li>
</ul>

<h2>Why one system rather than four</h2>

<p>The reason food cost is hard to know is not arithmetic. It is that the price you paid lives on a supplier invoice, the quantity lives in a recipe, and the sales live in a POS — and nothing reconciles the three. Servora's design is that each fact is entered <em>once</em>, at the point somebody already had to touch it, and everything downstream re-costs itself.</p>

<p>Receive a delivery at a higher price and the ingredient's cost changes. Every recipe using it re-costs. The food cost percentage on the dashboard moves. Nobody re-keys anything, and nobody has to remember to.</p>

<figure><img src="/images/docs/dashboard-overview.svg" alt="The Servora dashboard showing food cost percentage, sales month to date, purchases and stock value across the top, an attention list of price and approval alerts below, and a table of recent documents"><figcaption>The dashboard: the four numbers that matter, what needs attention, and what has happened today.</figcaption></figure>

<h2>What it is not</h2>

<p>Servora is not a point-of-sale system and does not take orders from customers. It reads sales from your POS (or you enter the daily totals) and works backwards from there. It is not an accounting ledger either — it produces the documents and the numbers your accountant needs, but it does not replace them.</p>
HTML,
        ],

        [
            'slug'     => 'how-servora-is-laid-out',
            'title'    => 'How Servora is laid out',
            'excerpt'  => 'The sidebar, the workspaces, and why every screen has the same three parts.',
            'keywords' => 'navigation, sidebar, menu, layout, where is',
            'body' => <<<'HTML'
<p>Everything is reached from the sidebar on the left. It is grouped by the job you are doing, not by the database table underneath.</p>

<figure><img src="/images/docs/navigation-map.svg" alt="A map of the Servora sidebar showing eight groups — Procurement, Inventory and Recipes, Labels, Sales, HR, Learning, Business Intelligence, and Settings and Billing — each listing the screens inside it"><figcaption>Every group in the sidebar and what lives inside it.</figcaption></figure>

<h2>You only see what you can use</h2>

<p>A group only appears if your role can open at least one screen inside it. If a colleague describes a menu item you cannot find, you almost certainly do not have that permission rather than a different version of the product. See <a href="/help/getting-started/roles-and-permissions">Roles and permissions</a>.</p>

<h2>Every screen has the same three parts</h2>

<ol>
  <li>A <strong>page header</strong> with the screen's name and its main action on the right.</li>
  <li>A <strong>filter strip</strong> — search, date range, outlet, status.</li>
  <li>A <strong>table or list</strong>, with the row actions on the right of each row.</li>
</ol>

<p>Once you can read one screen you can read all of them. Where a screen breaks the pattern there is a reason, and the guide for that screen says what it is.</p>

<h2>Two workspaces</h2>

<p>Most people only ever see the <strong>outlet workspace</strong> — the teal one described above. If your business runs a central kitchen, users attached to it also get the <strong>Central Kitchen workspace</strong>, which is purple and answers a different question: what are we producing today, rather than what are we selling today. Switch between them from the top of the sidebar. See <a href="/help/central-kitchen/the-kitchen-workspace">The kitchen workspace</a>.</p>

<h2>Outlets</h2>

<p>If your company has more than one outlet, the outlet switcher at the top of the sidebar decides which one you are looking at. Almost every screen is scoped to it. Changing outlets does not change what you are allowed to do — only which outlet's data you are doing it to.</p>
HTML,
        ],

        [
            'slug'     => 'your-first-week',
            'title'    => 'Your first week',
            'excerpt'  => 'The order to set things up in, so nothing you enter has to be entered twice.',
            'keywords' => 'setup, onboarding, first steps, getting set up, implementation',
            'body' => <<<'HTML'
<p>Servora builds on itself: recipes need ingredients, ingredients need suppliers, and reports need all of it. Doing it in this order means nothing gets entered twice.</p>

<h2>Day 1 — the frame</h2>

<ol>
  <li><strong>Company details</strong> (Settings) — legal name, registration number, address, currency, tax type. These print on every document you send a supplier.</li>
  <li><strong>Outlets</strong> — one row per physical location. Add the central kitchen too, if you have one.</li>
  <li><strong>Users</strong> — invite the managers first. Assign each one a role; do not give everybody Company Admin.</li>
</ol>

<h2>Day 2 — what you buy</h2>

<ol>
  <li><strong>Suppliers</strong> — name, contact, email. The email matters: purchase orders can be sent from Servora.</li>
  <li><strong>Product categories</strong> — Produce, Dairy, Dry goods, and so on. Keep it to a dozen; categories are how every cost report groups.</li>
  <li><strong>Ingredients (Market List)</strong> — this is the big one. Each needs a purchase unit, a pack size, a price and a yield. See <a href="/help/ingredients-and-recipes/the-market-list">The Market List</a>.</li>
</ol>

<blockquote><p>You do not have to type ingredients in one at a time. Upload a supplier price list or invoice under <strong>Review Documents</strong> and Servora reads it, matches what it can, and asks you about the rest.</p></blockquote>

<h2>Day 3–4 — what you cook</h2>

<ol>
  <li><strong>Prep items</strong> first — sambal, stocks, sauces, marinades. They are recipes, and other recipes use them.</li>
  <li><strong>Recipes</strong> — your menu. Each line is an ingredient or a prep item with a quantity.</li>
  <li><strong>Selling prices</strong> — set them and Servora shows you the food cost percentage against your target.</li>
</ol>

<h2>Day 5 — the daily habits</h2>

<p>Everything above is set up once. These are what the team does every day, and the system is only as good as they are:</p>

<ul>
  <li>Receive deliveries as they arrive, not at the end of the week.</li>
  <li>Record wastage when it happens.</li>
  <li>Enter or sync sales daily.</li>
  <li>Print labels at prep time, not at service.</li>
</ul>

<h2>Week 2 onwards</h2>

<p>Do a full stock take. Until you have one, your closing stock is an estimate and so is your food cost. After that, look at <strong>Reports</strong> and <strong>AI Analysis</strong> weekly — the second one reads the first and writes what changed.</p>
HTML,
        ],

        [
            'slug'     => 'roles-and-permissions',
            'title'    => 'Roles and permissions',
            'excerpt'  => 'Who can see what, how roles are assigned, and why a menu item might be missing.',
            'keywords' => 'permissions, roles, access, cannot see, missing menu, 403',
            'body' => <<<'HTML'
<p>Every user has a role, and the role decides which screens they can open and which buttons they get on those screens. Roles are per company: the same person can be a Branch Manager at one company and a Chef at another.</p>

<h2>The roles that ship</h2>

<table>
  <thead><tr><th>Role</th><th>Typically</th><th>Can do</th></tr></thead>
  <tbody>
    <tr><td>Company Admin</td><td>Owner, operations director</td><td>Everything in the company, including users, billing and settings.</td></tr>
    <tr><td>Business Manager</td><td>Ops manager across outlets</td><td>All operational screens and reports; not billing.</td></tr>
    <tr><td>Branch Manager</td><td>Outlet manager</td><td>Their outlet: purchasing, inventory, sales, roster, attendance.</td></tr>
    <tr><td>Chef</td><td>Head chef</td><td>Recipes, prep, purchasing requests, labels, wastage.</td></tr>
    <tr><td>Purchaser</td><td>Buyer</td><td>Suppliers, orders, receiving, invoices.</td></tr>
    <tr><td>Staff</td><td>Floor and kitchen team</td><td>Clock in, view roster, training, print labels.</td></tr>
  </tbody>
</table>

<p>Your company may have its own roles on top of these — a System Admin can build them from a role template.</p>

<h2>"My colleague has a menu I don't have"</h2>

<p>That is permissions, not a different version. A sidebar group is hidden entirely when you cannot open anything inside it, which is why the difference looks bigger than one missing link. Ask a Company Admin to check your role under <strong>Settings → Users</strong>.</p>

<h2>Approvals are separate from roles</h2>

<p>Being a manager does not automatically make you an approver. Purchase request approvers, purchase order approvers, overtime approvers and leave approvers are each configured as their own list, under the relevant module's settings. This is deliberate: the person who signs off spend is often not the person who signs off leave.</p>

<h2>Outlet access</h2>

<p>Separately from the role, each user is granted access to specific outlets. A Branch Manager with one outlet sees one; a Business Manager may hold several and switch between them. Some roles are configured to see submissions from every outlet without switching — useful for a head-office approver.</p>
HTML,
        ],

        [
            'slug'     => 'working-across-outlets',
            'title'    => 'Working across outlets',
            'excerpt'  => 'What the outlet switcher changes, what it does not, and how stock moves between locations.',
            'keywords' => 'outlets, branches, multi outlet, switch outlet, locations',
            'body' => <<<'HTML'
<p>An <strong>outlet</strong> is a physical location: a restaurant, a kiosk, a central kitchen. Almost everything in Servora belongs to one.</p>

<h2>The switcher</h2>

<p>The control at the top of the sidebar sets your active outlet. It changes <em>what you are looking at</em> — the orders, the stock, the roster, the sales. It does not change what you are allowed to do; your role travels with you.</p>

<p>You only see outlets you have been given access to. If you need another one, a Company Admin grants it under <strong>Settings → Users</strong>.</p>

<h2>What is shared and what is not</h2>

<table>
  <thead><tr><th>Shared across the company</th><th>Per outlet</th></tr></thead>
  <tbody>
    <tr><td>Ingredients and their costs</td><td>Stock on hand</td></tr>
    <tr><td>Recipes and prep items</td><td>Purchase orders and deliveries</td></tr>
    <tr><td>Suppliers</td><td>Sales records and targets</td></tr>
    <tr><td>Employees and payroll</td><td>Rosters and attendance</td></tr>
    <tr><td>Label templates and shelf-life rules</td><td>Labels actually printed</td></tr>
  </tbody>
</table>

<p>The split follows a simple rule: <em>definitions</em> are company-wide, <em>events</em> are per outlet. A recipe is a definition. Cooking it is an event.</p>

<h2>Moving stock between outlets</h2>

<p>Use <strong>Stock Management → Transfers</strong>. A transfer has two ends and both have to act: the sending outlet creates and sends it, the receiving outlet confirms what actually arrived. Until the second half happens the stock is in transit and belongs to neither — which is exactly what you want a variance report to tell you.</p>

<h2>Companies, not just outlets</h2>

<p>If you operate more than one legal entity, each is a separate <strong>company</strong> with its own outlets, users, subscription and data. Switch companies from the same area of the sidebar. Nothing crosses between them.</p>
HTML,
        ],
    ],
];
