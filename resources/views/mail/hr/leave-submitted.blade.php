<x-mail::message>
# {{ $isTimeOff ? 'Time off request' : 'Leave request' }}

Hello {{ $recipientName }},

**{{ $employeeName }}** has applied for {{ $isTimeOff ? 'time off' : $typeName }} and it is waiting on your decision.

<x-mail::panel>
**{{ $dates }}** &middot; {{ $days }}
@if ($reason)

{{ $reason }}
@endif
</x-mail::panel>

@if ($remaining !== '')
{{ $employeeName }} has **{{ $remaining }}** remaining after this request.
@endif

<x-mail::button :url="$url">
Review the request
</x-mail::button>

Thanks,<br>
{{ $brandName }}
</x-mail::message>
