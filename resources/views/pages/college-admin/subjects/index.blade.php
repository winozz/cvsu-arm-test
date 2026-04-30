<?php

use App\Livewire\Forms\Admin\SubjectForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Subject;
use App\Support\SubjectDuplicateDetector;
use App\Traits\CanManage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public College $college;

    public Campus $campus;

    public SubjectForm $subjectForm;

    public bool $subjectModal = false;

    public bool $isEditingSubject = false;

    public ?string $subjectDuplicateConflictType = null;

    public array $subjectExactDuplicateConflicts = [];

    public array $subjectSimilarDuplicateConflicts = [];

    public bool $subjectSimilarityConfirmed = false;

    public function mount(): void
    {
        $this->ensureCanManage('subjects.view');

        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.campus', 'employeeProfile.college', 'facultyProfile.campus', 'facultyProfile.college']);
        $profile = $user?->employeeProfile ?? $user?->facultyProfile;

        if (filled($profile?->campus_id) && filled($profile?->college_id) && $profile?->campus && $profile?->college) {
            $this->campus = $profile->campus;
            $this->college = $profile->college;

            return;
        }

        $this->resolveFallbackCollegeContext();
    }

    public function openCreateSubjectModal(): void
    {
        $this->ensureCanManage('subjects.create');

        $this->resetValidation();
        $this->subjectForm->resetForm();
        $this->isEditingSubject = false;
        $this->resetSubjectDuplicateState();
        $this->subjectModal = true;
    }

    #[On('openEditSubjectModal')]
    public function openEditSubjectModal(Subject $subject): void
    {
        $this->ensureCanManage('subjects.update');

        $this->resetValidation();
        $this->subjectForm->setSubject($subject);
        $this->isEditingSubject = true;
        $this->resetSubjectDuplicateState();
        $this->subjectModal = true;
    }

    public function closeSubjectModal(): void
    {
        $this->subjectModal = false;
        $this->isEditingSubject = false;
        $this->resetSubjectDuplicateState();
        $this->resetValidation();
        $this->subjectForm->resetForm();
    }

    public function reopenSubjectModal(): void
    {
        $this->subjectModal = true;
    }

    #[Computed]
    public function stats(): array
    {
        $baseQuery = Subject::query();

        return [
            'total' => (clone $baseQuery)->count(),
            'active' => (clone $baseQuery)->where('is_active', true)->count(),
            'inactive' => (clone $baseQuery)->where('is_active', false)->count(),
        ];
    }

    public function confirmSaveSubject(): void
    {
        $this->ensureCanManage($this->isEditingSubject ? 'subjects.update' : 'subjects.create');

        $this->subjectForm->validateForm();

        $excluding = $this->isEditingSubject ? $this->subjectForm->subject : null;
        $conflicts = SubjectDuplicateDetector::findConflicts($excluding, $this->subjectForm->code, $this->subjectForm->title);

        if ($conflicts['exact'] !== []) {
            $this->subjectDuplicateConflictType = 'exact';
            $this->subjectExactDuplicateConflicts = $conflicts['exact'];
            $this->subjectSimilarDuplicateConflicts = $conflicts['similar'];
            $this->subjectSimilarityConfirmed = false;

            return;
        }

        if (!$this->isEditingSubject && $conflicts['similar'] !== []) {
            $this->subjectDuplicateConflictType = 'similar';
            $this->subjectExactDuplicateConflicts = [];
            $this->subjectSimilarDuplicateConflicts = $conflicts['similar'];
            $this->subjectSimilarityConfirmed = false;

            return;
        }

        $this->resetSubjectDuplicateState();
        $this->subjectModal = false;

        $title = $this->isEditingSubject ? 'Save Changes?' : 'Create Subject?';
        $description = $this->isEditingSubject ? 'Are you sure you want to update this subject?' : 'Are you sure you want to create this subject?';
        $confirm = $this->isEditingSubject ? 'Yes, save changes' : 'Yes, create subject';

        $this->dialog()->question($title, $description)->confirm($confirm, 'saveSubject')->cancel('Cancel', 'reopenSubjectModal')->send();
    }

    public function proceedWithSimilarSubjectCreation(): void
    {
        $this->subjectSimilarityConfirmed = true;
        $this->resetSubjectDuplicateState();

        $this->saveSubject();
    }

    public function saveSubject(): void
    {
        $this->ensureCanManage($this->isEditingSubject ? 'subjects.update' : 'subjects.create');

        try {
            $validated = $this->subjectForm->validateForm();
            if (!$this->isEditingSubject) {
                Subject::create($this->subjectForm->payload($validated));
                $message = 'Subject created successfully.';
            } else {
                $this->subjectForm->subject->update($this->subjectForm->payload($validated));
                $message = 'Subject details updated successfully.';
            }

            $this->closeSubjectModal();
            $this->dispatch('pg:eventRefresh-subjectsTable');
            $this->toast()->success('Success', $message)->send();
        } catch (ValidationException $e) {
            $this->reopenSubjectModal();
            throw $e;
        } catch (Throwable $e) {
            $this->reopenSubjectModal();
            Log::error('Subject Save Failed: ' . $e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the subject.')->send();
        }
    }

    protected function resolveFallbackCollegeContext(): void
    {
        $fallbackCollege = College::query()->with('campus')->orderBy('name')->first();

        abort_unless($fallbackCollege?->campus, 403);

        $this->campus = $fallbackCollege->campus;
        $this->college = $fallbackCollege;
    }

    protected function resetSubjectDuplicateState(): void
    {
        $this->subjectDuplicateConflictType = null;
        $this->subjectExactDuplicateConflicts = [];
        $this->subjectSimilarDuplicateConflicts = [];
        $this->subjectSimilarityConfirmed = false;
    }

    public function dismissSubjectConflict(): void
    {
        $this->resetSubjectDuplicateState();
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">{{ $college->code }}</h1>
                <x-badge :text="$college->is_active ? 'Active' : 'Inactive'" :color="$college->is_active ? 'primary' : 'red'" round />
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">{{ $college->name }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Subjects</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
                </div>

                <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon icon="book-open" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
                    <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['active'] }}</p>
                </div>

                <div class="rounded-lg bg-green-50 p-2 text-green-600 dark:bg-green-950/40 dark:text-green-300">
                    <x-icon icon="check-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inactive</p>
                    <p class="mt-1 text-2xl font-bold text-red-500">{{ $this->stats['inactive'] }}</p>
                </div>

                <div class="rounded-lg bg-red-50 p-2 text-red-600 dark:bg-red-950/40 dark:text-red-300">
                    <x-icon icon="x-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Subject List</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Browse the shared subject catalog used across {{ $college->name }}.
                </p>
            </div>

            <div class="flex gap-2">
                @can('subjects.create')
                    <x-button wire:click="openCreateSubjectModal" sm color="primary" icon="plus" text="New Subject" />
                @endcan
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.college-admin.subjects-table />
        </div>
    </x-card>

    <x-modal wire="subjectModal" title="{{ $isEditingSubject ? 'Edit Subject Details' : 'New Subject' }}"
        size="5xl">
        <div class="space-y-4">
            @if ($subjectDuplicateConflictType !== null)
                @php $previewCount = 3; @endphp

                @if ($subjectDuplicateConflictType === 'exact')
                    <div
                        class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800/50 dark:bg-red-950/30">
                        <div class="flex items-start gap-3">
                            <x-icon icon="x-circle" class="mt-0.5 h-5 w-5 shrink-0 text-red-500" />
                            <div class="min-w-0 flex-1 space-y-3">
                                <div>
                                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">Exact Duplicate
                                        Found — Save Blocked</p>
                                    <p class="mt-0.5 text-sm text-red-700 dark:text-red-300">A subject with the exact
                                        same code already exists. Edit the code to make this entry unique.</p>
                                </div>

                                @if ($subjectExactDuplicateConflicts !== [])
                                    <div x-data="{ open: false }">
                                        <p
                                            class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">
                                            Exact matches</p>
                                        <ul class="space-y-1">
                                            @foreach (array_slice($subjectExactDuplicateConflicts, 0, $previewCount) as $conflict)
                                                <li
                                                    class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                                                    <span
                                                        class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-400"></span>
                                                    <span class="break-all">{{ $conflict }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if (count($subjectExactDuplicateConflicts) > $previewCount)
                                            <ul x-show="open" x-cloak class="mt-1 space-y-1">
                                                @foreach (array_slice($subjectExactDuplicateConflicts, $previewCount) as $conflict)
                                                    <li
                                                        class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                                                        <span
                                                            class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-400"></span>
                                                        <span class="break-all">{{ $conflict }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <button @click="open = !open" type="button"
                                                class="mt-2 text-xs font-medium text-red-600 underline underline-offset-2 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200">
                                                <span x-show="!open">See
                                                    {{ count($subjectExactDuplicateConflicts) - $previewCount }}
                                                    more&hellip;</span>
                                                <span x-show="open" x-cloak>Show less</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif

                                @if ($subjectSimilarDuplicateConflicts !== [])
                                    <div x-data="{ open: false }"
                                        class="border-t border-red-200 pt-2.5 dark:border-red-800/50">
                                        <p
                                            class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-red-600 dark:text-red-400">
                                            Other similar matches</p>
                                        <ul class="space-y-1">
                                            @foreach (array_slice($subjectSimilarDuplicateConflicts, 0, $previewCount) as $conflict)
                                                <li
                                                    class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                                                    <span
                                                        class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-400"></span>
                                                    <span class="break-all">{{ $conflict }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if (count($subjectSimilarDuplicateConflicts) > $previewCount)
                                            <ul x-show="open" x-cloak class="mt-1 space-y-1">
                                                @foreach (array_slice($subjectSimilarDuplicateConflicts, $previewCount) as $conflict)
                                                    <li
                                                        class="flex items-start gap-2 text-sm text-red-700 dark:text-red-300">
                                                        <span
                                                            class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-red-400"></span>
                                                        <span class="break-all">{{ $conflict }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <button @click="open = !open" type="button"
                                                class="mt-2 text-xs font-medium text-red-600 underline underline-offset-2 hover:text-red-800 dark:text-red-400 dark:hover:text-red-200">
                                                <span x-show="!open">See
                                                    {{ count($subjectSimilarDuplicateConflicts) - $previewCount }}
                                                    more&hellip;</span>
                                                <span x-show="open" x-cloak>Show less</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 flex justify-end border-t border-red-200 pt-3 dark:border-red-800/50">
                            <x-button flat sm text="Dismiss & Edit" wire:click="dismissSubjectConflict" />
                        </div>
                    </div>
                @else
                    <div
                        class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800/50 dark:bg-amber-950/30">
                        <div class="flex items-start gap-3">
                            <x-icon icon="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                            <div class="min-w-0 flex-1 space-y-3">
                                <div>
                                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Possible
                                        Duplicate Subjects Found</p>
                                    <p class="mt-0.5 text-sm text-amber-700 dark:text-amber-300">These existing subjects
                                        look similar to what you&rsquo;re creating. Review them before proceeding.</p>
                                </div>

                                @if ($subjectSimilarDuplicateConflicts !== [])
                                    <div x-data="{ open: false }">
                                        <ul class="space-y-1">
                                            @foreach (array_slice($subjectSimilarDuplicateConflicts, 0, $previewCount) as $conflict)
                                                <li
                                                    class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300">
                                                    <span
                                                        class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                                                    <span class="break-all">{{ $conflict }}</span>
                                                </li>
                                            @endforeach
                                        </ul>
                                        @if (count($subjectSimilarDuplicateConflicts) > $previewCount)
                                            <ul x-show="open" x-cloak class="mt-1 space-y-1">
                                                @foreach (array_slice($subjectSimilarDuplicateConflicts, $previewCount) as $conflict)
                                                    <li
                                                        class="flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300">
                                                        <span
                                                            class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"></span>
                                                        <span class="break-all">{{ $conflict }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <button @click="open = !open" type="button"
                                                class="mt-2 text-xs font-medium text-amber-600 underline underline-offset-2 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-200">
                                                <span x-show="!open">See
                                                    {{ count($subjectSimilarDuplicateConflicts) - $previewCount }}
                                                    more&hellip;</span>
                                                <span x-show="open" x-cloak>Show less</span>
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div
                            class="mt-3 flex items-center justify-end gap-2 border-t border-amber-200 pt-3 dark:border-amber-800/50">
                            <x-button flat sm text="Go Back" wire:click="dismissSubjectConflict" />
                            <x-button sm color="warning" text="Proceed Anyway"
                                wire:click="proceedWithSimilarSubjectCreation" />
                        </div>
                    </div>
                @endif
            @endif

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Subject Code" wire:model="subjectForm.code"
                    hint="Use a short code like ITEC101 or MATH10." />
                <x-input label="Subject Title" wire:model="subjectForm.title" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Lecture Units" type="number" min="0"
                    wire:model="subjectForm.lecture_units" />
                <x-input label="Laboratory Units" type="number" min="0"
                    wire:model="subjectForm.laboratory_units" />
            </div>

            <x-textarea label="Description" wire:model="subjectForm.description"
                hint="Optional short description for this subject." />

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <x-toggle wire:model="subjectForm.is_credit" label="Credit subject" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $subjectForm->is_credit ? 'This subject counts toward credit hours.' : 'This subject is non-credit.' }}
                    </p>
                </div>

                <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <x-toggle wire:model="subjectForm.is_active" label="Subject is active" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $subjectForm->is_active ? 'This subject is available for active assignments.' : 'This subject will be marked inactive.' }}
                    </p>
                </div>
            </div>
        </div>

        <x-slot:footer>
            @canany(['subjects.update', 'subjects.create'])
                <x-button flat text="Cancel" wire:click="closeSubjectModal" sm />
                <x-button color="primary" :text="$isEditingSubject ? 'Save Changes' : 'Create Subject'" wire:click="confirmSaveSubject" sm :disabled="$subjectDuplicateConflictType === 'exact'" />
            @endcanany
        </x-slot:footer>
    </x-modal>
</div>
