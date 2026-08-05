<x-mail::message>
# Your payslip for {{ $period }}

Hello {{ $name }},

Your payslip for **{{ $period }}** is attached to this email as a PDF.

If anything on it looks wrong, or you cannot open the attachment, please speak to
whoever handles payroll at {{ $brandName }} rather than replying to this message.

Thanks,<br>
{{ $brandName }}

<x-mail::subcopy>
This email was sent to you because you are on {{ $brandName }}'s payroll. It contains
personal information — please do not forward it.
</x-mail::subcopy>
</x-mail::message>
