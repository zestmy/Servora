{{-- Plain and short on purpose: this is read on a phone, often from a
     notification, by somebody standing at a door waiting to clock in. --}}
<div style="font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <p style="margin: 0 0 16px;">Hi {{ $employee->name }},</p>

    <p style="margin: 0 0 8px;">Your sign-in code is:</p>

    <p style="margin: 0 0 16px; font-size: 32px; font-weight: 700; letter-spacing: 6px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">
        {{ $code }}
    </p>

    <p style="margin: 0 0 16px;">
        It works for {{ $minutes }} minutes and once only. Asking for a new code stops this one working.
    </p>

    <p style="margin: 0; color: #6b7280; font-size: 13px;">
        If you did not ask to sign in, you can ignore this email — nobody can use the code without it.
        Tell your manager if it keeps arriving.
    </p>
</div>
