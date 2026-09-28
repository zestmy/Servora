<?php

namespace App\Support\Marketing;

/**
 * Every visible string on the marketing home page, by key, in English.
 *
 * The home view reads its words from here (through $t in the view), and a
 * country landing page (App\Models\LandingPage) overrides them key by key,
 * so the one design serves every language. Admin › Country Pages lists these
 * keys grouped by SECTIONS, with the English beside each box.
 *
 * Placeholders are replaced at render and must survive translation:
 *   :days  trial length           :basic / :full  suite price per outlet
 *   :addon cheapest add-on        :hr / :ck       HR per employee, per kitchen
 *   :price :target                recipe demo figures
 * Translations should keep `&` and figures as they are.
 *
 * The product screens drawn on the page (dashboard, invoice, phone) stay in
 * English on purpose: they show the product, and the product is in English.
 *
 * Adding a string to the view means adding its key here — HomeCopyTest fails
 * otherwise, because a key missing here renders as the key itself.
 */
final class HomeCopy
{
    public const SECTIONS = [
        'meta'    => 'Page title and search description',
        'nav'     => 'Top navigation and footer',
        'hero'    => 'Hero',
        'chip'    => 'Hero module chips',
        'cta'     => 'Buttons used across the page',
        'ticker'  => 'Market price strip',
        'types'   => 'Business types strip',
        'facts'   => 'Numbers band',
        'cost'    => 'Live recipe costing',
        'ai'      => 'AI invoice capture',
        'buy'     => 'Purchasing flow',
        'review'  => 'Weekly review',
        'floor'   => 'On the kitchen floor',
        'modules' => 'Module grid',
        'tag'     => 'Module plan tags',
        'steps'   => 'Getting started',
        'quotes'  => 'Testimonials',
        'plans'   => 'Pricing teaser',
        'faq'     => 'Questions',
        'close'   => 'Closing section',
    ];

