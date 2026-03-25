<?php

namespace App\Http\Controllers\Affiliate;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankController extends Controller
{
    public function update(Request $request)
    {
        $request->validate([
            'bank_name'           => 'required|string|max:100',
            'bank_account_name'   => 'required|string|max:200',
            'bank_account_number' => 'required|string|max:50',
        ]);

        Auth::guard('affiliate')->user()->update($request->only('bank_name', 'bank_account_name', 'bank_account_number'));

        return redirect()->route('affiliate.dashboard')->with('success', 'Bank details saved.');
    }
}
