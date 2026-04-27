<?php

use App\Models\Campus;
use App\Models\College;
use App\Models\Subject;
use App\Traits\CanManage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component
{
    use CanManage, Interactions;

    public College $college;

    public Campus $campus;

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

            <div
                class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-300">
                Search, filter, and export subject records without leaving the page.
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.college-admin.subjects-table />
        </div>
    </x-card>
</div>
