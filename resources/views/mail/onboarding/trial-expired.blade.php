<x-mail::message>
# Your Servora trial has ended, {{ $company->name }}

Your {{ $subscription->plan->name }} trial expired on **{{ $subscription->trial_ends_at->format('d M Y') }}**.

Don't worry — all your data is safe. Subscribe today to restore full access to your ingredients, recipes, sales records, and reports.

<x-mail::button :url="url('/billing')" color="primary">
Subscribe to Servora
</x-mail::button>

Your data will be preserved for 30 days. After that, inactive accounts may be archived.

Thanks,<br>
The Servora Team
</x-mail::message>
