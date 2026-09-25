<?php

namespace App\Livewire\Audits;

use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The form builder.
 *
 * One section on screen at a time — a ROSE form is three hundred items and a
 * page that renders all of them with an input each is not a page anyone can
 * edit on. Items are edited inline; every field saves on blur, so there is no
 * Save button to forget and no half-edited form to lose.
 *
 * Only the structure bumps `version`: labels, points, order, sections. Header
 * fields and the description do not — an audit copies those at start too, so
 * nothing downstream reads them by version.
 */
class TemplateEdit extends Component
{
    #[Locked]
    public int $templateId;

    public ?int $sectionId = null;

    // Template header
    public string $name = '';
    public string $code = '';
    public string $description = '';
    public string $altLanguage = '';
    public bool   $requiresAcknowledgement = true;

    /** @var array<int, array{key:string,label:string,type:string,required:bool}> */
    public array $headerFields = [];

    // Section being edited
    public string $sectionName = '';
    public string $sectionNameAlt = '';
    public string $sectionMode = 'area';

    // Items of the current section, keyed by id, flat with depth.
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public function mount(int $id): void
    {
        $template = AuditTemplate::with('sections')->findOrFail($id);

        $this->templateId  = $template->id;
        $this->name        = $template->name;
        $this->code        = $template->code ?? '';
        $this->description = $template->description ?? '';
        $this->altLanguage = $template->alt_language ?? '';
        $this->requiresAcknowledgement = $template->requires_acknowledgement;
        $this->headerFields = $template->headerFieldList();

        $this->sectionId = $template->sections->first()?->id;
        $this->loadSection();
    }

    private function template(): AuditTemplate
    {
        return AuditTemplate::findOrFail($this->templateId);
    }

    private function section(): ?AuditTemplateSection
    {
        if (! $this->sectionId) {
            return null;
        }

        return AuditTemplateSection::where('audit_template_id', $this->templateId)->find($this->sectionId);
    }

    private function bumpVersion(): void
    {
        AuditTemplate::whereKey($this->templateId)->increment('version');
    }

    // ── Template header ──────────────────────────────────────────────────

    public function saveHeader(): void
    {
        $this->validate([
            'name'        => 'required|string|max:120',
            'code'        => 'nullable|string|max:20',
            'description' => 'nullable|string|max:2000',
            'altLanguage' => 'nullable|string|max:40',
            'headerFields.*.label' => 'required|string|max:80',
            'headerFields.*.type'  => 'in:' . implode(',', AuditTemplate::HEADER_TYPES),
        ], [
            'headerFields.*.label.required' => 'Every header field needs a label.',
        ]);

        $fields = collect($this->headerFields)->map(fn ($f, $i) => [
            'key'      => $f['key'] ?: \Illuminate\Support\Str::slug($f['label'], '_') ?: 'field_' . $i,
            'label'    => $f['label'],
            'type'     => $f['type'],
            'required' => (bool) ($f['required'] ?? false),
        ])->values()->all();

        $this->template()->update([
            'name'                     => $this->name,
            'code'                     => $this->code ?: null,
            'description'              => $this->description ?: null,
            'alt_language'             => $this->altLanguage ?: null,
            'requires_acknowledgement' => $this->requiresAcknowledgement,
            'header_fields'            => $fields,
        ]);

        $this->headerFields = $fields;

        session()->flash('success', 'Form details saved.');
    }

    public function addHeaderField(): void
    {
        $this->headerFields[] = ['key' => '', 'label' => '', 'type' => 'text', 'required' => false];
    }

    public function removeHeaderField(int $i): void
    {
        unset($this->headerFields[$i]);
        $this->headerFields = array_values($this->headerFields);
    }

    // ── Sections ─────────────────────────────────────────────────────────

    public function selectSection(int $id): void
    {
        $this->sectionId = $id;
        $this->loadSection();
    }

    public function addSection(): void
    {
        $order = (int) AuditTemplateSection::where('audit_template_id', $this->templateId)->max('sort_order') + 1;

        $section = AuditTemplateSection::create([
            'audit_template_id' => $this->templateId,
            'name'              => 'New section',
            'scoring_mode'      => AuditTemplateSection::MODE_AREA,
            'sort_order'        => $order,
        ]);

        $this->bumpVersion();
        $this->selectSection($section->id);
    }

