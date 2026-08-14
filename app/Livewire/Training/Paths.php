<?php

namespace App\Livewire\Training;

use App\Models\TrainingCourse;
use App\Models\TrainingPath;
use App\Models\TrainingPathItem;
use App\Traits\RequiresActiveCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Learning paths and what is in them.
 *
 * List on the left, contents on the right — the same shape as Print Sets,
 * because it is the same job: a named ordered collection whose order is the
 * point.
 */
class Paths extends Component
{
    use RequiresActiveCompany;

    public ?int $editingPathId = null;

    // Path create/rename modal
    public bool $showModal = false;
    public ?int $modalPathId = null;
    public string $name = '';
    public string $description = '';
    public string $status = 'draft';
    public bool $unlocksSequentially = true;
    public ?int $targetDays = null;

    public ?int $addCourseId = null;

    public function mount(): void
    {
        $this->requireActiveCompany();

        $this->editingPathId = TrainingPath::orderBy('name')->value('id');
    }

    public function openCreate(): void
    {
        $this->reset(['modalPathId', 'name', 'description', 'targetDays']);
        $this->status              = 'draft';
        $this->unlocksSequentially = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $path = TrainingPath::findOrFail($id);

        $this->modalPathId         = $path->id;
        $this->name                = $path->name;
        $this->description         = (string) $path->description;
        $this->status              = $path->status;
        $this->unlocksSequentially = $path->unlocks_sequentially;
        $this->targetDays          = $path->target_days;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function savePath(): void
    {
        $this->authorize('training.manage');

        $data = $this->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status'      => ['required', Rule::in(array_keys(TrainingPath::STATUSES))],
            'targetDays'  => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $path = $this->modalPathId
            ? TrainingPath::findOrFail($this->modalPathId)
            : new TrainingPath(['company_id' => Auth::user()->company_id, 'created_by' => Auth::id()]);

        $path->fill([
            'name'                 => $data['name'],
            'description'          => $data['description'] ?: null,
            'status'               => $data['status'],
            'unlocks_sequentially' => $this->unlocksSequentially,
            'target_days'          => $data['targetDays'],
        ])->save();

        $this->editingPathId = $path->id;
        $this->showModal     = false;

        session()->flash('success', "\"{$path->name}\" saved.");
    }

    public function deletePath(int $id): void
    {
        $this->authorize('training.manage');

        $path = TrainingPath::findOrFail($id);
        $name = $path->name;
        $path->delete();

        if ($this->editingPathId === $id) {
            $this->editingPathId = TrainingPath::orderBy('name')->value('id');
        }

        session()->flash('success', "\"{$name}\" was deleted.");
    }

    public function selectPath(int $id): void
    {
        $this->editingPathId = TrainingPath::findOrFail($id)->id;
    }

    public function addCourse(): void
    {
        $this->authorize('training.manage');

        if (! $this->editingPathId || ! $this->addCourseId) {
            return;
        }

        $path   = TrainingPath::findOrFail($this->editingPathId);
        $course = TrainingCourse::findOrFail($this->addCourseId);

        // firstOrCreate, not create: the unique index is the real guard and a
        // double-click must not be an error page.
        TrainingPathItem::firstOrCreate(
            ['training_path_id' => $path->id, 'training_course_id' => $course->id],
            ['sort_order' => (int) $path->items()->max('sort_order') + 1],
        );

        $this->addCourseId = null;

        session()->flash('success', "Added \"{$course->title}\".");
    }

    public function removeItem(int $itemId): void
    {
        $this->authorize('training.manage');

        $this->item($itemId)->delete();
    }

    public function toggleRequired(int $itemId): void
    {
        $this->authorize('training.manage');

        $item = $this->item($itemId);
        $item->update(['is_required' => ! $item->is_required]);
    }

    /** Same swap-by-position approach as the quiz builder, for the same reason. */
    public function move(int $itemId, string $direction): void
    {
        $this->authorize('training.manage');

        $path  = TrainingPath::findOrFail($this->editingPathId);
        $items = $path->items()->get();
        $at    = $items->search(fn (TrainingPathItem $i) => $i->id === $itemId);

        if ($at === false) {
            return;
        }

        $target = $direction === 'up' ? $at - 1 : $at + 1;

        if ($target < 0 || $target >= $items->count()) {
            return;
        }

        $a = $items[$at];
        $b = $items[$target];

        if ($a->sort_order === $b->sort_order) {
            $items->values()->each(fn ($i, $n) => $i->update(['sort_order' => $n]));
            $a = $a->fresh();
            $b = $b->fresh();
        }

        [$aOrder, $bOrder] = [$a->sort_order, $b->sort_order];
        $a->update(['sort_order' => $bOrder]);
        $b->update(['sort_order' => $aOrder]);
    }

    /** Scoped by the open path, so an item id from elsewhere 404s. */
    private function item(int $id): TrainingPathItem
    {
        return TrainingPathItem::where('training_path_id', $this->editingPathId)->findOrFail($id);
    }

    public function render()
    {
        $paths = TrainingPath::withCount('items')->orderBy('name')->get();

        $path = $this->editingPathId
            ? TrainingPath::with(['items.course:id,title,estimated_minutes,status'])->find($this->editingPathId)
            : null;

        $courses = TrainingCourse::published()
            ->when($path, fn ($q) => $q->whereNotIn('id', $path->items->pluck('training_course_id')))
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('livewire.training.paths', compact('paths', 'path', 'courses'))
            ->layout(\App\Helpers\WorkspaceLayout::get(), ['title' => 'Learning paths']);
    }
}
