<?php

return [
    'slug'    => 'people-and-payroll',
    'title'   => 'People & payroll',
    'summary' => 'Employees, rosters, attendance, clock-in, leave, overtime, payroll and EA forms.',
    'icon'    => 'users',
    'sort'    => 70,
    'articles' => [

        [
            'slug'     => 'employees',
            'title'    => 'Employee records',
            'excerpt'  => 'One record per person, holding what payroll, the roster and compliance each need.',
            'keywords' => 'employee, staff, HR, personal details, documents, certification',
            'body' => <<<'HTML'
<p>An employee record is the single place a person exists in Servora. Payroll, the roster, attendance and training all read from it.</p>

<h2>What a complete record holds</h2>

<ul>
  <li><strong>Identity</strong> — name, IC or passport, date of birth, contact details, emergency contact.</li>
  <li><strong>Employment</strong> — start date, position, department, section, outlet, employment type.</li>
  <li><strong>Pay</strong> — basic salary, pay components (allowances, deductions), bank details.</li>
  <li><strong>Statutory</strong> — EPF, SOCSO, EIS and tax numbers, and the categories that decide the rates.</li>
  <li><strong>Documents</strong> — contract, IC copy, permits, certificates, with expiry dates.</li>
</ul>

<h2>Fill the statutory profile before the first payroll</h2>

<p>Payroll cannot calculate contributions for a person whose statutory categories are blank, and a run that silently skips somebody is worse than one that refuses. Complete these before the first run rather than during it.</p>

<h2>Documents that expire</h2>

<p>Work permits, food-handler certificates, medical clearances. Record the expiry and Servora warns you before it lapses. An expired food-handler certificate found by an inspector is a different problem from one found by a reminder.</p>

<h2>Leavers</h2>

<p>Mark the leaving date rather than deleting the record. Payroll history, EA forms and audit trails all need the person to keep existing. A dated leaver drops off the roster and out of active headcount without taking their history with them.</p>
HTML,
        ],

        [
            'slug'     => 'duty-roster',
            'title'    => 'The duty roster',
            'excerpt'  => 'Plan the week, publish it, and let the team read it on their phones.',
            'keywords' => 'roster, schedule, shifts, rota, duty, planning, publish',
            'body' => <<<'HTML'
<p>The roster is what you <em>plan</em>. It is deliberately a separate record from attendance, which is what <em>happened</em> — see <a href="/help/people-and-payroll/attendance">Attendance</a>.</p>

<figure><img src="/images/docs/duty-roster.svg" alt="A weekly duty roster grid with six staff down the side and seven days across the top, each cell showing a shift code — M for morning, E for evening, OFF for rest day, AL for leave"><figcaption>A published week. Codes are configurable; these are the defaults.</figcaption></figure>

<h2>Shifts first</h2>

<p>Under <strong>HR → Shifts</strong>, define the shifts you actually run — Morning 07:00–16:00, Evening 15:00–00:00, Split. Each carries its hours and any break. Rostering then becomes picking a shift rather than typing times.</p>

<h2>Building a week</h2>

<ol>
  <li><strong>HR → Duty Roster.</strong> Pick the outlet and the week.</li>
  <li>Assign a shift to each person on each day. Leave a rest day blank or mark it OFF.</li>
  <li>Approved leave already appears — you cannot accidentally roster someone who is away.</li>
  <li>Copy last week and adjust, rather than starting from empty.</li>
  <li><strong>Publish.</strong> The team can see it; before that it is a draft.</li>
</ol>

<h2>Stations</h2>

<p>A shift can carry a station — grill, pass, front. It turns a roster into something the kitchen can actually work from, rather than a list of who is in the building.</p>

<h2>Amendments</h2>

<p>Change a published week and the change is recorded as an amendment with a reason, not an overwrite. That matters when somebody says they were never told, and it matters again at payroll.</p>

<h2>Getting it out</h2>

<p>Export the week as a PDF for the noticeboard, or have it emailed to a list of recipients when it is published. Staff with a login see it in the app; those without read the printed one.</p>
HTML,
        ],

        [
            'slug'     => 'attendance',
            'title'    => 'Attendance',
            'excerpt'  => 'What actually happened, entered by a manager — and why it is not derived from the roster.',
            'keywords' => 'attendance, present, absent, MC, sick leave, attendance code, timesheet',
            'body' => <<<'HTML'
<p>Attendance is the record of who actually worked. It is entered by a manager, deliberately: deriving it from the roster would record the week you planned, and the whole reason you need it is the days it differs.</p>

<h2>The codes</h2>

<table>
  <thead><tr><th>Code</th><th>Means</th></tr></thead>
  <tbody>
    <tr><td>✓</td><td>Present — worked the shift.</td></tr>
    <tr><td>X</td><td>Off — a scheduled rest day.</td></tr>
    <tr><td>ABS</td><td>Absent — should have worked and did not.</td></tr>
    <tr><td>MC / SL</td><td>Medical or sick leave.</td></tr>
    <tr><td>AL</td><td>Annual leave.</td></tr>
    <tr><td>PH</td><td>Public holiday.</td></tr>
  </tbody>
</table>

<p>The codes are configurable — add your own under <strong>Settings → HR</strong>. Each carries whether it is paid, and whether it counts as a working day.</p>

<h2>Marking a month</h2>

<p><strong>HR → Attendance Record</strong> shows a grid of staff against days. Click a cell to set its code. Most days are one code across the whole team, so start by filling the pattern and then fix the exceptions.</p>

<h2>Why the distinction matters at payroll</h2>

<p>Absent and medical leave are both days not worked and they are treated completely differently: one is unpaid, the other is paid and counts toward entitlement. Service-charge distribution also reads these codes — a month labelled MC is treated differently from a month labelled ABS. Marking everything ✓ because it is faster produces a payroll run that is wrong in both directions.</p>

<h2>Clock-in</h2>

<p>Where you use the clock-in app, actual clock times sit alongside the attendance record — see <a href="/help/people-and-payroll/clock-in">Clock-in</a>. They inform the attendance mark; they do not replace it, because a manager still has to say whether a late clock-in was a late start or a shift swap.</p>
HTML,
        ],

        [
            'slug'     => 'clock-in',
            'title'    => 'Clock-in',
            'excerpt'  => 'A tablet at the door, a PIN or a face, and a timestamp nobody can round up.',
            'keywords' => 'clock in, clock out, time clock, punch, kiosk, face recognition, PIN',
            'body' => <<<'HTML'
<p>The clock-in app runs on a tablet or a spare PC at the outlet. Staff clock in and out; the times are recorded against their employee record.</p>

<h2>Setting one up</h2>

<ol>
  <li><strong>HR → Clock-Ins</strong>, register the device and generate its pairing code.</li>
  <li>Open the clock app on the tablet and enter the code. It stays paired.</li>
  <li>Choose the method — a personal PIN, or face recognition where you have enrolled photographs.</li>
</ol>

<p>The device is tied to an outlet, so a clock-in is always attributable to a place as well as a person.</p>

<h2>What managers see</h2>

<p>Clock events appear per employee per day, with the time in, the time out, and the hours between. Late arrivals and missing clock-outs are flagged. A missing clock-out is normally somebody who forgot at close — a manager can correct it, and the correction is recorded as a correction.</p>

<h2>Settings worth reviewing</h2>

<ul>
  <li><strong>Grace period</strong> — how many minutes late still counts as on time.</li>
  <li><strong>Rounding</strong> — whether times round to five or fifteen minutes.</li>
  <li><strong>Early clock-in window</strong> — how far ahead of a shift someone may clock in, so an early arrival does not become paid overtime.</li>
</ul>

<h2>Clock times are not attendance</h2>

<p>They are evidence for it. The attendance record is a manager's statement about the day; the clock is what the tablet saw. Keeping them separate is what lets you record "worked, clocked in late because of the delivery" as a fact rather than a dispute.</p>
HTML,
        ],

        [
            'slug'     => 'leave-and-overtime',
            'title'    => 'Leave, time off and overtime claims',
            'excerpt'  => 'Requests, approvals and balances — all of which land in payroll.',
            'keywords' => 'leave, annual leave, MC, time off, overtime, OT, claim, approval, balance',
            'body' => <<<'HTML'
<h2>Leave types and entitlement</h2>

<p>Define your leave types under <strong>Settings → HR</strong> — annual, medical, unpaid, maternity, compassionate — each with whether it is paid and how much is granted a year. Entitlement can vary by length of service.</p>

<h2>Requesting</h2>

<p>An employee raises a request with dates and a reason. It goes to the leave approvers configured for that outlet or department. Approvers see it in the app and by email; the requester sees the outcome and their remaining balance.</p>

<p>Approved leave flows straight into the duty roster, so the person cannot be scheduled while they are away.</p>

<h2>Time off</h2>

<p>Time off is the shorter version: an afternoon, a couple of hours, a swap. Same request-and-approve shape, without consuming annual leave.</p>

<h2>Overtime claims</h2>

<p>Where overtime is claimed rather than rostered, the employee submits the hours and the reason and an OT approver signs it. Approved claims feed the payroll run at the rate configured for that employee's category.</p>

<p>OT approvers are their own list, separate from leave approvers and from purchase approvers. The person who signs off spend is rarely the person who signs off hours.</p>

<h2>Do it before you close the month</h2>

<p>An unapproved claim is not in payroll. Clear the queue before the run is locked — after that, it is a manual adjustment on next month's run and everybody's figures move.</p>
HTML,
        ],

        [
            'slug'     => 'run-payroll',
            'title'    => 'Run payroll',
            'excerpt'  => 'Gross to net, with statutory contributions, service charge, and a lock you cannot undo.',
            'keywords' => 'payroll, salary, EPF, SOCSO, EIS, PCB, payslip, bank file, net pay',
            'body' => <<<'HTML'
<p>A payroll run takes a month, gathers everything that affects pay, and produces net figures, payslips and a bank file.</p>

<figure><img src="/images/docs/payroll-run.svg" alt="A payroll run screen showing gross, statutory, net payable and service charge totals, a table of employees with basic pay, overtime, service charge, deductions and net, and a pre-lock checklist"><figcaption>A run ready to be checked. The checklist on the left is the part people skip.</figcaption></figure>

<h2>Before you start</h2>

<ol>
  <li>Attendance marked for every employee, for every day.</li>
  <li>Overtime claims approved or rejected — not left pending.</li>
  <li>Leave approved, and unpaid leave marked as unpaid.</li>
  <li>Salary changes and new joiners entered.</li>
  <li>The service charge period closed, if you distribute one.</li>
</ol>

<h2>Running it</h2>

<ol>
  <li><strong>HR → Payroll → New run.</strong> Choose the month and the outlets.</li>
  <li>Servora calculates each employee: basic, allowances, overtime, service charge, then statutory contributions and deductions.</li>
  <li>Review the list. Look at the outliers first — the biggest net, the smallest, anyone whose figure moved a lot since last month.</li>
  <li>Add adjustments where something is not in the system: a one-off bonus, an advance being recovered.</li>
  <li><strong>Lock the run.</strong></li>
</ol>

<h2>What locking means</h2>

<p>A locked run stops recalculating. Payslips and the bank file are generated from the locked figures, so a later change to somebody's salary cannot silently rewrite a payslip that has already been sent. It is not reversible — check before you lock, not after.</p>

<h2>Payslips</h2>

<p>Generated per employee as a PDF and, where you have the addresses, emailed out. Delivery is recorded, so you can see who has received theirs.</p>

<h2>Service charge</h2>

<p>Where a service charge is collected and distributed, it is calculated over its own period and by points rather than headcount — so days worked, and the attendance codes behind them, decide each share. Close the period before the payroll run reads it.</p>

<h2>EA forms</h2>

<p>At year end, <strong>HR → EA Forms</strong> generates each employee's annual statement from the locked runs. Nothing to re-enter, provided the runs were locked as they went.</p>
HTML,
        ],
    ],
];
