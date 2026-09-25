<?php

namespace App\Livewire\Audits;

use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Support\Audits\RoseTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * The audit forms a company owns. Create a blank one, install the ROSE
 * starter, duplicate, switch on and off, delete.
 */
class Templates extends Component
{
    public bool   $showCreate = false;
    public string $name       = '';
    public string $code       = '';
    public string $altLanguage = '';

    public function openCreate(): void
    {
        $this->reset(['name', 'code', 'altLanguage']);
        $this->resetErrorBag();
        $this->showCreate = true;
    }

    public function create()
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'code'        => 'nullable|string|max:20',
            'altLanguage' => 'nullable|string|max:40',
        ]);

        $template = AuditTemplate::create([
            'company_id'    => Auth::user()->company_id,
            'name'          => $this->name,
            'code'          => $this->code ?: null,
            'alt_language'  => $this->altLanguage ?: null,
            'is_active'     => true,
            'header_fields' => [],
            'created_by'    => Auth::id(),
        ]);

        return $this->redirect(route('audits.templates.edit', $template->id), navigate: true);
    }

    /** The ROSE starter — see RoseTemplate for what it is and what was genericised. */
    public function installRose()
    {
        $template = RoseTemplate::install(Auth::user()->company, Auth::user());

        session()->flash('success', 'ROSE form installed. Rename the product slots and stock items to your own menu.');

        return $this->redirect(route('audits.templates.edit', $template->id), navigate: true);
    }

    public function duplicate(int $id)
    {
        $source = AuditTemplate::with('sections.items')->findOrFail($id);

        $copy = DB::transaction(function () use ($source) {
            $copy = $source->replicate(['version']);
            $copy->name    = $source->name . ' (copy)';
            $copy->version = 1;
            $copy->created_by = Auth::id();
            $copy->save();

            foreach ($source->sections as $section) {
                $newSection = $section->replicate();
                $newSection->audit_template_id = $copy->id;
                $newSection->save();

                $map = [];
                foreach ($section->items as $item) {
                    $newItem = $item->replicate();
                    $newItem->audit_template_section_id = $newSection->id;
                    $newItem->parent_id = $item->parent_id ? ($map[$item->parent_id] ?? null) : null;
                    $newItem->save();
                    $map[$item->id] = $newItem->id;
                }
            }

            return $copy;
        });

        return $this->redirect(route('audits.templates.edit', $copy->id), navigate: true);
    }

    public function toggleActive(int $id): void
    {
        $template = AuditTemplate::findOrFail($id);
        $template->update(['is_active' => ! $template->is_active]);
    }

    public function delete(int $id): void
    {
        abort_unless(Auth::user()?->canDo('audits.delete'), 403);

        AuditTemplate::findOrFail($id)->delete();

        session()->flash('success', 'Audit form deleted. Audits already conducted from it keep their own copy.');
    }

    public function render()
    {
        $templates = AuditTemplate::ordered()
            ->withCount(['sections', 'audits'])
            ->get()
            ->each(fn ($t) => $t->setAttribute('points', $t->totalPoints()));

        return view('livewire.audits.templates', [
            'templates' => $templates,
            'hasRose'   => $templates->contains(fn ($t) => $t->code === RoseTemplate::CODE),
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Audit Forms']);
    }
}
