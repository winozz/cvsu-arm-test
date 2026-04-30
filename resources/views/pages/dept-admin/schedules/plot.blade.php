<?php

use App\Models\CurriculumEntry;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ScheduleRoomTime;
use App\Models\User;
use App\Services\ScheduleConflictService;
use App\Services\SchedulePlottingService;
use App\Traits\CanManage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    // Plot form
    public ?int $plotScheduleId = null;
    public string $plotClassType = 'LEC';
    public ?string $plotDay = null;
    public ?string $plotTimeIn = null;
    public ?string $plotTimeOut = null;
    public ?int $plotFacultyId = null;
    public ?int $plotRoomId = null;

    public bool $facultyConflict = false;
    public bool $roomConflict = false;

    public function mount(): void
    {
        $this->ensureCanManage('schedules.assign');

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

        $requestedScheduleId = request()->integer('schedule');

        if ($requestedScheduleId > 0) {
            $this->plotScheduleId = $requestedScheduleId;
        }
    }

    #[Computed]
    public function scheduleOptions(): array
    {
        return Schedule::query()
            ->with(['sections:id,schedule_id,computed_section_name', 'subject:id,code'])
            ->where(function ($query) {
                $query->where(function ($localScope) {
                    $localScope
                        ->where('campus_id', $this->campusId)
                        ->where('college_id', $this->collegeId)
                        ->when($this->departmentId !== null, fn($departmentScope) => $departmentScope->where('department_id', $this->departmentId));
                });

                if ($this->departmentId !== null) {
                    $query->orWhereHas('serviceRequests', function ($requestQuery) {
                        $requestQuery->where('assigned_department_id', $this->departmentId)->whereIn('schedule_service_requests.status', ['assigned_to_dept', 'dept_submitted']);
                    });
                }
            })
            ->whereIn('status', ['draft', 'pending_plotting'])
            ->orderBy('sched_code')
            ->get()
            ->map(
                fn($s) => [
                    'label' => $s->sched_code . ' — ' . ($s->subject?->code ?? '?') . ' / ' . ($s->sections->first()?->computed_section_name ?? '—'),
                    'value' => $s->id,
                ],
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function classTypeOptions(): array
    {
        return collect(ScheduleRoomTime::CLASS_TYPES)->map(fn($t) => ['label' => $t, 'value' => $t])->values()->toArray();
    }

    #[Computed]
    public function dayOptions(): array
    {
        return collect(ScheduleRoomTime::DAYS)->map(fn($d) => ['label' => $d, 'value' => $d])->values()->toArray();
    }

    #[Computed]
    public function facultyOptions(): array
    {
        return User::query()
            ->whereHas('facultyProfile', function ($q) {
                $q->where('campus_id', $this->campusId)
                    ->where('college_id', $this->collegeId)
                    ->when($this->departmentId !== null, fn($q2) => $q2->where('department_id', $this->departmentId));
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($u) => ['label' => $u->name, 'value' => $u->id])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function roomOptions(): array
    {
        return Room::query()
            ->where('campus_id', $this->campusId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(
                fn($r) => [
                    'label' => $r->name,
                    'value' => $r->id,
                ],
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function plottedSchedules()
    {
        return Schedule::query()
            ->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name', 'roomTimes:id,schedule_id,class_type,day,time_in,time_out', 'facultyAssignments.user:id,name', 'roomTimes.room:id,name'])
            ->where('campus_id', $this->campusId)
            ->where('college_id', $this->collegeId)
            ->when($this->departmentId !== null, fn($q) => $q->where('department_id', $this->departmentId))
            ->where('status', 'plotted')
            ->latest()
            ->limit(30)
            ->get();
    }

    public function updatedPlotFacultyId(): void
    {
        $this->checkConflicts();
    }
    public function updatedPlotRoomId(): void
    {
        $this->checkConflicts();
    }
    public function updatedPlotDay(): void
    {
        $this->checkConflicts();
    }
    public function updatedPlotTimeIn(): void
    {
        $this->checkConflicts();
    }
    public function updatedPlotTimeOut(): void
    {
        $this->checkConflicts();
    }
    public function updatedPlotClassType(): void
    {
        $this->checkConflicts();
    }

    private function checkConflicts(): void
    {
        $this->facultyConflict = false;
        $this->roomConflict = false;

        if (!$this->plotDay || !$this->plotTimeIn || !$this->plotTimeOut) {
            return;
        }

        $conflict = app(ScheduleConflictService::class);

        if ($this->plotFacultyId) {
            $this->facultyConflict = $conflict->hasFacultyConflict($this->plotFacultyId, $this->plotDay, $this->plotTimeIn, $this->plotTimeOut, $this->plotClassType, $this->plotScheduleId);
        }

        if ($this->plotRoomId) {
            $this->roomConflict = $conflict->hasRoomConflict($this->plotRoomId, $this->plotDay, $this->plotTimeIn, $this->plotTimeOut, $this->plotScheduleId);
        }
    }

    public function plot(): void
    {
        $this->ensureCanManage('schedules.assign');

        $validated = $this->validate([
            'plotScheduleId' => ['required', 'integer', 'exists:schedules,id'],
            'plotClassType' => ['required', Rule::in(ScheduleRoomTime::CLASS_TYPES)],
            'plotDay' => ['nullable', Rule::in(ScheduleRoomTime::DAYS)],
            'plotTimeIn' => ['nullable', 'date_format:H:i'],
            'plotTimeOut' => ['nullable', 'date_format:H:i', 'after:plotTimeIn'],
            'plotFacultyId' => ['nullable', 'integer', 'exists:users,id'],
            'plotRoomId' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);

        try {
            app(SchedulePlottingService::class)->plot(
                (int) $validated['plotScheduleId'],
                array_filter(
                    [
                        'class_type' => $validated['plotClassType'],
                        'day' => $validated['plotDay'] ?? null,
                        'time_in' => $validated['plotTimeIn'] ?? null,
                        'time_out' => $validated['plotTimeOut'] ?? null,
                        'user_id' => $validated['plotFacultyId'] ?? null,
                        'room_id' => $validated['plotRoomId'] ?? null,
                    ],
                    fn($v) => $v !== null,
                ),
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
            return;
        }

        $this->reset(['plotScheduleId', 'plotFacultyId', 'plotRoomId']);
        $this->facultyConflict = false;
        $this->roomConflict = false;
        $this->toast()->success('Plotted', 'Schedule has been plotted.')->send();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Plot Schedule</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $campusName }} | {{ $collegeName }} | {{ $departmentName }}
            </p>
        </div>
        <a href="{{ route('schedules.service-requests') }}"
            class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to Schedule Assignments
        </a>
    </div>

    <x-card>
        <div class="space-y-4">
            <h2 class="text-base font-semibold dark:text-white">Assign Faculty &amp; Room</h2>

            @if ($facultyConflict)
                <div
                    class="rounded-md border border-red-300 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-700 dark:bg-red-900/30 dark:text-red-300">
                    <strong>Conflict:</strong> The selected faculty already has a class during this time block.
                </div>
            @endif

            @if ($roomConflict)
                <div
                    class="rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                    <strong>Conflict:</strong> The selected room is occupied during this time block.
                </div>
            @endif

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div class="md:col-span-2 lg:col-span-3">
                    <x-select.styled label="Schedule" wire:model="plotScheduleId" :options="$this->scheduleOptions"
                        select="label:label|value:value" searchable />
                </div>

                <x-select.styled label="Class Type" wire:model.live="plotClassType" :options="$this->classTypeOptions"
                    select="label:label|value:value" />
                <x-select.styled label="Day" wire:model.live="plotDay" :options="$this->dayOptions"
                    select="label:label|value:value" />
                <x-input label="Time In" type="time" wire:model.live="plotTimeIn" />
                <x-input label="Time Out" type="time" wire:model.live="plotTimeOut" />

                <x-select.styled label="Faculty" wire:model.live="plotFacultyId" :options="$this->facultyOptions"
                    select="label:label|value:value" searchable />
                <x-select.styled label="Room" wire:model.live="plotRoomId" :options="$this->roomOptions"
                    select="label:label|value:value" searchable />
            </div>

            <div class="flex justify-end">
                <x-button color="primary" text="Plot Schedule" wire:click="plot" />
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-3">
            <h2 class="text-base font-semibold dark:text-white">Recently Plotted</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                    <thead>
                        <tr
                            class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            <th class="px-3 py-2">Code</th>
                            <th class="px-3 py-2">Subject</th>
                            <th class="px-3 py-2">Section</th>
                            <th class="px-3 py-2">Schedule Details</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->plottedSchedules as $schedule)
                            <tr>
                                <td class="px-3 py-2 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $schedule->sched_code }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">{{ $schedule->subject?->code }} –
                                    {{ $schedule->subject?->title }}</td>
                                <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                    {{ $schedule->sections->first()?->computed_section_name ?? '—' }}</td>
                                <td class="px-3 py-2 text-zinc-500 dark:text-zinc-400">
                                    @foreach ($schedule->roomTimes as $rt)
                                        <div>{{ $rt->class_type }} | {{ $rt->day }}
                                            {{ substr($rt->time_in, 0, 5) }}–{{ substr($rt->time_out, 0, 5) }} |
                                            {{ $rt->room?->name ?? '—' }}</div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-center text-zinc-400">No plotted schedules yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </x-card>
</div>
