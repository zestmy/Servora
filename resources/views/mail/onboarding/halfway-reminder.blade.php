<x-mail::message>
# Halfway through your trial, {{ $company->name }}

You're 7 days into your Servora trial. Here's a quick check-in:

- **Plan:** {{ $subscription->plan->name }}
- **Days remaining:** {{ $subscription->daysRemaining() }}

If you haven't already, try these high-impact features:

1. **Purchasing workflow** — PO to Delivery Order to GRN
2. **Stock takes** — Know your closing stock and COGS
3. **Reports** — Monthly cost summaries and P&L breakdowns

Need help getting started? Reply to this email and we'll guide you through.

<x-mail::button :url="url('/billing')">
View Your Plan
</x-mail::button>

Thanks,<br>
The Servora Team
</x-mail::message>
