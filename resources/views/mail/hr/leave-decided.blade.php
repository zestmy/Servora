<x-mail::message>
# {{ $isTimeOff ? 'Time off' : 'Leave' }} {{ strtolower($outcome) }}

Hello {{ $employeeName }},

Your request for {{ $isTimeOff ? 'time off' : $typeName }} on **{{ $dates }}** ({{ $days }})
has been **{{ strtolower($outcome) }}**@if ($decidedBy) by {{ $decidedBy }}@endif.

@if ($decisionNote)
<x-mail::panel>
{{ $decisionNote }}
</x-mail::panel>
@endif

@if (strtolower($outcome) === 'approved')
The days have been taken off your balance.
@else
Nothing has been deducted from your balance.
@endif

Thanks,<br>
{{ $brandName }}
</x-mail::message>