    /** @return array<string, string> key => English */
    public static function defaults(): array
    {
        return [
            // ── meta ────────────────────────────────────────────────────────
            'meta.title'       => 'F&B Management Platform',
            'meta.description' => 'Servora is AI-powered restaurant operations software for F&B: AI reads your supplier invoices and reviews your numbers weekly, alongside recipe costing, purchasing, inventory and staff training.',

            // ── nav ─────────────────────────────────────────────────────────
            'nav.features' => 'Features',
            'nav.pricing'  => 'Pricing',
            'nav.tools'    => 'Free Tools',
            'nav.help'     => 'Help',
            'nav.refer'    => 'Refer & Earn',
            'nav.login'    => 'Log In',
            'nav.cta'      => 'Start Free Trial',
            'nav.tagline'  => 'Costing, purchasing, inventory and training for F&B operators who need to know their numbers before month end.',
            'nav.english'  => 'View in English',

            // ── hero ────────────────────────────────────────────────────────
            'hero.pill_long'    => 'New: per-outlet plans, and Free for one outlet, forever',
            'hero.pill_short'   => 'New: Free for one outlet, forever',
            'hero.title'        => 'Know your food cost',
            'hero.title_accent' => 'before month end',
            'hero.lead'         => "AI reads your supplier invoices, every recipe re-costs itself as prices move, and your week is written up for Monday's meeting. Purchasing, stock, labels and payroll all run on the same numbers.",
            'hero.see'          => 'See it work',
            'hero.point_1'      => 'No card to start',
            'hero.point_2'      => 'Free plan for one outlet',
            'hero.point_3'      => 'Live in an afternoon',

            'chip.costing'    => 'Costing',
            'chip.purchasing' => 'Purchasing',
            'chip.inventory'  => 'Inventory',
            'chip.labels'     => 'Labels',
            'chip.hr'         => 'HR & Payroll',
            'chip.audits'     => 'Audits',

            'cta.trial'   => 'Start :days-day free trial',
            'cta.pricing' => 'View pricing',

            // ── ticker / types / facts ─────────────────────────────────────
            'ticker.label' => 'Market',
            'ticker.note'  => 'Median prices paid by kitchens running on Servora · updated hourly · suppliers not identified',
            'ticker.link'  => 'cost a recipe with these prices',

            'types.heading'     => 'Built for every kind of F&B operation',
            'types.restaurants' => 'Restaurants',
            'types.cafes'       => 'Cafes',
            'types.cloud'       => 'Cloud kitchens',
            'types.catering'    => 'Catering',
            'types.bakeries'    => 'Bakeries',
            'types.bars'        => 'Bars & pubs',
            'types.food_courts' => 'Food courts',
            'types.hotels'      => 'Hotels',
            'types.central'     => 'Central kitchens',
            'types.franchises'  => 'Franchises',

            'facts.areas'      => 'areas of the operation, on one set of numbers',
            'facts.days'       => 'days',
            'facts.trial'      => 'of the whole product on the trial, add-ons included',
            'facts.scans'      => 'AI invoice scans per outlet a month, on Basic',
            'facts.free'       => 'for one outlet on the Free plan, for as long as you like',

            // ── cost ────────────────────────────────────────────────────────
            'cost.kicker'         => 'Live recipe costing',
            'cost.title'          => 'One price moves.',
            'cost.title_accent'   => 'Every plate knows.',
            'cost.lead'           => "Build a recipe once. When a supplier's price changes on an invoice, the cost of every dish that uses it changes with it, and anything that drifts over target is flagged. Drag the chicken price and watch.",
            'cost.selling'        => 'Selling price :price · target food cost :target%',
            'cost.within'         => 'Within target',
            'cost.over'           => 'Over target',
            'cost.col_ingredient' => 'Ingredient',
            'cost.col_qty'        => 'Qty',
            'cost.col_cost'       => 'Cost',
            'cost.live'           => 'live',
            'cost.packaging'      => 'packaging',
            'cost.serving'        => 'Cost per serving',
            'cost.slider'         => 'Chicken thigh, per kg',
            'cost.food_cost'      => 'Food cost',
            'cost.scale'          => 'Target :target% · scale to 40%',
            'cost.impact'         => 'At 1,500 plates a month, that price move is',
            'cost.impact_after'   => 'of margin a month, on this one dish.',
            'cost.hold'           => 'To get back to :target%, the menu price would need to be',
            'cost.headroom'       => 'of headroom per plate before it crosses :target%.',
            'cost.note'           => 'An illustration with sample figures. In Servora the prices come from your own invoices.',

            // ── ai ──────────────────────────────────────────────────────────
            'ai.kicker'       => 'AI document capture',
            'ai.title'        => 'Photograph the invoice.',
            'ai.title_accent' => 'The lines walk in.',
            'ai.lead'         => 'Typing is the reason costing goes stale. Snap the delivery invoice on a phone and the supplier, items, quantities and prices are read off the page, matched to your ingredients, and held for a quick review before anything lands.',
            'ai.1_title'      => 'Photo or PDF, invoices and delivery orders',
            'ai.1_body'       => 'Z-reports from the till are read the same way.',
            'ai.2_title'      => 'Matched to your ingredients, UOM corrected',
            'ai.2_body'       => 'Nothing imports blind: every line waits in a review queue.',
            'ai.3_title'      => 'Price moves caught at the door',
            'ai.3_body'       => 'You see the increase before it reaches a single costing.',

            // ── buy ─────────────────────────────────────────────────────────
            'buy.kicker'       => 'Purchasing, end to end',
            'buy.title'        => 'Request to invoice.',
            'buy.title_accent' => 'Nothing lost between.',
            'buy.lead'         => "Every order is followed from the kitchen's request to the supplier's bill, and the invoice is checked against what was ordered and what actually arrived.",
            'buy.step_1'       => 'Request',
            'buy.step_1_sub'   => 'Kitchen asks',
            'buy.step_2'       => 'Approve',
            'buy.step_2_sub'   => 'Manager signs off',
            'buy.step_3'       => 'Order',
            'buy.step_3_sub'   => 'PO emailed to supplier',
            'buy.step_4'       => 'Deliver',
            'buy.step_4_sub'   => 'Delivery order',
            'buy.step_5'       => 'Receive',
            'buy.step_5_sub'   => 'GRN, costs updated',
            'buy.step_6'       => 'Invoice',
            'buy.step_6_sub'   => 'Three-way matched',
            'buy.match'        => 'Three-way match',
            'buy.doc_1'        => 'Purchase order',
            'buy.doc_2'        => 'Goods received',
            'buy.doc_3'        => 'Supplier invoice',
            'buy.1_title'      => 'Par levels write the order',
            'buy.1_body'       => 'Stock below par pre-fills the next PO, per ingredient, per outlet.',
            'buy.2_title'      => 'One order, several suppliers',
            'buy.2_body'       => 'Build one list and Servora splits it into a PO for each supplier.',
            'buy.3_title'      => 'Central purchasing',
            'buy.3_body'       => 'Approved requests from every outlet, consolidated by supplier.',

            // ── review ──────────────────────────────────────────────────────
            'review.kicker'       => 'Reports and AI insights',
            'review.title'        => 'Your week,',
            'review.title_accent' => 'written up',
            'review.title_end'    => 'for Monday.',
            'review.lead'         => 'The weekly WIP review puts the week just closed against the one before: sales, cost of goods by department, wastage, transfers, overtime and labour cost. Scroll it, present it full-screen as a slide deck, or send the PDF.',
            'review.point_1'      => 'A plain-English review of what moved, and two or three things to do about it',
            'review.point_2'      => 'Cost summary, COGS and month-to-date comparisons on every outlet',
            'review.point_3'      => 'Reports scheduled to your inbox, exported to Excel, PDF or CSV',

            // ── floor ───────────────────────────────────────────────────────
            'floor.kicker'        => 'On the kitchen floor',
            'floor.title'         => 'Built for wet hands',
            'floor.title_and'     => 'and',
            'floor.title_accent'  => 'busy passes',
            'floor.lead'          => 'Staff get phone apps on your own subdomain, each behind a personal PIN, with touch targets big enough for wet and gloved hands.',
            'floor.included'      => 'included in Full',
            'floor.labels_tab'    => 'Labels',
            'floor.labels_title'  => 'Date labels at the bench',
            'floor.labels_body'   => 'Pick the item, print the label. Shelf life is worked out from your own rules, prepared-by is captured, and every label that came off the printer is logged.',
            'floor.labels_addon'  => 'Food Safety Labels',
            'floor.clock_tab'     => 'Clock-in',
            'floor.clock_title'   => 'Clock-in nobody can do for a friend',
            'floor.clock_body'    => 'The outlet kiosk shows a QR code that changes on its own, every 30 seconds by default. Staff scan it with their own phone and their own PIN, so nobody clocks in for a friend.',
            'floor.clock_addon'   => 'HR & Payroll',
            'floor.audits_tab'    => 'Audits',
            'floor.audits_title'  => 'Audits that end in a fix',
            'floor.audits_body'   => 'Scored checklists on a phone, Pass, Conditional pass or Fail, and every non-conformance becomes a corrective action with an owner, a photo of the fix and a re-audit date.',
            'floor.audits_addon'  => 'Audits & Compliance',
            'floor.learn_tab'     => 'Learn SOP',
            'floor.learn_title'   => 'Every outlet makes it the same way',
            'floor.learn_body'    => 'SOPs with the method, plating photos and video, plus courses, quizzes, a leaderboard and certificates. Staff open it from the staff portal with their PIN.',
            'floor.learn_addon'   => 'Learn SOP',

            // ── modules ─────────────────────────────────────────────────────
            'modules.kicker'       => 'One platform',
            'modules.title'        => 'Every part of the operation,',
            'modules.title_accent' => 'one set of numbers',
            'modules.lead'         => 'Start with costing on the Free plan. Add purchasing and inventory with Basic, then only the add-ons you actually run, or take Full and get them all.',
            'modules.cta'          => 'Everything in each module',
            'modules.costing'          => 'Ingredients and recipe costing',
            'modules.costing_desc'     => 'Build a recipe once and watch its cost, yield and food-cost percentage update as ingredient prices move.',
            'modules.ai'               => 'AI document capture',
            'modules.ai_desc'          => 'Photograph a supplier invoice and the lines walk themselves in, matched to your ingredients, with a review step before anything lands.',
            'modules.purchasing'       => 'Purchasing and receiving',
            'modules.purchasing_desc'  => 'Request, approve, order, receive, then match the invoice against the order and the GRN.',
            'modules.inventory'        => 'Inventory and stock',
            'modules.inventory_desc'   => 'Stock takes, wastage, staff meals, prep items, par levels and transfers between outlets.',
            'modules.kitchen'          => 'Central kitchen',
            'modules.kitchen_desc'     => 'Plan batch production against your outlets, then log what it actually yielded.',
            'modules.labels'           => 'Food safety labelling',
            'modules.labels_desc'      => 'HACCP date labels printed at the bench, shelf life worked out for you, and every label that came off the printer logged.',
            'modules.sales'            => 'Sales and POS Sync',
            'modules.sales_desc'       => 'Daily takings by meal period and Z-report capture, or let POS Sync bring the sales in from the till on its own.',
            'modules.reports'          => 'Reports and analytics',
            'modules.reports_desc'     => 'The weekly WIP review as a slide deck for the meeting, monthly cost summaries, COGS, labour cost, and exports your accountant takes without rework.',
            'modules.hr'               => 'HR and payroll',
            'modules.hr_desc'          => 'Roster, QR clock-in, attendance, leave, OT claims, service charge, payroll, payslips and EA forms.',
            'modules.learn'            => 'Learn SOP',
            'modules.learn_desc'       => 'SOPs, plating photos and video, with courses, quizzes and certificates, opened by QR on any phone.',
            'modules.audits'           => 'Audits and compliance',
            'modules.audits_desc'      => 'Scored outlet audits on a phone, with corrective actions tracked to an owner and a re-audit date.',
            'modules.assets'           => 'Assets',
            'modules.assets_desc'      => 'A register of smallwares and equipment, counted, received and disposed of like stock.',
            'modules.control'          => 'Multi-outlet and control',
            'modules.control_desc'     => 'Shared data across sites, role-based access per company, and an audit log of who changed what.',
            'modules.suppliers'        => 'Supplier portal',
            'modules.suppliers_desc'   => 'Suppliers sign in to acknowledge the orders you send and see their own invoices.',

            'tag.free'         => 'Free',
            'tag.basic'        => 'Basic',
            'tag.per_kitchen'  => 'Per kitchen',
            'tag.addon'        => 'Add-on',
            'tag.basic_addon'  => 'Basic · add-on',
            'tag.per_employee' => 'Per employee',
            'tag.every_plan'   => 'Every plan',

            // ── steps ───────────────────────────────────────────────────────
            'steps.kicker'       => 'Getting started',
            'steps.title'        => 'Live in',
            'steps.title_accent' => 'an afternoon',
            'steps.lead'         => 'No implementation project, no consultant. Most operators cost their first recipes the day they sign up.',
            'steps.1_title'      => 'Create your account',
            'steps.1_body'       => 'Company name and email. No card, no sales call.',
            'steps.2_title'      => 'Load ingredients and recipes',
            'steps.2_body'       => 'Import a supplier price list or add items as you go. Costs calculate the moment an ingredient has a price.',
            'steps.3_title'      => 'Work the month normally',
            'steps.3_body'       => 'Raise POs, receive deliveries, record sales and stock takes. The reports build themselves from what you already do.',

            // ── quotes ──────────────────────────────────────────────────────
            'quotes.kicker'       => 'From operators',
            'quotes.title'        => 'What operators say',
            'quotes.title_accent' => 'after a quarter',
            'quotes.1'            => 'Servora helped us cut food costs by 12% in 3 months. The recipe costing alone is worth it.',
            'quotes.1_role'       => 'Restaurant Owner',
            'quotes.2'            => 'Finally, a system that understands F&B operations. The PO to GRN flow saved us hours every week.',
            'quotes.2_role'       => 'Operations Manager',
            'quotes.3'            => 'The LMS module transformed our staff training. New hires get up to speed in half the time.',
            'quotes.3_role'       => 'F&B Group Director',

            // ── plans ───────────────────────────────────────────────────────
            'plans.kicker'       => 'Pricing',
            'plans.title'        => 'Free to start.',
            'plans.title_accent' => 'Per outlet',
            'plans.title_end'    => 'as you grow.',
            'plans.lead'         => 'Every sign-up gets the whole product for :days days, then keeps Free for as long as it likes.',
            'plans.everything'   => 'Everything',
            'plans.forever'      => 'forever',
            'plans.per_outlet'   => '/ outlet / month',
            'plans.start_free'   => 'Start free',
            'plans.try'          => 'Try it free for :days days',
            'plans.free_desc'    => 'Recipe costing for one outlet.',
            'plans.basic_desc'   => 'Per outlet: costing, purchasing, inventory, sales and reports.',
            'plans.full_desc'    => 'Per outlet: Basic plus Labels, Learn SOP, Audits, Assets, POS Sync and AI Insights.',
            'plans.addons'       => 'Add-ons from :addon a month per company. HR & Payroll :hr per employee.',
            'plans.work_out'     => 'Work out your price',
            'plans.fx_note'      => "Prices in :currency. You pay the equivalent in Malaysian ringgit, at Bank Negara Malaysia's rate on the day.",

            // ── faq ─────────────────────────────────────────────────────────
            'faq.kicker'       => 'FAQ',
            'faq.title'        => 'Questions,',
            'faq.title_accent' => 'answered',
            'faq.lead'         => 'Anything else is in the Help Centre, which is public too.',
            'faq.help'         => 'Open the Help Centre',
            'faq.1_q' => 'Is there really a free plan?',
            'faq.1_a' => 'Yes. Free keeps recipe costing, prep items, stock counts, daily sales and food-cost reports for one outlet, with up to 150 market list items, 30 recipes and 2 users. It does not expire.',
            'faq.2_q' => 'What happens when my trial ends?',
            'faq.2_a' => 'The :days-day trial is the Full suite with every add-on. When it ends you move to Free: nothing is deleted, and paid modules lock until you choose a plan.',
            'faq.3_q' => 'How is it priced?',
            'faq.3_a' => 'Per outlet, per month: Basic :basic and Full :full. Add-ons are one flat price per company, HR & Payroll is :hr per employee, and a central kitchen :ck each. Yearly billing is two months free.',
            'faq.4_q' => 'Does it work with my POS?',
            'faq.4_a' => 'Every paid plan takes sales by hand, by CSV import or from a photo of the Z-report. The POS Sync add-on brings them in automatically through a small agent on the outlet PC.',
            'faq.5_q' => 'Do I need to install anything?',
            'faq.5_a' => 'No. Servora runs in the browser on a desktop, tablet or phone. The staff apps install from the browser to a phone. Only label printing and POS Sync use a small agent on the outlet PC.',
            'faq.6_q' => 'Can staff use it without a login each?',
            'faq.6_a' => 'Yes. Kitchen staff use a personal PIN on the staff portal, label app and SOP library. PIN-only staff never count as users.',
            'faq.7_q' => 'Can I get my data out?',
            'faq.7_a' => 'Always. Every module exports to CSV, and the reports and inventory screens to Excel and PDF too.',

            // ── close ───────────────────────────────────────────────────────
            'close.title'        => 'Stop finding out',
            'close.title_accent' => 'at month end',
            'close.lead'         => 'Cost one real recipe and see whether the margin matches what you assumed. Set-up takes minutes and the trial is the whole product.',
            'close.note'         => 'No card to start · Free plan for one outlet · Cancel from inside the app',
        ];
    }

    /** @return array<string, array<string, string>> section => [key => English] */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::defaults() as $key => $english) {
            $out[strstr($key, '.', true)][$key] = $english;
        }

        return array_intersect_key(array_replace(array_fill_keys(array_keys(self::SECTIONS), []), $out), $out);
    }
}
