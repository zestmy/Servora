<x-mail::message>
# Your Servora trial has ended, {{ $company->name }}

Your {{ $subscription->plan->name }} trial expired on **{{ $subscription->trial_ends_at->format('d M Y') }}**.

You are now on the **Free plan**. Nothing has been deleted — your ingredients, recipes and records are all still there, and recipe costing for one outlet keeps working.

Purchasing, inventory control, the full reports and the add-on modules are locked until you upgrade. Basic is RM180 per outlet a month; Full, with every add-on, is RM400.

<x-mail::button :url="url('/billing')" color="primary">
See plans
</x-mail::button>

Thanks,<br>
The Servora Team
</x-mail::message>
