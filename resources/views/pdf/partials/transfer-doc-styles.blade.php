{{-- Additions for the transfer documents (labour cost and stock), on top of
     ot-claim-styles so both read as the same family as the OT claim form. --}}
<style>
    .ot-header .doc-number { font-size: 9.5pt; color: #334155; margin-top: 2px; font-weight: bold; }
    .ot-header .doc-status.draft     { color: #334155; background: #e2e8f0; }
    .ot-header .doc-status.cancelled { color: #991b1b; background: #fee2e2; }
    .ot-header .doc-status.transit   { color: #92400e; background: #fef3c7; }

    .section-title {
        font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 1.6px;
        color: #0f172a; margin: 14px 0 6px 0; padding-bottom: 3px; border-bottom: 1.5px solid #0f172a;
    }

    table.items td.num, table.items th.num { text-align: right; white-space: nowrap; }
    table.items td .sub { display: block; font-size: 7.5pt; color: #64748b; }
    table.items.compact thead th { font-size: 7.5pt; padding: 5px 5px; letter-spacing: 0.4px; }
    table.items.compact tbody td, table.items.compact tfoot td { font-size: 8.5pt; padding: 4px 5px; }
    table.items tr.grand td { font-size: 10pt; font-weight: bold; color: #0f172a; }

    .money-total { font-size: 13pt; font-weight: bold; color: #0369a1; }

    .tag { display: inline-block; font-size: 6.5pt; font-weight: bold; letter-spacing: 0.6px; padding: 0 4px; border-radius: 2px; margin-left: 3px; }
    .tag-prep   { color: #92400e; background: #fef3c7; }
    .tag-recipe { color: #115e59; background: #ccfbf1; }
    .tag-custom { color: #334155; background: #e2e8f0; }
    .tag-asset  { color: #0369a1; background: #e0f2fe; }

    .net-pos { color: #b91c1c; font-weight: bold; }
    .net-neg { color: #047857; font-weight: bold; }

    /* Hand-signed blocks: goods change hands physically, so a line to sign on. */
    .sig-space { height: 34px; border-bottom: 1px solid #94a3b8; margin: 6px 12px 4px 12px; }
</style>
<style>
    /* Tighter vertical rhythm than the OT form: these carry two tables, and a
       three-person event should still sign off on one page. */
    .signatures { margin-top: 14px; }
    .computer-generated-note { margin-top: 8px; padding-top: 5px; }
    .sig-cell { padding: 6px 10px; }
    .pdf-footer { margin-top: 10px; }
    .notes { margin-top: 8px; padding: 6px 10px; }
    .section-title { margin-top: 10px; }
</style>