    public function saveSection(): void
    {
        $section = $this->section();
        if (! $section) return;

        $this->validate([
            'sectionName'    => 'required|string|max:120',
            'sectionNameAlt' => 'nullable|string|max:120',
            'sectionMode'    => 'in:area,penalty',
        ]);

        $section->update([
            'name'         => $this->sectionName,
            'name_alt'     => $this->sectionNameAlt ?: null,
            'scoring_mode' => $this->sectionMode,
        ]);

        $this->bumpVersion();
    }

    public function moveSection(int $id, int $direction): void
    {
        $sections = AuditTemplateSection::where('audit_template_id', $this->templateId)->orderBy('sort_order')->get();
        $this->swap($sections, $id, $direction);
        $this->bumpVersion();
    }

    public function deleteSection(int $id): void
    {
        $section = AuditTemplateSection::where('audit_template_id', $this->templateId)->findOrFail($id);
        $section->items()->delete();
        $section->delete();

        $this->bumpVersion();

        if ($this->sectionId === $id) {
            $this->sectionId = AuditTemplateSection::where('audit_template_id', $this->templateId)->orderBy('sort_order')->value('id');
        }

        $this->loadSection();
    }

    private function loadSection(): void
    {
        $section = $this->section();

        $this->items = [];

        if (! $section) {
            $this->sectionName = $this->sectionNameAlt = '';
            return;
        }

        $this->sectionName    = $section->name;
        $this->sectionNameAlt = $section->name_alt ?? '';
        $this->sectionMode    = $section->scoring_mode;

        $all      = $section->items()->get();
        $byParent = $all->groupBy(fn ($i) => $i->parent_id ?? 0);

        $walk = function (?int $parentId, int $depth) use (&$walk, $byParent) {
            foreach ($byParent->get($parentId ?? 0, collect()) as $item) {
                $this->items[$item->id] = [
                    'id'        => $item->id,
                    'parent_id' => $item->parent_id,
                    'depth'     => $depth,
                    'number'    => $item->number ?? '',
                    'label'     => $item->label,
                    'label_alt' => $item->label_alt ?? '',
                    'hint'      => $item->hint ?? '',
                    'type'      => $item->type,
                    'info_type' => $item->info_type ?? 'text',
                    'points'    => $item->points,
                    'has_children' => $byParent->has($item->id),
                ];
                $walk($item->id, $depth + 1);
            }
        };

        $walk(null, 0);
    }

    // ── Items ────────────────────────────────────────────────────────────

    public function addItem(?int $parentId = null): void
    {
        $section = $this->section();
        if (! $section) return;

        if ($parentId) {
            $parent = AuditTemplateItem::where('audit_template_section_id', $section->id)->findOrFail($parentId);
            // Two levels only: a heading and its lettered sub-items. That is
            // as deep as any printed audit form goes, and a third level is
            // unreadable on a phone.
            abort_if($parent->parent_id !== null, 422);
        }

        $siblings = AuditTemplateItem::where('audit_template_section_id', $section->id)
            ->where('parent_id', $parentId)->orderBy('sort_order')->get();

        $number = $parentId
            ? chr(ord('a') + min(25, $siblings->count()))
            : (string) ($siblings->count() + 1);

        // Sort order is section-wide and children follow their parent; the
        // simplest correct thing is to renumber the whole section after.
        $item = AuditTemplateItem::create([
            'audit_template_section_id' => $section->id,
            'parent_id'  => $parentId,
            'number'     => $number,
            'label'      => $parentId ? 'New sub-item' : 'New item',
            'type'       => AuditTemplateItem::TYPE_CHECK,
            'points'     => 2,
            'sort_order' => 9999,
        ]);

        $this->renumberSection($section);
        $this->bumpVersion();
        $this->loadSection();

        $this->dispatch('audit-item-added', id: $item->id);
    }

