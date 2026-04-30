<?php

use App\Models\CurriculumEntry;
use App\Models\Schedule;
use App\Models\Subject;
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

    // Form fields
    public ?int $customSubjectId = null;
    public string $customProgramCode = '';
    public ?int $customYearLevel = null;
    public string $customSectionIdentifier = '';
    public string $customSectionType = 'IRREGULAR';
    public string $customSemester = '1ST';
    public string $customSchoolYear = '';
    public int $customSlots = 40;
    public ?string $customNstpTrack = null;

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
        $this->customSchoolYear = $year . '-' . ($year + 1);
    }

    #[Computed]
    public function subjectOptions(): array
    {
        return Subject::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn($s) => ['label' => $s->code . ' – ' . $s->title, 'value' => $s->id])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function sectionTypeOptions(): array
    {
        return collect(['IRREGULAR', 'PETITION', 'NSTP', 'OTHERS'])
            ->map(fn($t) => ['label' => $t, 'value' => $t])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function semesterOptions(): array
    {
        return collect(CurriculumEntry::SEMESTERS)->map(fn($label, $value) => ['label' => $label, 'value' => $value])->values()->toArray();
    }

    #[Computed]
    public function nstpTrackOptions(): array
    {
        return [['label' => 'CWTS', 'value' => 'CWTS'], ['label' => 'ROTC', 'value' => 'ROTC']];
    }

    public function createCustom(): void
    {
        $this->ensureCanManage('schedules.create');

        $validated = $this->validate([
            'customSubjectId' => ['required', 'integer', 'exists:subjects,id'],
            'customProgramCode' => ['required', 'string', 'max:20'],
            'customYearLevel' => ['nullable', 'integer', 'min:1', 'max:8'],
            'customSectionIdentifier' => ['required', 'string', 'max:20'],
            'customSectionType' => ['required', Rule::in(['IRREGULAR', 'PETITION', 'NSTP', 'OTHERS'])],
            'customSemester' => ['required', Rule::in(array_keys(CurriculumEntry::SEMESTERS))],
            'customSchoolYear' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'customSlots' => ['required', 'integer', 'min:1', 'max:500'],
            'customNstpTrack' => ['nullable', Rule::in(['CWTS', 'ROTC'])],
        ]);

        $schedule = app(ScheduleGenerationService::class)->createCustomSectionSchedule([
            'campus_id' => $this->campusId,
            'college_id' => $this->collegeId,
            'department_id' => $this->departmentId,
            'subject_id' => $validated['customSubjectId'],
            'program_code' => $validated['customProgramCode'],
            'year_level' => $validated['customYearLevel'],
            'section_identifier' => $validated['customSectionIdentifier'],
            'section_type' => $validated['customSectionType'],
            'semester' => $validated['customSemester'],
            'school_year' => $validated['customSchoolYear'],
            'slots' => $validated['customSlots'],
            'nstp_track' => $validated['customNstpTrack'],
        ]);

        $this->reset(['customSubjectId', 'customProgramCode', 'customYearLevel', 'customSectionIdentifier', 'customNstpTrack']);
        $this->toast()
            ->success('Created', 'Section schedule ' . $schedule->sched_code . ' created.')
            ->send();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Custom Section Creation</h1>
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
                <h2 class="text-base font-semibold dark:text-white">Create Custom Section</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Create a single custom schedule for irregular,
                    petition, NSTP, or other non-standard sections.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div class="md:col-span-2 lg:col-span-3">
                    <x-select.styled label="Subject" wire:model="customSubjectId" :options="$this->subjectOptions"
                        select="label:label|value:value" searchable />
                </div>

                <x-input label="Program Code" wire:model="customProgramCode" placeholder="e.g. BSIT" />
                <x-input label="Year Level (optional)" type="number" wire:model="customYearLevel" min="1"
                    max="8" />
                <x-input label="Section Identifier" wire:model="customSectionIdentifier"
                    placeholder="e.g. A, IRC, PET1" />

                <x-select.styled label="Section Type" wire:model="customSectionType" :options="$this->sectionTypeOptions"
                    select="label:label|value:value" />
                <x-select.styled label="Semester" wire:model="customSemester" :options="$this->semesterOptions"
                    select="label:label|value:value" />
                <x-input label="School Year (YYYY-YYYY)" wire:model="customSchoolYear" />
                <x-input label="Slots" type="number" wire:model="customSlots" min="1" max="500" />

                @if ($customSectionType === 'NSTP')
                    <x-select.styled label="NSTP Track" wire:model="customNstpTrack" :options="$this->nstpTrackOptions"
                        select="label:label|value:value" />
                @endif
            </div>

            <div class="flex justify-end">
                <x-button color="primary" text="Create Section" wire:click="createCustom" />
            </div>
        </div>
    </x-card>
</div>
