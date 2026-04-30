<?php

use App\Models\CurriculumEntry;
use App\Models\Program;
use App\Models\Schedule;
use App\Services\ScheduleGenerationService;
use App\Traits\CanManage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public int $campusId;
    public int $collegeId;
    public ?int $departmentId = null;
    public string $campusName = '-';
    public string $collegeName = '-';
    public string $departmentName = '-';

    public ?int $bulkProgramId = null;
    public ?int $bulkYearLevel = null;
    public string $bulkSemester = '1ST';
    public string $bulkSchoolYear = '';
    public int $bulkSectionCount = 1;
    public int $bulkSlots = 40;

    public function mount(): void
    {
        $this->ensureCanManage('schedules.create');

        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.campus', 'employeeProfile.college', 'employeeProfile.department', 'facultyProfile.campus', 'facultyProfile.college', 'facultyProfile.department']);
        $profile = $user?->assignedAcademicProfile();

        abort_unless($profile && filled($profile->campus_id) && filled($profile->college_id), 403);

        $this->campusId = (int) $profile->campus_id;
        $this->collegeId = (int) $profile->college_id;
        $this->departmentId = filled($profile->department_id) ? (int) $profile->department_id : null;
        $this->campusName = $profile->campus?->name ?? '-';
        $this->collegeName = $profile->college?->name ?? '-';
        $this->departmentName = $profile->department?->name ?? 'College-wide';

        $year = (int) now()->format('Y');
        $this->bulkSchoolYear = $year . '-' . ($year + 1);
    }

    #[Computed]
    public function programOptions(): array
    {
        return Program::query()
            ->whereHas('colleges', fn($q) => $q->where('colleges.id', $this->collegeId))
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn($p) => ['label' => $p->code . ' – ' . $p->title, 'value' => $p->id])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return collect(CurriculumEntry::SEMESTERS)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->toArray();
    }

    #[Computed]
    public function recentSchedules()
    {
        return Schedule::query()
            ->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name'])
            ->where('campus_id', $this->campusId)
            ->where('college_id', $this->collegeId)
            ->when($this->departmentId !== null, fn($q) => $q->where('department_id', $this->departmentId))
            ->where('status', 'draft')
            ->latest()
            ->limit(20)
            ->get();
    }

    public function generate(): void
    {
        $this->ensureCanManage('schedules.create');

        $validated = $this->validate([
            'bulkProgramId' => ['required', 'integer', 'exists:programs,id'],
            'bulkYearLevel' => ['required', 'integer', 'min:1', 'max:8'],
            'bulkSemester' => ['required', Rule::in(array_keys(CurriculumEntry::SEMESTERS))],
            'bulkSchoolYear' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'bulkSectionCount' => ['required', 'integer', 'min:1', 'max:30'],
            'bulkSlots' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $program = Program::query()->findOrFail((int) $validated['bulkProgramId']);

        $generated = app(ScheduleGenerationService::class)->generateBlockSchedules([
            'campus_id' => $this->campusId,
            'college_id' => $this->collegeId,
            'department_id' => $this->departmentId,
            'program_id' => $program->id,
            'program_code' => $program->code,
            'year_level' => (int) $validated['bulkYearLevel'],
            'semester' => $validated['bulkSemester'],
            'school_year' => $validated['bulkSchoolYear'],
            'section_count' => (int) $validated['bulkSectionCount'],
            'slots' => (int) $validated['bulkSlots'],
        ]);

        $this->reset(['bulkProgramId', 'bulkYearLevel', 'bulkSectionCount']);
        $this->toast()
            ->success('Generated', $generated->count() . ' schedule(s) created.')
            ->send();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Bulk Block Generation</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $campusName }} | {{ $collegeName }} | {{ $departmentName }}
            </p>
        </div>
        <a href="{{ route('schedules.index') }}"
            class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to Schedules
        </a>
    </div>

    <x-card>
        <div class="space-y-4">
            <div>
                <h2 class="text-base font-semibold dark:text-white">Generate Block Sections</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Creates one schedule per curriculum entry per
                    section, excluding NSTP1 and NSTP2 subjects.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <x-select.styled label="Program" wire:model="bulkProgramId" :options="$this->programOptions"
                    select="label:label|value:value" searchable />
                <x-input label="Year Level" type="number" wire:model="bulkYearLevel" min="1" max="8" />
                <x-select.styled label="Semester" wire:model="bulkSemester" :options="$this->semesterOptions"
                    select="label:label|value:value" />
                <x-input label="School Year (YYYY-YYYY)" wire:model="bulkSchoolYear" />
                <x-input label="Number of Sections" type="number" wire:model="bulkSectionCount" min="1"
                    max="30" />
                <x-input label="Slots per Section" type="number" wire:model="bulkSlots" min="1"
                    max="500" />
            </div>

            <div class="flex justify-end">
                <x-button color="primary" text="Generate Block Schedules" wire:click="generate" />
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-3">
            <h2 class="text-base font-semibold dark:text-white">Recently Generated (Draft)</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                    <thead>
                        <tr
                            class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Subject</th>
                            <th class="px-3 py-2">Section</th>
                            <th class="px-3 py-2">Semester / Year</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->recentSchedules as $schedule)
                            <tr>
                                <td class="px-3 py-2 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $schedule->sched_code }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $schedule->subject?->code }} –
                                    {{ $schedule->subject?->title }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ $schedule->sections->first()?->computed_section_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">{{ $schedule->semester }} /
                                    {{ $schedule->school_year }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-zinc-400">No draft schedules found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-card>
</div>
