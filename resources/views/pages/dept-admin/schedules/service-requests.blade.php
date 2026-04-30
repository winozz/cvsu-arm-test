<?php

use App\Models\ScheduleServiceRequest;
use App\Services\ScheduleWorkflowService;
use App\Traits\CanManage;
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

    public function mount(): void
    {
        $this->ensureCanManage('schedules.view');

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
    }

    #[Computed]
    public function serviceRequests()
    {
        return ScheduleServiceRequest::query()
            ->with(['requestingCollege:id,name', 'servicingCollege:id,name', 'assignedDepartment:id,name', 'schedules' => fn($q) => $q->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name'])])
            ->when($this->departmentId !== null, fn($q) => $q->where('assigned_department_id', $this->departmentId), fn($q) => $q->whereHas('schedules', fn($q2) => $q2->where('college_id', $this->collegeId)))
            ->whereIn('status', ['assigned_to_dept', 'dept_submitted'])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function submit(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');

        $request = ScheduleServiceRequest::query()->findOrFail($serviceRequestId);
        abort_unless($this->departmentId !== null && (int) $request->assigned_department_id === $this->departmentId, 403);

        app(ScheduleWorkflowService::class)->deptSubmit($serviceRequestId);
        unset($this->serviceRequests);
        $this->toast()->success('Submitted', 'Schedules submitted to your college admin.')->send();
    }

    public function removeSchedule(int $serviceRequestId, int $scheduleId): void
    {
        $this->ensureCanManage('schedules.view');

        $request = ScheduleServiceRequest::query()->findOrFail($serviceRequestId);
        abort_unless($this->departmentId !== null && (int) $request->assigned_department_id === $this->departmentId, 403);

        app(ScheduleWorkflowService::class)->removeScheduleFromRequest($serviceRequestId, $scheduleId);
        unset($this->serviceRequests);
        $this->toast()->success('Removed', 'Schedule removed from request.')->send();
    }
};
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Schedule Assignments</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $campusName }} | {{ $collegeName }} | {{ $departmentName }}
            </p>
        </div>
        <a href="{{ route('schedules.index') }}"
            class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200">
            <x-icon name="arrow-left" class="h-4 w-4" /> Back to Schedules
        </a>
    </div>

    <div class="space-y-4">
        @forelse ($this->serviceRequests as $sr)
            <x-card>
                <div class="space-y-3">
                    {{-- Header --}}
                    <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                        <div>
                            <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                Request #{{ $sr->id }}
                                &mdash; from <span
                                    class="text-blue-600">{{ $sr->requestingCollege?->name ?? '—' }}</span>
                            </p>
                            <p class="text-xs text-zinc-400">Updated {{ $sr->updated_at?->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($sr->status === 'assigned_to_dept')
                                <x-badge color="amber" text="Assigned to Dept" />
                            @elseif ($sr->status === 'dept_submitted')
                                <x-badge color="emerald" text="Submitted" />
                            @endif

                            @if ($sr->status === 'assigned_to_dept' && $departmentId !== null && (int) $sr->assigned_department_id === $departmentId)
                                <x-button size="sm" color="primary" text="Submit to College Admin"
                                    wire:click="submit({{ $sr->id }})"
                                    wire:confirm="Submit all plotted schedules back to the college admin?" />
                            @endif
                        </div>
                    </div>

                    {{-- Schedule list --}}
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                            <thead>
                                <tr
                                    class="text-left text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <th class="px-3 py-2">Code</th>
                                    <th class="px-3 py-2">Subject</th>
                                    <th class="px-3 py-2">Section</th>
                                    <th class="px-3 py-2">Status</th>
                                    @if ($sr->status === 'assigned_to_dept' && $departmentId !== null && (int) $sr->assigned_department_id === $departmentId)
                                        <th class="px-3 py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($sr->schedules as $schedule)
                                    <tr>
                                        <td class="px-3 py-2 font-mono font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $schedule->sched_code }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ $schedule->subject?->code }} – {{ $schedule->subject?->title }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ $schedule->sections->first()?->computed_section_name ?? '—' }}</td>
                                        <td class="px-3 py-2">
                                            <x-badge :color="match ($schedule->status) {
                                                'plotted' => 'emerald',
                                                'pending_plotting' => 'amber',
                                                default => 'zinc',
                                            }" :text="str_replace('_', ' ', $schedule->status)" />
                                        </td>
                                        @if ($sr->status === 'assigned_to_dept' && $departmentId !== null && (int) $sr->assigned_department_id === $departmentId)
                                            <td class="px-3 py-2">
                                                <div class="flex items-center gap-2">
                                                    <a href="{{ route('schedules.plot', ['schedule' => $schedule->id]) }}"
                                                        class="inline-flex items-center gap-1 rounded border border-blue-500 px-2 py-1 text-xs font-medium text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/30">
                                                        <x-icon name="pencil-square" class="h-3.5 w-3.5" /> Plot
                                                    </a>
                                                    <x-button size="xs" color="red" text="Remove"
                                                        wire:click="removeSchedule({{ $sr->id }}, {{ $schedule->id }})"
                                                        wire:confirm="Remove this schedule from the request?" />
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>
        @empty
            <x-card>
                <p class="py-6 text-center text-zinc-400">No schedule assignments for your department.</p>
            </x-card>
        @endforelse
    </div>
</div>
