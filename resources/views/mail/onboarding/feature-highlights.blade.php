<x-mail::message>
# Did you know? {{ $company->name }}

Here are some features you might not have discovered yet:

**Recipe Costing** — Build recipes with real-time food cost % calculations. Know exactly what each dish costs to make.

**Z-Report OCR** — Upload a photo of your Z-report and let AI extract the numbers automatically.

**Staff Training (LMS)** — Create SOPs with step-by-step instructions and training videos for your team.

**Multi-Outlet** — Managing more than one outlet? Servora handles shared recipes with outlet-specific data.

<x-mail::button :url="url('/recipes')">
Try Recipe Costing
</x-mail::button>

Your trial has **{{ $subscription->daysRemaining() }} days** remaining.

Thanks,<br>
The Servora Team
</x-mail::message>
