<?php

use App\Models\College;
use App\Models\Department;
use App\Models\Schedule;
use App\Models\ScheduleSection;
use App\Models\ScheduleServiceRequest;
use App\Services\ScheduleWorkflowService;
use App\Traits\CanManage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public int $collegeId;

    public string $collegeName = '-';

    /** @var array<int> */
    public array $requestScheduleIds = [];

    /** @var array<int, string> */
    public array $requestSectionNames = [];

    public ?int $requestServicingCollegeId = null;

    public string $incomingStatusFilter = '';

    public bool $assignModal = false;

    public ?int $assignServiceRequestId = null;

    public ?int $assignDepartmentId = null;

    public function mount(): void
    {
        $this->ensureCanManage('schedules.view');

        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.college', 'facultyProfile.college']);
        $profile = $user?->assignedAcademicProfile();

        abort_unless($profile && filled($profile->college_id), 403);

        $this->collegeId = (int) $profile->college_id;
        $this->collegeName = $profile->college?->name ?? '-';
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'incoming_pending' => ScheduleServiceRequest::query()->where('servicing_college_id', $this->collegeId)->where('status', 'pending')->count(),
            'incoming_total' => ScheduleServiceRequest::query()->where('servicing_college_id', $this->collegeId)->count(),
            'outgoing_total' => ScheduleServiceRequest::query()->where('requesting_college_id', $this->collegeId)->count(),
        ];
    }

    #[Computed]
    public function sectionOptions(): array
    {
        return ScheduleSection::query()
            ->select('computed_section_name')
            ->whereNotNull('computed_section_name')
            ->whereHas('schedule', function ($query) {
                $query->where('college_id', $this->collegeId)->whereIn('status', ['draft', 'pending_plotting', 'plotted']);
            })
            ->groupBy('computed_section_name')
            ->orderBy('computed_section_name')
            ->get()
            ->map(
                fn(ScheduleSection $section): array => [
                    'label' => $section->computed_section_name,
                    'value' => $section->computed_section_name,
                ],
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function selectedSectionSchedules()
    {
        return Schedule::query()
            ->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name'])
            ->whereIn('id', $this->requestScheduleIds)
            ->orderBy('sched_code')
            ->get();
    }

    #[Computed]
    public function servicingCollegeOptions(): array
    {
        return College::query()
            ->where('id', '!=', $this->collegeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(
                fn(College $college) => [
                    'label' => $college->code . ' - ' . $college->name,
                    'value' => (int) $college->id,
                ],
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function assignDepartmentOptions(): array
    {
        return Department::query()
            ->where('college_id', $this->collegeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(
                fn(Department $department) => [
                    'label' => $department->code . ' - ' . $department->name,
                    'value' => (int) $department->id,
                ],
            )
            ->values()
            ->toArray();
    }

    #[Computed]
    public function outgoingRequests()
    {
        return ScheduleServiceRequest::query()
            ->with(['servicingCollege:id,name', 'schedules' => fn($q) => $q->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name'])])
            ->where('requesting_college_id', $this->collegeId)
            ->latest()
            ->get();
    }

    #[Computed]
    public function incomingRequests()
    {
        return ScheduleServiceRequest::query()
            ->with(['requestingCollege:id,name', 'servicingCollege:id,name', 'assignedDepartment:id,name', 'schedules' => fn($q) => $q->with(['subject:id,code,title', 'sections:id,schedule_id,computed_section_name'])])
            ->where('servicing_college_id', $this->collegeId)
            ->when(filled($this->incomingStatusFilter), fn($query) => $query->where('status', $this->incomingStatusFilter))
            ->latest()
            ->get();
    }

    public function createRequest(): void
    {
        $this->ensureCanManage('schedules.view');

        $validated = $this->validate([
            'requestSectionNames' => ['required', 'array', 'min:1'],
            'requestSectionNames.*' => ['string'],
            'requestScheduleIds' => ['required', 'array', 'min:1'],
            'requestScheduleIds.*' => ['integer', 'exists:schedules,id'],
            'requestServicingCollegeId' => ['required', 'integer', 'exists:colleges,id'],
        ]);

        abort_if((int) $validated['requestServicingCollegeId'] === $this->collegeId, 422, 'Cannot send to own college.');

        app(ScheduleWorkflowService::class)->createServiceRequest($this->collegeId, (int) $validated['requestServicingCollegeId'], array_map('intval', $validated['requestScheduleIds']));

        $this->reset(['requestSectionNames', 'requestScheduleIds', 'requestServicingCollegeId']);
        unset($this->outgoingRequests);
        $this->toast()->success('Submitted', 'Schedule request submitted successfully.')->send();
    }

    public function updatedRequestSectionNames(): void
    {
        if ($this->requestSectionNames === []) {
            $this->requestScheduleIds = [];
            return;
        }

        $this->requestScheduleIds = Schedule::query()
            ->where('college_id', $this->collegeId)
            ->whereIn('status', ['draft', 'pending_plotting', 'plotted'])
            ->whereHas('sections', fn($query) => $query->whereIn('computed_section_name', $this->requestSectionNames))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();
    }

    public function acceptRequest(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');
        $this->assertIncomingRequest($serviceRequestId);

        app(ScheduleWorkflowService::class)->respondToServiceRequest($serviceRequestId, true);
        unset($this->incomingRequests);
        $this->toast()->success('Accepted', 'Schedule request accepted.')->send();
    }

    public function rejectRequest(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');
        $this->assertIncomingRequest($serviceRequestId);

        app(ScheduleWorkflowService::class)->respondToServiceRequest($serviceRequestId, false);
        unset($this->incomingRequests);
        $this->toast()->success('Rejected', 'Schedule request rejected.')->send();
    }

    public function openAssignModal(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');
        $this->assertIncomingRequest($serviceRequestId);

        $this->assignServiceRequestId = $serviceRequestId;
        $this->assignDepartmentId = null;
        $this->assignModal = true;
    }

    public function assignToDepartment(): void
    {
        $this->ensureCanManage('schedules.view');

        $validated = $this->validate([
            'assignServiceRequestId' => ['required', 'integer', 'exists:schedule_service_requests,id'],
            'assignDepartmentId' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $this->assertIncomingRequest((int) $validated['assignServiceRequestId']);

        $department = Department::query()->where('college_id', $this->collegeId)->findOrFail((int) $validated['assignDepartmentId']);

        app(ScheduleWorkflowService::class)->assignToDepartment((int) $validated['assignServiceRequestId'], (int) $department->id);

        $this->assignModal = false;
        $this->assignServiceRequestId = null;
        $this->assignDepartmentId = null;
        unset($this->incomingRequests);

        $this->toast()->success('Delegated', 'Schedule request delegated to department.')->send();
    }

    public function completeRequest(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');
        $this->assertIncomingRequest($serviceRequestId);

        app(ScheduleWorkflowService::class)->completeRequest($serviceRequestId);
        unset($this->incomingRequests);
        $this->toast()->success('Completed', 'Schedule request verified and completed.')->send();
    }

    public function cancelRequest(int $serviceRequestId): void
    {
        $this->ensureCanManage('schedules.view');
        ScheduleServiceRequest::query()->whereKey($serviceRequestId)->where('requesting_college_id', $this->collegeId)->firstOrFail();

        app(ScheduleWorkflowService::class)->cancelRequest($serviceRequestId);
        unset($this->outgoingRequests);
        $this->toast()->warning('Cancelled', 'Schedule request cancelled.')->send();
    }

    private function assertIncomingRequest(int $serviceRequestId): void
    {
        ScheduleServiceRequest::query()->whereKey($serviceRequestId)->where('servicing_college_id', $this->collegeId)->firstOrFail();
    }
};
?>

<div class="space-y-6">
    <div>
        <h1 class="text-xl font-bold dark:text-white">Schedule Requests</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">College: {{ $collegeName }}</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Incoming Pending</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->stats['incoming_pending'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Incoming Total</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['incoming_total'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Outgoing Total</p>
            <p class="mt-1 text-2xl font-bold text-blue-600">{{ $this->stats['outgoing_total'] }}</p>
        </x-card>
    </div>

    <x-card>
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold dark:text-white">Create Schedule Request</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">Select one or more sections and all subjects
                    under them will be auto-selected for the request.</p>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <x-select.styled label="Sections (bulk)" wire:model.live="requestSectionNames" :options="$this->sectionOptions"
                    select="label:label|value:value" searchable multiple />
                <x-select.styled label="Servicing College" wire:model="requestServicingCollegeId" :options="$this->servicingCollegeOptions"
                    select="label:label|value:value" searchable />
            </div>

            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    Selected Subjects ({{ count($requestScheduleIds) }})
                </p>
                @if (count($requestScheduleIds) > 0)
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($this->selectedSectionSchedules as $schedule)
                            <span
                                class="rounded bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-mono text-zinc-700 dark:text-zinc-300">
                                {{ $schedule->subject?->code ?? '—' }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-zinc-400">Choose section(s) to auto-select their subjects.</p>
                @endif
            </div>

            <div class="flex justify-end">
                <x-button color="primary" text="Submit Schedule Request" wire:click="createRequest" />
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-4">
            <h2 class="text-lg font-semibold dark:text-white">Outgoing Requests</h2>

            <div class="space-y-3">
                @forelse ($this->outgoingRequests as $req)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-2">
                        <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    Request #{{ $req->id }} &rarr;
                                    <span class="text-violet-600">{{ $req->servicingCollege?->name ?? '—' }}</span>
                                </p>
                                <p class="text-xs text-zinc-400">{{ $req->updated_at?->diffForHumans() }}</p>
                            </div>
                            <x-badge :text="str_replace('_', ' ', strtoupper($req->status))" :color="match ($req->status) {
                                'pending' => 'amber',
                                'accepted' => 'blue',
                                'assigned_to_dept' => 'violet',
                                'dept_submitted' => 'emerald',
                                'completed' => 'slate',
                                'rejected' => 'red',
                                'cancelled' => 'zinc',
                                default => 'zinc',
                            }" round />
                            @if (in_array($req->status, ['pending', 'accepted'], true))
                                <x-button size="sm" outline color="red" text="Cancel"
                                    wire:click="cancelRequest({{ $req->id }})"
                                    wire:confirm="Cancel this schedule request? All attached schedules will revert to draft." />
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($req->schedules as $schedule)
                                <span
                                    class="rounded bg-zinc-100 dark:bg-zinc-800 px-2 py-0.5 text-xs font-mono text-zinc-700 dark:text-zinc-300">
                                    {{ $schedule->sched_code }} /
                                    {{ $schedule->sections->first()?->computed_section_name ?? '—' }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-zinc-400">No outgoing requests yet.</p>
                @endforelse
            </div>
        </div>
    </x-card>

    <x-card>
        <div class="space-y-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <h2 class="text-lg font-semibold dark:text-white">Incoming Queue</h2>
                <div class="w-full md:w-72">
                    <x-select.styled label="Filter Status" wire:model="incomingStatusFilter" :options="[
                        ['label' => 'All', 'value' => ''],
                        ['label' => 'Pending', 'value' => 'pending'],
                        ['label' => 'Accepted', 'value' => 'accepted'],
                        ['label' => 'Rejected', 'value' => 'rejected'],
                        ['label' => 'Assigned to Department', 'value' => 'assigned_to_dept'],
                        ['label' => 'Dept Submitted', 'value' => 'dept_submitted'],
                        ['label' => 'Completed', 'value' => 'completed'],
                        ['label' => 'Cancelled', 'value' => 'cancelled'],
                    ]"
                        select="label:label|value:value" />
                </div>
            </div>

            <div class="space-y-4">
                @forelse ($this->incomingRequests as $request)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 space-y-3">
                        <div class="flex flex-col items-start justify-between gap-2 sm:flex-row sm:items-center">
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    Request #{{ $request->id }} — from
                                    <span class="text-blue-600">{{ $request->requestingCollege?->name ?? '—' }}</span>
                                </p>
                                @if ($request->assignedDepartment)
                                    <p class="text-xs text-zinc-400">Assigned: {{ $request->assignedDepartment->name }}
                                    </p>
                                @endif
                                <p class="text-xs text-zinc-400">Updated {{ $request->updated_at?->diffForHumans() }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :text="str_replace('_', ' ', strtoupper($request->status))" :color="match ($request->status) {
                                    'pending' => 'amber',
                                    'accepted' => 'blue',
                                    'assigned_to_dept' => 'violet',
                                    'dept_submitted' => 'emerald',
                                    'rejected' => 'red',
                                    'completed' => 'slate',
                                    default => 'slate',
                                }" round />

                                @if ($request->status === 'pending')
                                    <x-button size="sm" color="primary" text="Accept"
                                        wire:click="acceptRequest({{ $request->id }})" />
                                    <x-button size="sm" color="red" text="Reject"
                                        wire:click="rejectRequest({{ $request->id }})" />
                                @endif
                                @if (in_array($request->status, ['accepted', 'assigned_to_dept'], true))
                                    <x-button size="sm" outline color="blue" text="Delegate to Dept"
                                        wire:click="openAssignModal({{ $request->id }})" />
                                @endif
                                @if ($request->status === 'dept_submitted')
                                    <x-button size="sm" color="emerald" text="Verify &amp; Complete"
                                        wire:click="completeRequest({{ $request->id }})"
                                        wire:confirm="Mark this request as completed and finalize all schedules?" />
                                @endif
                            </div>
                        </div>

                        {{-- Schedule list --}}
                        <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700 text-sm">
                            <thead>
                                <tr class="text-left text-xs font-semibold uppercase text-zinc-400">
                                    <th class="px-2 py-1">Code</th>
                                    <th class="px-2 py-1">Subject</th>
                                    <th class="px-2 py-1">Section</th>
                                    <th class="px-2 py-1">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($request->schedules as $schedule)
                                    <tr>
                                        <td class="px-2 py-1 font-mono text-zinc-900 dark:text-zinc-100">
                                            {{ $schedule->sched_code }}</td>
                                        <td class="px-2 py-1 text-zinc-600 dark:text-zinc-300">
                                            {{ $schedule->subject?->code }} – {{ $schedule->subject?->title }}</td>
                                        <td class="px-2 py-1 text-zinc-600 dark:text-zinc-300">
                                            {{ $schedule->sections->first()?->computed_section_name ?? '—' }}</td>
                                        <td class="px-2 py-1">
                                            <x-badge :color="match ($schedule->status) {
                                                'plotted' => 'emerald',
                                                'pending_plotting' => 'amber',
                                                default => 'zinc',
                                            }" :text="str_replace('_', ' ', $schedule->status)" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="py-4 text-center text-zinc-400">No incoming requests found.</p>
                @endforelse
            </div>
        </div>
    </x-card>

    <x-modal wire="assignModal" title="Delegate to Department" size="md">
        <div class="space-y-4">
            <x-select.styled label="Department" wire:model="assignDepartmentId" :options="$this->assignDepartmentOptions"
                select="label:label|value:value" searchable />
        </div>

        <x-slot:footer>
            <div class="flex justify-end gap-2">
                <x-button flat text="Cancel" wire:click="$set('assignModal', false)" sm />
                <x-button color="primary" text="Assign" wire:click="assignToDepartment" sm />
            </div>
        </x-slot:footer>
    </x-modal>
</div>