    /** Any field of an item, on blur. */
    public function updatedItems($value, string $key): void
    {
        [$id, $field] = explode('.', $key, 2) + [null, null];

        if (! $id || ! $field || ! isset($this->items[(int) $id])) {
            return;
        }

        $item = AuditTemplateItem::whereHas('section', fn ($q) => $q->where('audit_template_id', $this->templateId))
            ->find((int) $id);

        if (! $item) {
            return;
        }

        switch ($field) {
            case 'label':
                $value = trim((string) $value);
                if ($value === '') { $this->loadSection(); return; }
                $item->label = mb_substr($value, 0, 500);
                break;
            case 'label_alt':
                $item->label_alt = trim((string) $value) !== '' ? mb_substr(trim($value), 0, 500) : null;
                break;
            case 'number':
                $item->number = trim((string) $value) !== '' ? mb_substr(trim($value), 0, 10) : null;
                break;
            case 'hint':
                $item->hint = trim((string) $value) !== '' ? mb_substr(trim($value), 0, 255) : null;
                break;
            case 'points':
                $item->points = max(0, min(999, (int) $value));
                break;
            case 'type':
                if (! in_array($value, AuditTemplateItem::TYPES, true)) { $this->loadSection(); return; }
                $item->type = $value;
                if ($value === AuditTemplateItem::TYPE_INFO) {
                    $item->points = 0;
                    $item->info_type = $item->info_type ?: 'text';
                }
                break;
            case 'info_type':
                if (! in_array($value, AuditTemplateItem::INFO_TYPES, true)) { $this->loadSection(); return; }
                $item->info_type = $value;
                break;
            default:
                return;
        }

        $item->save();
        $this->bumpVersion();
        $this->items[(int) $id][$field] = $item->{$field} ?? '';
    }

    public function moveItem(int $id, int $direction): void
    {
        $section = $this->section();
        if (! $section) return;

        $item = AuditTemplateItem::where('audit_template_section_id', $section->id)->findOrFail($id);

        $siblings = AuditTemplateItem::where('audit_template_section_id', $section->id)
            ->where('parent_id', $item->parent_id)->orderBy('sort_order')->get();

        $this->swap($siblings, $id, $direction);
        $this->renumberSection($section);
        $this->bumpVersion();
        $this->loadSection();
    }

    public function deleteItem(int $id): void
    {
        $section = $this->section();
        if (! $section) return;

        $item = AuditTemplateItem::where('audit_template_section_id', $section->id)->findOrFail($id);
        $item->children()->delete();
        $item->delete();

        $this->renumberSection($section);
        $this->bumpVersion();
        $this->loadSection();
    }

    /**
     * Rewrite sort_order so it reads top-to-bottom with children under their
     * parent, and re-letter sub-items. Numbers on top-level items are left
     * alone — a form's "8." may be deliberate after a deletion.
     */
    private function renumberSection(AuditTemplateSection $section): void
    {
        $all      = $section->items()->get();
        $byParent = $all->groupBy(fn ($i) => $i->parent_id ?? 0);
        $order    = 0;

        DB::transaction(function () use ($byParent, &$order) {
            foreach ($byParent->get(0, collect()) as $parent) {
                $parent->forceFill(['sort_order' => $order++])->save();

                foreach ($byParent->get($parent->id, collect())->values() as $i => $child) {
                    $child->forceFill([
                        'sort_order' => $order++,
                        'number'     => $i < 26 ? chr(ord('a') + $i) : (string) ($i + 1),
                    ])->save();
                }
            }
        });
    }

    /** Swap a row with its neighbour in a sorted collection and persist the new order. */
    private function swap($rows, int $id, int $direction): void
    {
        $rows  = $rows->values();
        $index = $rows->search(fn ($r) => $r->id === $id);

        if ($index === false) return;

        $target = $index + ($direction < 0 ? -1 : 1);

        if ($target < 0 || $target >= $rows->count()) return;

        $a = $rows[$index];
        $b = $rows[$target];

        [$a->sort_order, $b->sort_order] = [$b->sort_order, $a->sort_order];

        if ($a->sort_order === $b->sort_order) {
            $a->sort_order = $target;
            $b->sort_order = $index;
        }

        $a->save();
        $b->save();
    }

    public function render()
    {
        $template = AuditTemplate::with('sections')->findOrFail($this->templateId);

        $sectionPoints = [];
        foreach ($template->sections as $s) {
            $sectionPoints[$s->id] = (int) AuditTemplateItem::where('audit_template_section_id', $s->id)
                ->whereNotIn('id', fn ($q) => $q->select('parent_id')->from('audit_template_items')->whereNotNull('parent_id'))
                ->sum('points');
        }

        return view('livewire.audits.template-edit', [
            'template'      => $template,
            'sections'      => $template->sections,
            'sectionPoints' => $sectionPoints,
            'headerTypes'   => AuditTemplate::HEADER_TYPES,
            'itemTypes'     => AuditTemplateItem::TYPES,
            'infoTypes'     => AuditTemplateItem::INFO_TYPES,
        ])->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Audit Form']);
    }
}
