<?php

use App\Traits\CanManage;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public function mount(): void
    {
        $this->ensureCanManage('subjects.view');
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                {{-- <h1 class="text-xl font-bold dark:text-white">{{ $college->code }}</h1> --}}
                {{-- <x-badge :text="$college->is_active ? 'Active' : 'Inactive'" :color="$college->is_active ? 'primary' : 'red'" round /> --}}
            </div>
            {{-- <p class="italic text-zinc-600 dark:text-zinc-200">{{ $college->name }}</p> --}}
        </div>
        <div class="flex gap-2">
            {{-- @can('programs.view')
                <x-button tag="a" href="{{ route('dashboard.resolve') }}" sm outline text="Back to Dashboard" />
            @endcan --}}
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Programs</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Active</p>
            <p class="mt-1 text-2xl font-bold text-green-600">{{ $this->stats['active'] }}</p>
        </x-card>
        <x-card>
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Inactive</p>
            <p class="mt-1 text-2xl font-bold text-red-500">{{ $this->stats['inactive'] }}</p>
        </x-card> --}}
    </div>

    <x-card>
        <div class="flex flex-col gap-4 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Subjects List</h2>
                {{-- <p class="text-sm text-zinc-500 dark:text-zinc-400">Subjects offered under {{ $college->name }}.</p> --}}
            </div>

            {{-- <div class="flex gap-2">
                @can('programs.create')
                    <x-button wire:click="openCreateProgramModal" sm color="primary" icon="plus" text="New Program" />
                @endcan
            </div> --}}
        </div>

        <div class="p-6">
            <livewire:tables.college-admin.subjects-table />
        </div>
    </x-card>


</div>
