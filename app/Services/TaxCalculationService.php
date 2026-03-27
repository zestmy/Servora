<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TaxRate;

class TaxCalculationService
{
    /**
     * Calculate tax for a given subtotal.
     *
     * @return array{tax_amount: float, total: float, rate: float, name: string}
     */
    public static function calculate(float $subtotal, ?int $taxRateId = null, ?Company $company = null): array
    {
        // Try explicit tax rate first
        if ($taxRateId) {
            $taxRate = TaxRate::find($taxRateId);
            if ($taxRate) {
                return self::computeFromRate($subtotal, $taxRate);
            }
        }

        // Try company's default tax rate
        if ($company && $company->default_tax_country) {
            $taxRate = TaxRate::defaultForCompany($company);
            if ($taxRate) {
                return self::computeFromRate($subtotal, $taxRate);
            }
        }

        // Fallback to company's legacy tax_percent
        if ($company) {
            $pct = floatval($company->tax_percent ?? 0);
            if ($pct > 0) {
                $taxAmount = round($subtotal * ($pct / 100), 4);
                return [
                    'tax_amount' => $taxAmount,
                    'total'      => round($subtotal + $taxAmount, 4),
                    'rate'       => $pct,
                    'name'       => $company->tax_type ?? 'Tax',
                ];
            }
        }

        return [
            'tax_amount' => 0,
            'total'      => $subtotal,
            'rate'       => 0,
            'name'       => '',
        ];
    }

    /**
     * Compute tax from a TaxRate model.
     */
    private static function computeFromRate(float $subtotal, TaxRate $taxRate): array
    {
        $rate = floatval($taxRate->rate);

        if ($taxRate->is_inclusive) {
            // Subtotal already includes tax — extract tax
            $taxAmount = round($subtotal - ($subtotal / (1 + ($rate / 100))), 4);
            $total = $subtotal;
        } else {
            // Tax is added on top
            $taxAmount = round($subtotal * ($rate / 100), 4);
            $total = round($subtotal + $taxAmount, 4);
        }

        return [
            'tax_amount' => $taxAmount,
            'total'      => $total,
            'rate'       => $rate,
            'name'       => $taxRate->name,
        ];
    }

    /**
     * Compute grand total including tax and delivery charges.
     */
    public static function grandTotal(float $subtotal, float $taxAmount, float $deliveryCharges): float
    {
        return round($subtotal + $taxAmount + $deliveryCharges, 4);
    }
}
