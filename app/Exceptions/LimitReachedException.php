<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adding one more of something the plan caps (Entitlements::assertCanAdd).
 *
 * In a Livewire action it never reaches the error page: AppServiceProvider
 * turns it into a `plan-limit` browser event, which the plan-limit dialog
 * shows. Anywhere else it renders as a redirect back with the message.
 */
class LimitReachedException extends Exception
{
    public function __construct(
        public readonly string $metric,
        public readonly int $current,
        public readonly int $limit,
        ?string $message = null,
    ) {
        $label = str_replace('_', ' ', $metric);
        parent::__construct($message ?? "You have reached your plan limit of {$limit} {$label}. Please upgrade to add more.");
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage(), 'metric' => $this->metric], 403);
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
