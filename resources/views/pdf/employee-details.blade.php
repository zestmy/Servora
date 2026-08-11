<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $employee->name }} — Employee Details</title>
    <style>
        /* Scoped reset — `html` (or `*`) must NOT be reset here: dompdf
           implements @page margins via the root element, so `html { margin: 0 }`
           silently zeroes the page margins. */
        body, div, span, p, h1, h2, h3, table, thead, tbody, tr, th, td, img { margin: 0; padding: 0; }
        @@page { margin: 14mm 12mm; }

        body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 9px; color: #1a1a1a; line-height: 1.35; }

        .header { display: table; width: 100%; border-bottom: 1.5px solid #2d3748; padding-bottom: 6px; margin-bottom: 10px; }
        .header-left  { display: table-cell; vertical-align: middle; width: 62%; }
        .header-right { display: table-cell; vertical-align: middle; width: 38%; text-align: right; }
        .logo  { max-height: 32px; max-width: 110px; vertical-align: middle; }
        .brand { font-size: 12px; font-weight: bold; vertical-align: middle; }
        .title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 1.2px; color: #2d3748; }
        .meta  { font-size: 7px; color: #999; margin-top: 2px; }

        /* Identity block: photo beside the name, the way a form opens. */
        .identity { display: table; width: 100%; margin-bottom: 10px; }
        .identity-photo { display: table-cell; width: 84px; vertical-align: top; }
        .identity-body  { display: table-cell; vertical-align: top; padding-left: 10px; }
        .photo-frame {
            width: 76px; height: 92px; border: 1px solid #cbd5e0; background: #f7fafc;
            text-align: center; overflow: hidden;
        }
        .photo-frame img { width: 76px; height: 92px; }
        .photo-empty { font-size: 6.5px; color: #a0aec0; padding-top: 40px; }
        .emp-name { font-size: 15px; font-weight: bold; }
        .emp-sub  { font-size: 8.5px; color: #4a5568; margin-top: 1px; }

        h2.section {
            font-size: 8px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;
            color: #fff; background: #2d3748; padding: 3px 6px; margin: 10px 0 0;
        }

        /* Label/value pairs in two columns, boxed like a form rather than
           printed like a report — this is read by someone checking a field at
           a time, not scanning for a total. */
        table.fields { width: 100%; border-collapse: collapse; }
        table.fields td {
            border: 1px solid #e2e8f0; padding: 3.5px 6px; vertical-align: top;
        }
        td.label {
            width: 22%; background: #f7fafc; font-size: 7.5px; text-transform: uppercase;
            letter-spacing: 0.4px; color: #4a5568;
        }
        td.value { width: 28%; font-size: 9px; }
        .muted { color: #a0aec0; }

        .note { margin-top: 10px; font-size: 7px; color: #718096; }

        /* Signature strip, because this is a form somebody signs. */
        .sign { display: table; width: 100%; margin-top: 18px; }
        .sign-cell { display: table-cell; width: 50%; padding-right: 18px; }
        .sign-line { border-top: 1px solid #718096; margin-top: 30px; padding-top: 3px; font-size: 7.5px; color: #4a5568; }
    </style>
</head>
<body>

@php
    /* One helper so an empty field reads the same everywhere. A blank cell and
       a cell containing "—" look identical to a person and completely
       different to somebody wondering whether the form printed correctly. */
    $show = fn ($value) => filled($value)
        ? e($value)
        : '<span class="muted">&mdash;</span>';
@endphp

<div class="header">
    <div class="header-left">
        @if ($logoBase64)
            <img src="{{ $logoBase64 }}" class="logo" alt="">
        @else
            <span class="brand">{{ $brandName }}</span>
        @endif
    </div>
    <div class="header-right">
        <div class="title">Employee Details</div>
        <div class="meta">Generated {{ now()->format('d M Y, H:i') }}</div>
    </div>
</div>

<div class="identity">
    <div class="identity-photo">
        <div class="photo-frame">
            @if ($photoBase64)
                <img src="{{ $photoBase64 }}" alt="">
            @else
                <div class="photo-empty">No photograph</div>
            @endif
        </div>
    </div>
    <div class="identity-body">
        <div class="emp-name">{{ $employee->name }}</div>
        <div class="emp-sub">
            {{ $employee->designation ?: 'No designation' }}
            @if ($employee->staff_id) &middot; Staff ID {{ $employee->staff_id }} @endif
        </div>
        <div class="emp-sub">
            {{ $employee->outlet?->name ?: 'No branch' }}
            @if ($employee->section?->name) &middot; {{ $employee->section->name }} @endif
        </div>
    </div>
</div>

<h2 class="section">Personal Particulars</h2>
<table class="fields">
    <tr>
        <td class="label">Full name</td><td class="value">{!! $show($employee->name) !!}</td>
        <td class="label">IC / Passport</td><td class="value">{!! $show($employee->ic_number) !!}</td>
    </tr>
    <tr>
        <td class="label">Date of birth</td><td class="value">{!! $show($employee->date_of_birth?->format('d M Y')) !!}</td>
        <td class="label">Gender</td><td class="value">{!! $show($employee->gender) !!}</td>
    </tr>
    <tr>
        <td class="label">Nationality</td><td class="value">{!! $show($employee->nationality) !!}</td>
        <td class="label">Race</td><td class="value">{!! $show($employee->race) !!}</td>
    </tr>
    <tr>
        <td class="label">Religion</td><td class="value">{!! $show($employee->religion) !!}</td>
        <td class="label">Marital status</td><td class="value">{!! $show($employee->marital_status) !!}</td>
    </tr>
    <tr>
        <td class="label">Education</td><td class="value">{!! $show($employee->education_level) !!}</td>
        <td class="label">&nbsp;</td><td class="value">&nbsp;</td>
    </tr>
</table>

<h2 class="section">Contact</h2>
<table class="fields">
    <tr>
        <td class="label">Mobile</td><td class="value">{!! $show($employee->phone) !!}</td>
        <td class="label">Email</td><td class="value">{!! $show($employee->email) !!}</td>
    </tr>
</table>

<h2 class="section">Employment</h2>
<table class="fields">
    <tr>
        <td class="label">Branch</td><td class="value">{!! $show($employee->outlet?->name) !!}</td>
        <td class="label">Section</td><td class="value">{!! $show($employee->section?->name) !!}</td>
    </tr>
    <tr>
        <td class="label">Designation</td><td class="value">{!! $show($employee->designation) !!}</td>
        <td class="label">Reports to</td><td class="value">{!! $show($employee->superior?->name) !!}</td>
    </tr>
    <tr>
        <td class="label">Date joined</td><td class="value">{!! $show($employee->join_date?->format('d M Y')) !!}</td>
        <td class="label">Staff ID</td><td class="value">{!! $show($employee->staff_id) !!}</td>
    </tr>

    {{-- Employment standing carries its own ability, exactly as it does on the
         form. A PDF that printed it for somebody who cannot see it on screen
         would undo that gate with a button. --}}
    @if ($canViewEmployment)
        <tr>
            <td class="label">Status</td>
            <td class="value">{!! $show($employee->employment_status ? ucfirst(str_replace('_', ' ', $employee->employment_status)) : null) !!}</td>
            <td class="label">Status date</td><td class="value">{!! $show($employee->employment_status_date?->format('d M Y')) !!}</td>
        </tr>
        <tr>
            <td class="label">Active</td><td class="value">{{ $employee->is_active ? 'Yes' : 'No' }}</td>
            <td class="label">Outsourcing</td><td class="value">{!! $show($employee->outsourcing_company) !!}</td>
        </tr>
    @endif
</table>

@if ($canViewPay)
    <h2 class="section">Pay &amp; Bank</h2>
    <table class="fields">
        <tr>
            {{-- The same column means two things depending on who is paid —
                 see Employee::salaryFieldLabel(). This page is printed and
                 signed, so a contract rate must not go out labelled as
                 somebody's basic salary. --}}
            <td class="label">{{ $employee->salaryFieldLabel() }}</td>
            <td class="value">{!! $show($employee->basic_salary ? number_format((float) $employee->basic_salary, 2) : null) !!}</td>
            <td class="label">Pay type</td><td class="value">{!! $show($employee->pay_type) !!}</td>
        </tr>
        <tr>
            <td class="label">Bank</td><td class="value">{!! $show($employee->bank_name) !!}</td>
            <td class="label">Account no.</td><td class="value">{!! $show($employee->bank_account_no) !!}</td>
        </tr>
        <tr>
            <td class="label">Account name</td><td class="value">{!! $show($employee->bank_account_name) !!}</td>
            <td class="label">Service points</td><td class="value">{!! $show($employee->service_points_entitlement) !!}</td>
        </tr>
    </table>
    @if ($employee->isOutsourced())
        <p style="font-size: 8pt; color: #555; margin-top: -4px;">
            Outsourced — the figure above is the rate paid to
            {{ $employee->outsourcing_company ?: 'the outsourcing agent' }}, not the employee's
            take-home pay. No statutory contributions are made by this company, and payment is
            made on the agent's invoice rather than to the account shown.
        </p>
    @endif
@endif

<h2 class="section">Certifications</h2>
<table class="fields">
    <tr>
        <td class="label">Food handler</td>
        <td class="value">{{ $employee->food_handler_certified ? 'Yes' : 'No' }}{{ $employee->food_handler_cert_no ? ' · ' . $employee->food_handler_cert_no : '' }}</td>
        <td class="label">Expires</td><td class="value">{!! $show($employee->food_handler_expired_on?->format('d M Y')) !!}</td>
    </tr>
    <tr>
        <td class="label">Typhoid</td><td class="value">{{ $employee->typhoid_card ? 'Yes' : 'No' }}</td>
        <td class="label">Valid until</td><td class="value">{!! $show($employee->typhoid_expired_on?->format('d M Y')) !!}</td>
    </tr>
    <tr>
        <td class="label">Halal training</td><td class="value">{{ $employee->halal_training ? 'Yes' : 'No' }}</td>
        <td class="label">Expires</td><td class="value">{!! $show($employee->halal_training_expired_on?->format('d M Y')) !!}</td>
    </tr>
</table>

<h2 class="section">Emergency Contact</h2>
<table class="fields">
    <tr>
        <td class="label">Name</td><td class="value">{!! $show($employee->emergency_contact_name) !!}</td>
        <td class="label">Relationship</td><td class="value">{!! $show($employee->emergency_contact_relationship) !!}</td>
    </tr>
    <tr>
        <td class="label">Phone</td><td class="value">{!! $show($employee->emergency_contact_phone) !!}</td>
        <td class="label">Alternate</td><td class="value">{!! $show($employee->emergency_contact_phone_alt) !!}</td>
    </tr>
    <tr>
        <td class="label">Address</td>
        <td class="value" colspan="3">{!! $show($employee->emergency_contact_address) !!}</td>
    </tr>
</table>

<div class="sign">
    <div class="sign-cell">
        <div class="sign-line">Employee signature &amp; date</div>
    </div>
    <div class="sign-cell">
        <div class="sign-line">Verified by (HR) &amp; date</div>
    </div>
</div>

<p class="note">
    Printed from Servora on {{ now()->format('d M Y') }} by {{ $generatedBy }}.
    This record contains personal data — handle and store it accordingly.
</p>

</body>
</html>
