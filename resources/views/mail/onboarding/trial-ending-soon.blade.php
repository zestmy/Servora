<x-mail::message>
# Your trial ends in 3 days, {{ $company->name }}

Your {{ $subscription->plan->name }} trial expires on **{{ $subscription->trial_ends_at->format('d M Y') }}**.

Subscribe now to keep your data and continue using Servora without interruption.

<x-mail::button :url="url('/billing')" color="primary">
Subscribe Now
</x-mail::button>

**What happens when the trial ends?**
- Your data is preserved for 30 days
- You can subscribe anytime to restore full access
- No data is deleted during the grace period

Thanks,<br>
The Servora Team
</x-mail::message>
