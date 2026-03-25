<?php

namespace App\Livewire\Admin\Referrals;

use App\Models\Commission;
use App\Models\Referral;
use App\Models\ReferralCode;
use Livewire\Component;
use Livewire\WithPagination;

class Dashboard extends Component
{
    use WithPagination;

    public string $tab = 'referrals';
    public string $statusFilter = '';

    public function approveCommission(int $id): void
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['status' => Commission::STATUS_APPROVED]);
        session()->flash('success', 'Commission approved.');
    }

    public function markPaid(int $id, string $reference = ''): void
    {
        $commission = Commission::findOrFail($id);
        $commission->update([
            'status'           => Commission::STATUS_PAID,
            'paid_at'          => now(),
            'payout_reference' => $reference ?: null,
        ]);
        session()->flash('success', 'Commission marked as paid.');
    }

    public function rejectCommission(int $id): void
    {
        $commission = Commission::findOrFail($id);
        $commission->update(['status' => Commission::STATUS_REJECTED]);
        session()->flash('success', 'Commission rejected.');
    }

    public function render()
    {
        // Stats
        $totalReferrals = Referral::count();
        $totalConversions = Referral::where('status', Referral::STATUS_CONVERTED)->orWhere('status', Referral::STATUS_PAID)->count();
        $totalPending = Commission::where('status', Commission::STATUS_PENDING)->sum('amount');
        $totalPaid = Commission::where('status', Commission::STATUS_PAID)->sum('amount');

        // Referral codes with stats
        $codes = ReferralCode::withCount('referrals')
            ->orderByDesc('total_clicks')
            ->limit(20)
            ->get();

        // Referrals list
        $referrals = Referral::with(['referralCode', 'referredCompany'])
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15, pageName: 'referralsPage');

        // Commissions list
        $commissions = Commission::with(['referral.referralCode', 'referral.referredCompany', 'payment'])
            ->latest()
            ->paginate(15, pageName: 'commissionsPage');

        return view('livewire.admin.referrals.dashboard', compact(
            'totalReferrals', 'totalConversions', 'totalPending', 'totalPaid',
            'codes', 'referrals', 'commissions'
        ))->layout('layouts.app', ['title' => 'Referral Dashboard']);
    }
}
