<?php

namespace App\Livewire\Admin\Plans;

use App\Models\Plan;
use Illuminate\Support\Str;
use Livewire\Component;

class Form extends Component
{
    public ?int $planId = null;

    public string $name            = '';
    public string $slug            = '';
    public string $description     = '';
    public string $price_monthly   = '0';
    public string $price_yearly    = '0';
    public string $currency        = 'MYR';
    public string $max_outlets     = '1';
    public string $max_users       = '5';
    public string $max_recipes     = '';
    public string $max_ingredients = '';
    public string $max_lms_users   = '';
    public string $trial_days      = '14';
    public string $sort_order      = '0';
    public bool $is_active         = true;

    // Feature flags
    public bool $flag_lms         = true;
    public bool $flag_reports     = true;
    public bool $flag_analytics   = false;
    public bool $flag_ai_analysis = false;

    public function mount(?int $id = null): void
    {
        if ($id) {
            $plan = Plan::findOrFail($id);
            $this->planId          = $plan->id;
            $this->name            = $plan->name;
            $this->slug            = $plan->slug;
            $this->description     = $plan->description ?? '';
            $this->price_monthly   = (string) $plan->price_monthly;
            $this->price_yearly    = (string) $plan->price_yearly;
            $this->currency        = $plan->currency;
            $this->max_outlets     = (string) ($plan->max_outlets ?? '');
            $this->max_users       = (string) ($plan->max_users ?? '');
            $this->max_recipes     = (string) ($plan->max_recipes ?? '');
            $this->max_ingredients = (string) ($plan->max_ingredients ?? '');
            $this->max_lms_users   = (string) ($plan->max_lms_users ?? '');
            $this->trial_days      = (string) $plan->trial_days;
            $this->sort_order      = (string) $plan->sort_order;
            $this->is_active       = $plan->is_active;

            $flags = $plan->feature_flags ?? [];
            $this->flag_lms         = !empty($flags['lms']);
            $this->flag_reports     = !empty($flags['reports']);
            $this->flag_analytics   = !empty($flags['analytics']);
            $this->flag_ai_analysis = !empty($flags['ai_analysis']);
        }
    }

    public function updatedName(): void
    {
        if (!$this->planId) {
            $this->slug = Str::slug($this->name);
        }
    }

    protected function rules(): array
    {
        $uniqueSlug = 'unique:plans,slug' . ($this->planId ? ',' . $this->planId : '');

        return [
            'name'            => 'required|string|max:100',
            'slug'            => ['required', 'string', 'max:100', $uniqueSlug],
            'description'     => 'nullable|string|max:1000',
            'price_monthly'   => 'required|numeric|min:0',
            'price_yearly'    => 'required|numeric|min:0',
            'currency'        => 'required|string|size:3',
            'max_outlets'     => 'nullable|integer|min:1',
            'max_users'       => 'nullable|integer|min:1',
            'max_recipes'     => 'nullable|integer|min:1',
            'max_ingredients' => 'nullable|integer|min:1',
            'max_lms_users'   => 'nullable|integer|min:1',
            'trial_days'      => 'required|integer|min:0|max:365',
            'sort_order'      => 'required|integer|min:0',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name'            => $this->name,
            'slug'            => $this->slug,
            'description'     => $this->description ?: null,
            'price_monthly'   => $this->price_monthly,
            'price_yearly'    => $this->price_yearly,
            'currency'        => $this->currency,
            'max_outlets'     => $this->max_outlets !== '' ? (int) $this->max_outlets : null,
            'max_users'       => $this->max_users !== '' ? (int) $this->max_users : null,
            'max_recipes'     => $this->max_recipes !== '' ? (int) $this->max_recipes : null,
            'max_ingredients' => $this->max_ingredients !== '' ? (int) $this->max_ingredients : null,
            'max_lms_users'   => $this->max_lms_users !== '' ? (int) $this->max_lms_users : null,
            'trial_days'      => (int) $this->trial_days,
            'sort_order'      => (int) $this->sort_order,
            'is_active'       => $this->is_active,
            'feature_flags'   => [
                'lms'         => $this->flag_lms,
                'reports'     => $this->flag_reports,
                'analytics'   => $this->flag_analytics,
                'ai_analysis' => $this->flag_ai_analysis,
            ],
        ];

        if ($this->planId) {
            Plan::findOrFail($this->planId)->update($data);
            session()->flash('success', 'Plan updated.');
        } else {
            Plan::create($data);
            session()->flash('success', 'Plan created.');
        }

        $this->redirect(route('admin.plans.index'), navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.plans.form')
            ->layout('layouts.app', ['title' => $this->planId ? 'Edit Plan' : 'Create Plan']);
    }
}
