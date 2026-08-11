<?php

namespace App\Livewire\Settings;

use App\Models\LeaveType;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Leave types: what kinds of leave this company grants, and how each behaves.
 *
 * The flag that earns its keep is "can be applied for". A replacement public
 * holiday is often PAID OUT WITH SALARY rather than taken as a day off — the
 * entitlement still has to be recorded and reported, but nobody may apply for
 * it. Unticking it here is how HR says so, and the apply form then explains
 * itself instead of silently refusing.
 */
class LeaveTypes extends Component
{
    public bool   $showForm = false;
    public ?int   $editingId = null;

    public string $f_name = '';
    public string $f_code = '';
    public string $f_description = '';
    public bool   $f_is_paid = true;
    public bool   $f_is_claimable = true;
    public bool   $f_is_replacement_holiday = false;

    /*
     * Rules any type may carry — see LeaveRules. Per type rather than
     * company-wide, so a custom Study Leave or the Hospitalisation entitlement
     * can use them as readily as annual leave can.
     */
    public bool   $f_is_prorated           = false;
    public bool   $f_requires_confirmation = false;
    public bool   $f_requires_approval = true;
    /** Offers an MC upload on the application form. */
    public bool   $f_requires_attachment = false;
    public bool   $f_allows_half_day = true;
    public string $f_default_days = '0';
    public bool   $f_carry_forward = false;
    public string $f_carry_forward_cap = '';
    public string $f_colour = 'slate';
    public bool   $f_is_active = true;

    public const COLOURS = ['slate', 'teal', 'blue', 'indigo', 'amber', 'pink', 'gray'];

    public function mount(): void
    {
        // A company with no types at all cannot use the module, and choosing
        // seven names is not a decision worth blocking on.
        LeaveType::seedDefaults(Auth::user()->company_id);
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $type = LeaveType::findOrFail($id);

        $this->editingId           = $type->id;
        $this->f_name              = $type->name;
        $this->f_code              = $type->code;
        $this->f_description       = (string) $type->description;
        $this->f_is_paid           = (bool) $type->is_paid;
        $this->f_is_claimable      = (bool) $type->is_claimable;
        $this->f_is_replacement_holiday = (bool) $type->is_replacement_holiday;
        $this->f_is_prorated           = (bool) $type->is_prorated;
        $this->f_requires_confirmation = (bool) $type->requires_confirmation;
        $this->f_requires_approval = (bool) $type->requires_approval;
        $this->f_requires_attachment = (bool) $type->requires_attachment;
        $this->f_allows_half_day   = (bool) $type->allows_half_day;
        $this->f_default_days      = (string) (float) $type->default_days;
        $this->f_carry_forward     = (bool) $type->carry_forward;
        $this->f_carry_forward_cap = $type->carry_forward_cap !== null ? (string) (float) $type->carry_forward_cap : '';
        $this->f_colour            = $type->colour;
        $this->f_is_active         = (bool) $type->is_active;

        $this->showForm = true;
    }

    public function save(): void
    {
        $companyId = Auth::user()->company_id;

        $this->validate([
            'f_name' => 'required|string|max:100',
            'f_code' => [
                'required', 'string', 'max:20',
                // Codes appear on the roster and in reports, so a duplicate
                // would make two different leaves indistinguishable.
                \Illuminate\Validation\Rule::unique('leave_types', 'code')
                    ->where('company_id', $companyId)
                    ->ignore($this->editingId),
            ],
            'f_description'       => 'nullable|string|max:500',
            'f_default_days'      => 'required|numeric|min:0|max:400',
            'f_carry_forward_cap' => 'nullable|numeric|min:0|max:400',
            'f_colour'            => 'in:' . implode(',', self::COLOURS),
        ], [], [
            'f_name' => 'name', 'f_code' => 'code', 'f_default_days' => 'default days',
        ]);

        $data = [
            'company_id'        => $companyId,
            'name'              => trim($this->f_name),
            'code'              => strtoupper(trim($this->f_code)),
            'description'       => trim($this->f_description) ?: null,
            'is_paid'           => $this->f_is_paid,
            'is_claimable'      => $this->f_is_claimable,
            'requires_approval' => $this->f_requires_approval,
            'requires_attachment' => $this->f_requires_attachment,
            'allows_half_day'   => $this->f_allows_half_day,
            'default_days'      => round((float) $this->f_default_days, 1),
            'carry_forward'     => $this->f_carry_forward,
            // Only meaningful while carry forward is on — storing a cap for a
            // type that does not carry would read as policy that is not there.
            'carry_forward_cap' => $this->f_carry_forward && $this->f_carry_forward_cap !== ''
                ? round((float) $this->f_carry_forward_cap, 1)
                : null,
            'colour'            => $this->f_colour,
            'is_active'         => $this->f_is_active,
            // See LeaveRules. Any type may carry these, not just annual leave.
            'is_prorated'           => $this->f_is_prorated,
            'requires_confirmation' => $this->f_requires_confirmation,
        ];

        if ($this->editingId) {
            $type = LeaveType::findOrFail($this->editingId);
            $type->update($data);
            $message = 'Leave type updated.';
        } else {
            $data['sort_order'] = (int) LeaveType::max('sort_order') + 1;
            $type = LeaveType::create($data);
            $message = 'Leave type added.';
        }

        // Set last and through the model, which clears the flag off any other
        // type: two of them would make "how many replacement days do I have"
        // depend on sort order, which nobody would guess was the cause.
        if ($this->f_is_replacement_holiday) {
            LeaveType::makeReplacementHoliday($type);
            $message .= ' Its balance now comes from the public holiday register.';
        } elseif ($type->is_replacement_holiday) {
            $type->update(['is_replacement_holiday' => false]);
        }


        session()->flash('success', $message);

        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Retired rather than deleted once it has been used.
     *
     * A type with leave recorded against it cannot be removed without taking
     * that history with it, so it is switched off instead and stops appearing
     * on the apply form.
     */
    public function toggleActive(int $id): void
    {
        $type = LeaveType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);
    }

    public function delete(int $id): void
    {
        $type = LeaveType::withCount('requests')->findOrFail($id);

        if ($type->requests_count > 0) {
            session()->flash('error', 'This type has leave recorded against it — switch it off instead of deleting, or that history loses its name.');
            return;
        }

        $type->delete();
        session()->flash('success', 'Leave type deleted.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId', 'f_name', 'f_code', 'f_description', 'f_default_days',
            'f_carry_forward_cap',
        ]);
        $this->f_is_paid = true;
        $this->f_is_claimable = true;
        $this->f_is_replacement_holiday = false;
        $this->f_is_prorated           = false;
        $this->f_requires_confirmation = false;
        $this->f_requires_approval = true;
        $this->f_requires_attachment = false;
        $this->f_allows_half_day = true;
        $this->f_carry_forward = false;
        $this->f_colour = 'slate';
        $this->f_is_active = true;
        $this->f_default_days = '0';
        $this->resetErrorBag();
    }

    public function render()
    {
        $types = LeaveType::withCount('requests')->ordered()->get();

        return view('livewire.settings.leave-types', [
            'types' => $types,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Leave Types']);
    }
}
