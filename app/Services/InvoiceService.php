<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;

class InvoiceService
{
    public function createFromPayment(Payment $payment): Invoice
    {
        $subscription = $payment->subscription;
        $plan = $subscription->plan;
        $company = $payment->company;

        $amount = $payment->amount;
        $taxAmount = 0; // Adjust if SST/GST applies
        $total = $amount + $taxAmount;

        $lineItems = [
            [
                'description' => "Servora {$plan->name} Plan — " . ucfirst($subscription->billing_cycle),
                'quantity'    => 1,
                'unit_price'  => $amount,
                'amount'      => $amount,
            ],
        ];

        return Invoice::create([
            'company_id'     => $company->id,
            'payment_id'     => $payment->id,
            'invoice_number' => Invoice::generateNumber(),
            'amount'         => $amount,
            'tax_amount'     => $taxAmount,
            'total'          => $total,
            'status'         => 'paid',
            'issued_at'      => now(),
            'paid_at'        => $payment->paid_at ?? now(),
            'line_items'     => $lineItems,
        ]);
    }
}
