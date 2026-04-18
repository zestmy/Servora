<style>
    /* ── Shared OT claim PDF styles ────────────────────────────────────── */

    /* Header */
    .ot-header { display: table; width: 100%; margin-bottom: 12px; padding-bottom: 7px; border-bottom: 2px solid #0f172a; }
    .ot-header .hl { display: table-cell; vertical-align: middle; width: 62%; }
    .ot-header .hr { display: table-cell; vertical-align: middle; width: 38%; text-align: right; }
    .ot-header .company-logo { max-height: 46px; max-width: 170px; margin-bottom: 3px; display: inline-block; }
    .ot-header .company-name { font-size: 13pt; font-weight: bold; color: #0f172a; letter-spacing: -0.2px; }
    .ot-header .company-detail { font-size: 8pt; color: #64748b; margin-top: 1px; }
    .ot-header .doc-title { font-size: 11pt; font-weight: bold; color: #0f172a; text-transform: uppercase; letter-spacing: 2px; }
    .ot-header .doc-status {
        display: inline-block;
        font-size: 8pt; font-weight: bold; color: #065f46;
        background: #d1fae5; padding: 2px 8px;
        border-radius: 3px;
        margin-top: 4px;
        letter-spacing: 0.5px;
    }

    /* Employee details + claim summary grid */
    .info-grid { display: table; width: 100%; margin-bottom: 12px; border-collapse: collapse; }
    .info-cell { display: table-cell; width: 50%; vertical-align: top; }
    .info-cell.left  { padding-right: 6px; }
    .info-cell.right { padding-left: 6px; }
    .info-card { border: 1px solid #cbd5e1; background: #fff; }
    .info-card h4 {
        margin: 0;
        padding: 5px 10px;
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: #334155;
        background: #f1f5f9;
        border-bottom: 1px solid #cbd5e1;
        font-weight: bold;
    }
    .info-body { padding: 7px 10px; }
    .kv { display: table; width: 100%; margin-bottom: 2px; }
    .kv .k { display: table-cell; width: 38%; font-size: 7.5pt; color: #64748b; font-weight: bold; text-transform: uppercase; letter-spacing: 0.4px; padding: 2px 0; }
    .kv .v { display: table-cell; font-size: 9.5pt; color: #0f172a; padding: 2px 0; }
    .kv.name .v { font-size: 11.5pt; font-weight: bold; line-height: 1.25; }

    /* Hours by type breakdown */
    .hours-breakdown { margin-top: 5px; border-top: 1px dashed #cbd5e1; padding-top: 5px; }
    .hours-breakdown .bt-label { font-size: 7pt; color: #64748b; text-transform: uppercase; letter-spacing: 1.2px; font-weight: bold; margin-bottom: 3px; }
    table.hours-table { width: 100%; border-collapse: collapse; }
    table.hours-table td { padding: 3px 0; font-size: 9pt; }
    table.hours-table td.type-label { color: #334155; padding-left: 4px; }
    table.hours-table td.type-hours { text-align: right; font-weight: bold; color: #0f172a; }
    table.hours-table tr.total td { border-top: 1.5px solid #0f172a; padding-top: 5px; font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
    table.hours-table tr.total td.type-hours { font-size: 11.5pt; color: #0369a1; }

    /* Approvals (digital — no hand-signature area) */
    .signatures { display: table; width: 100%; margin-top: 22px; border-collapse: separate; border-spacing: 8px 0; }
    .sig-cell {
        display: table-cell;
        width: 50%;
        vertical-align: top;
        text-align: center;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
    }
    .sig-role {
        font-size: 7.5pt;
        text-transform: uppercase;
        letter-spacing: 1.4px;
        color: #64748b;
        font-weight: bold;
        margin-bottom: 4px;
    }
    .sig-name { font-size: 10pt; font-weight: bold; color: #0f172a; }
    .sig-title { font-size: 8pt; color: #64748b; margin-top: 1px; }

    .computer-generated-note {
        margin-top: 14px;
        padding: 8px 12px;
        border-top: 1px dashed #cbd5e1;
        font-size: 7.5pt;
        color: #64748b;
        text-align: center;
        font-style: italic;
        line-height: 1.45;
    }
</style>
