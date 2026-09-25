<x-mail::message>
# {{ $actions->count() }} audit fix{{ $actions->count() === 1 ? ' is' : 'es are' }} overdue

Hello {{ $name }},

An outlet audit found these, they were assigned to you, and the due date has passed:

<x-mail::table>
| What | Found | Was due |
|:--|:--|:--|
@foreach ($actions as $a)
| {{ \Illuminate\Support\Str::limit($a->description, 80) }} | {{ \Illuminate\Support\Str::limit($a->finding?->item_label, 60) }} | {{ $a->due_date->format('d M Y') }} |
@endforeach
</x-mail::table>

When one is done, mark it done in the Staff Portal — add a note and a photo of the fix if you can. The auditor then verifies it.

<x-mail::button :url="$portalUrl">Open Audit fixes</x-mail::button>

Thanks,<br>
{{ $brandName }}

<x-mail::subcopy>
Sent once a day while a corrective action in your name is overdue. If this should be somebody else's, tell your manager so it can be reassigned.
</x-mail::subcopy>
</x-mail::message>
