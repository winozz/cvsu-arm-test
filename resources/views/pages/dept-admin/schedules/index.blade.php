<?php

use App\Models\Schedule;
use App\Traits\CanManage;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use CanManage;

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
    public function stats(): array
    {
        $query = Schedule::query()
            ->where('campus_id', $this->campusId)
            ->where('college_id', $this->collegeId)
            ->when($this->departmentId !== null, fn($q) => $q->where('department_id', $this->departmentId));

        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->whereIn('status', ['draft', 'pending_plotting', 'pending_service_acceptance'])->count(),
            'plotted' => (clone $query)->where('status', 'plotted')->count(),
            'published' => (clone $query)->where('status', 'published')->count(),
        ];
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col items-start justify-between gap-2 md:flex-row md:items-center">
        <div>
            <h1 class="text-xl font-bold dark:text-white">Schedules</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $campusName }} | {{ $collegeName }} | {{ $departmentName }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @can('schedules.create')
                <a href="{{ route('schedules.bulk-generate') }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
                    <x-icon name="squares-plus" class="h-4 w-4" /> Bulk Generate
                </a>
                <a href="{{ route('schedules.custom-section') }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
                    <x-icon name="plus-circle" class="h-4 w-4" /> Custom Section
                </a>
            @endcan
            @can('schedules.assign')
                <a href="{{ route('schedules.plot') }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700">
                    <x-icon name="pencil-square" class="h-4 w-4" /> Plot
                </a>
            @endcan
            @can('schedules.view')
                <a href="{{ route('schedules.service-requests') }}"
                    class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700">
                    <x-icon name="inbox-arrow-down" class="h-4 w-4" /> Schedule Assignments
                </a>
            @endcan
        </div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Pending</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->stats['pending'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Plotted</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $this->stats['plotted'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Published</p>
            <p class="mt-1 text-2xl font-bold text-violet-600">{{ $this->stats['published'] }}</p>
        </x-card>
    </div>

    <livewire:tables.dept-admin.schedules-table :campus-id="$campusId" :college-id="$collegeId" :department-id="$departmentId" />
</div>
