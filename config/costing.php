<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Food cost targets
    |--------------------------------------------------------------------------
    |
    | The percentages the product judges a cost figure against. These were
    | hardcoded as bare `> 35` and `> 30` literals in five places — the two
    | dashboard gauge blocks, the COGS table, the chef alert and the shared
    | alert builder — so "the target" was five independent numbers that only
    | happened to agree.
    |
    | One definition now. Alert copy already said "target" as though this were
    | configured somewhere; this is that somewhere.
    |
    | `over` is the line past which a cost is a problem. `near` is the warning
    | band below it. Anything under `near` is on target.
    |
    | These are company-wide defaults. When margin targets need to vary per
    | tenant, the reader for that setting goes in this file's place and every
    | caller below keeps working unchanged.
    |
    */

    'target' => [
        'over' => (float) env('COSTING_TARGET_OVER', 35),
        'near' => (float) env('COSTING_TARGET_NEAR', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Wastage warning
    |--------------------------------------------------------------------------
    |
    | Wastage expressed as a percentage of revenue for the same period. A
    | ringgit figure on its own says nothing — RM 2,140 is unremarkable at one
    | volume and alarming at another — so the dashboards report the ratio and
    | raise attention above this line.
    |
    */

    'wastage_warn_pct' => (float) env('COSTING_WASTAGE_WARN_PCT', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Gross margin floor
    |--------------------------------------------------------------------------
    |
    | Below this, the finance dashboard raises attention. Finance was the only
    | role with no attention surface at all, which meant the one person whose
    | job is margin was the one person never told it had slipped.
    |
    */

    'margin_floor_pct' => (float) env('COSTING_MARGIN_FLOOR_PCT', 60),

    /*
    |--------------------------------------------------------------------------
    | Minimum days before a cost percentage is a verdict
    |--------------------------------------------------------------------------
    |
    | On day 2 of a month, cost % is computed off a sample too small to act on
    | — and was still painted red or green as though it were a finding. Under
    | this many elapsed days the gauges render the figure in ink and say so
    | instead of pretending to a judgement.
    |
    */

    'min_days_for_verdict' => (int) env('COSTING_MIN_DAYS_FOR_VERDICT', 5),

    /*
    |--------------------------------------------------------------------------
    | Purchase-order ageing
    |--------------------------------------------------------------------------
    |
    | How long a submitted PO may sit before the approver dashboard calls it
    | out. Four POs awaiting review is fine if they arrived this morning and a
    | problem if the oldest is nine days old; without this the two render
    | identically.
    |
    */

    'po_stale_days' => (int) env('COSTING_PO_STALE_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Over-cost recipe scan cache
    |--------------------------------------------------------------------------
    |
    | The scan hydrates every active recipe with its lines, ingredients, UOMs
    | and conversions to produce one count. See RecipeCostService for why that
    | cannot be pushed into SQL as things stand. Seconds.
    |
    */

    'overcost_cache_ttl' => (int) env('COSTING_OVERCOST_CACHE_TTL', 900),

];
