<?php

namespace App\Livewire\Staff\Training;

use App\Http\Middleware\ClockStaffAuthenticate;
use App\Models\Company;
use App\Models\TrainingQuiz;
use App\Scopes\CompanyScope;
use App\Services\Staff\StaffSession;
use Livewire\Component;

/**
 * The front door of a quiz — a public page that says what it is, then asks who
 * you are.
 *
 * Reached from a QR beside the pass or a link in a group chat, by somebody
 * holding their phone who has thirty seconds of attention. Two things have to
 * happen in that time: they have to see that this is the lamb rack paper and
 * not a phishing link, and they have to get through the PIN. Bouncing them
 * straight at a bare sign-in screen fails the first — a login form with no
 * context is exactly what a person backs out of.
 *
 * NO PIN IS TAKEN HERE. This screen ends at a button, and the button goes to
 * the quiz, which the ordinary staff-app middleware gates: it stores the
 * destination, sends them to the one sign-in screen this app has, and returns
 * them afterwards. A second PIN form living out here would be a second place
 * for the rate limiting, the email fallback and the lockout rules to drift out
 * of step — and PIN entry is not something to have two of.
 *
 * COMPANY SCOPE IS DROPPED AND company_id MATCHED BY HAND. Nobody is signed in
 * on this route, so the scope has nothing to resolve from; the subdomain
 * middleware has already decided which company we are on, and the token is
 * checked inside it.
 */
class QuizGate extends Component
{
    public string $token = '';

    public function mount(string $token, StaffSession $staff)
    {
        $this->token = $token;

        $quiz = $this->quiz();

        if (! $quiz) {
            return null;
        }

        // Already through the door — skip the introduction they do not need.
        if ($staff->check($staff->companyId())) {
            return $this->redirectRoute('clock.staff.learn.quiz', ['id' => $quiz->id], navigate: false);
        }

        // Where to come back to once the PIN lands. The middleware would set
        // this itself on the bounce; setting it here means the sign-in screen
        // is reached by an ordinary link rather than by a redirect, and the
        // return destination is the quiz either way.
        session()->put(
            ClockStaffAuthenticate::INTENDED_KEY,
            route('clock.staff.learn.quiz', ['id' => $quiz->id]),
        );

        return null;
    }

    private function companyId(): ?int
    {
        return app(StaffSession::class)->companyId();
    }

    public function quiz(): ?TrainingQuiz
    {
        $companyId = $this->companyId();

        if (! $companyId || trim($this->token) === '') {
            return null;
        }

        return TrainingQuiz::withoutGlobalScope(CompanyScope::class)
            ->where('company_id', $companyId)
            ->where('share_token', $this->token)
            // A draft quiz's link is dead until it is published, which is what
            // makes it safe to print the poster before the paper is finished.
            ->where('status', 'published')
            ->withCount('questions')
            ->with('course:id,title,summary')
            ->first();
    }

    public function render()
    {
        $quiz = $this->quiz();

        return view('livewire.staff.training.quiz-gate', [
            'quiz'    => $quiz,
            'company' => Company::find($this->companyId()),
            'minutes' => $quiz
                ? max(1, (int) ceil($quiz->questions_count * $quiz->default_seconds / 60))
                : 0,
        ])->layout('layouts.live', ['title' => $quiz?->title ?? 'Quiz']);
    }
}
