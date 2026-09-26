<x-mail::message>
# Overdue audit work

Hello {{ $name }},

@if ($schedules->isNotEmpty())
## {{ $schedules->count() }} scheduled audit{{ $schedules->count() === 1 ? '' : 's' }} overdue

<x-mail::table>
| Outlet | Form | Was due |
|:--|:--|:--|
@foreach ($schedules as $s)
| {{ $s->outlet?->name }} | {{ $s->template?->code ?: $s->template?->name }} | {{ $s->next_due_on->format('d M Y') }} ({{ $s->next_due_on->diffForHumans() }}) |
@endforeach
</x-mail::table>

<x-mail::button :url="$schedulesUrl">Open the schedule</x-mail::button>
@endif

@if ($reaudits->isNotEmpty())
## {{ $reaudits->count() }} re-audit{{ $reaudits->count() === 1 ? '' : 's' }} due

Conditional passes whose follow-up visit is due within {{ \App\Services\Audits\AuditReminderService::REAUDIT_SOON_DAYS }} days, or overdue.

<x-mail::table>
| Outlet | Form | Audited | Scored | Re-audit due |
|:--|:--|:--|:--|:--|
@foreach ($reaudits as $a)
| {{ $a->outlet?->name }} | {{ $a->template_code ?: $a->template_name }} | {{ $a->audit_date->format('d M Y') }} | {{ $a->score_percent !== null ? number_format($a->score_percent, 1) . '%' : '—' }} | {{ $a->reaudit_due_on->format('d M Y') }}{{ $a->reaudit_due_on->isPast() && ! $a->reaudit_due_on->isToday() ? ' (overdue)' : '' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$reauditsUrl">Open the audits</x-mail::button>
@endif

@if ($actions->isNotEmpty())
## {{ $actions->count() }} corrective action{{ $actions->count() === 1 ? '' : 's' }} overdue

<x-mail::table>
| Outlet | Action | Owner | Was due |
|:--|:--|:--|:--|
@foreach ($actions as $a)
| {{ $a->outlet?->name }} | {{ \Illuminate\Support\Str::limit($a->description, 70) }} | {{ $a->owner?->name ?? 'Nobody' }} | {{ $a->due_date->format('d M Y') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$actionsUrl">Review corrective actions</x-mail::button>
@endif

@if ($unactioned->isNotEmpty())
## {{ $unactioned->count() }} finding{{ $unactioned->count() === 1 ? '' : 's' }} with no corrective action yet

Submitted more than {{ \App\Services\Audits\AuditReminderService::UNACTIONED_AFTER_DAYS }} days ago and nobody has taken them up.

<x-mail::table>
| Outlet | Finding | Audit |
|:--|:--|:--|
@foreach ($unactioned as $f)
| {{ $f->outlet?->name }} | {{ $f->isMajor() ? '**MAJOR** ' : '' }}{{ \Illuminate\Support\Str::limit($f->item_label, 70) }} | {{ $f->audit?->audit_date?->format('d M Y') }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$unactionedUrl">Raise actions</x-mail::button>
@endif

Thanks,<br>
{{ $brandName }}

<x-mail::subcopy>
You get this because you conduct audits at {{ $brandName }}. It is sent once a day, only while something is overdue.
</x-mail::subcopy>
</x-mail::message>
