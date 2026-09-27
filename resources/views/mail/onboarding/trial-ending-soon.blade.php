<x-mail::message>
# Your trial ends in 3 days, {{ $company->name }}

Your {{ $subscription->plan->name }} trial expires on **{{ $subscription->trial_ends_at->format('d M Y') }}**.

Subscribe now to keep your data and continue using Servora without interruption.

<x-mail::button :url="url('/billing')" color="primary">
Subscribe Now
</x-mail::button>

**What happens when the trial ends?**
- You move to the Free plan automatically — no card needed, nothing deleted
- Recipe costing for one outlet keeps working
- Purchasing, inventory control, full reports and add-ons lock until you upgrade

Thanks,<br>
The Servora Team
</x-mail::message>
