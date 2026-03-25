<x-mail::message>
# Welcome to Servora, {{ $company->name }}!

You signed up yesterday — great start! Here are a few things to do to get the most out of your trial:

- **Add your ingredients** — the foundation of recipe costing
- **Build your first recipe** — see automatic cost calculations
- **Create a purchase order** — experience the full PO workflow
- **Record today's sales** — start tracking revenue

<x-mail::button :url="url('/dashboard')">
Go to Dashboard
</x-mail::button>

Your {{ $subscription->plan->name }} trial has **{{ $subscription->daysRemaining() }} days** remaining.

Thanks,<br>
The Servora Team
</x-mail::message>
