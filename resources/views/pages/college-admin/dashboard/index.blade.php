<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Department;
use App\Models\Program;
use App\Models\Room;
use App\Traits\CanManage;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    use CanManage;

    public College $college;

    public Campus $campus;

    public function mount(): void
    {
        $this->ensureCanManage('departments.view');

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

    #[Computed]
    public function stats(): array
    {
        return [
            'departments' => Department::query()->where('college_id', $this->college->id)->count(),
            'rooms' => Room::query()->where('college_id', $this->college->id)->count(),
            'programs' => Program::query()->whereHas('colleges', fn($query) => $query->whereKey($this->college->id))->count(),
        ];
    }

    protected function resolveFallbackCollegeContext(): void
    {
        $fallbackCollege = College::query()->with('campus')->orderBy('name')->first();

        abort_unless($fallbackCollege?->campus, 403);

        $this->campus = $fallbackCollege->campus;
        $this->college = $fallbackCollege;
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div class="space-y-2">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.2em] text-zinc-500 dark:text-zinc-400">
                    College Dashboard
                </p>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $college->name }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ $campus->code }} Campus</span>
                <span aria-hidden="true">&bull;</span>
                <span>{{ $college->code }}</span>
                <x-badge :text="$college->is_active ? 'Active' : 'Inactive'" :color="$college->is_active ? 'emerald' : 'red'" light round />
            </div>

            <p class="max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                Quick overview of the departments, rooms, and programs currently managed under this college.
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <x-card class="flex h-full flex-col justify-between gap-4 p-6">
            <div class="space-y-2">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Departments</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['departments'] }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Academic units configured for {{ $college->code }}.
                </p>
            </div>

            <div>
                <x-button tag="a" href="{{ route('departments.index') }}" sm color="primary"
                    text="View Departments" />
            </div>
        </x-card>

        <x-card class="flex h-full flex-col justify-between gap-4 p-6">
            <div class="space-y-2">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Rooms</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['rooms'] }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Learning spaces assigned to this college and its departments.
                </p>
            </div>

            <div>
                @if (Auth::user()?->canAccessCollegeRooms())
                    <x-button tag="a" href="{{ route('college-rooms.index') }}" sm color="primary"
                        text="View Rooms" />
                @else
                    <span class="text-sm text-zinc-400 dark:text-zinc-500">Room details are unavailable for this
                        account.</span>
                @endif
            </div>
        </x-card>

        <x-card class="flex h-full flex-col justify-between gap-4 p-6">
            <div class="space-y-2">
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Programs</p>
                <p class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['programs'] }}</p>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Degree offerings currently attached to this college.
                </p>
            </div>

            <div>
                @can('programs.view')
                    <x-button tag="a" href="{{ route('programs.index') }}" sm color="primary" text="View Programs" />
                @else
                    <span class="text-sm text-zinc-400 dark:text-zinc-500">Program details are unavailable for this
                        account.</span>
                @endcan
            </div>
        </x-card>
    </div>
</div>
