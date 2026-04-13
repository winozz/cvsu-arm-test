<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Traits\CanManage;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public ?int $selectedUserId = null;

    public array $userRoles = [];

    public array $userPermissions = [];

    public function mount(): void
    {
        $this->ensureCanManage('assignments.manage');
    }

    public function rules(): array
    {
        return [
            'selectedUserId' => ['required', 'integer', 'exists:users,id'],
            'userRoles' => ['nullable', 'array'],
            'userRoles.*' => ['exists:roles,name'],
            'userPermissions' => ['nullable', 'array'],
            'userPermissions.*' => ['exists:permissions,name'],
        ];
    }

    public function updatedSelectedUserId(): void
    {
        if (!$this->selectedUserId) {
            $this->reset(['userRoles', 'userPermissions']);

            return;
        }

        $user = User::query()->find($this->selectedUserId);

        if (!$user) {
            $this->reset(['selectedUserId', 'userRoles', 'userPermissions']);

            return;
        }

        $this->userRoles = $user->getRoleNames()->values()->toArray();
        $this->userPermissions = $user->getDirectPermissions()->pluck('name')->values()->toArray();
    }

    public function confirmSave(): void
    {
        $this->ensureCanManage('assignments.manage');
        $this->validate();

        $this->dialog()->question('Save user assignments?', 'This will sync the selected roles and direct permissions for the chosen user.')->confirm('Yes, save', 'saveUserAssignments')->cancel('Cancel')->send();
    }

    public function saveUserAssignments(): void
    {
        $this->ensureCanManage('assignments.manage');
        $validated = $this->validate();

        $user = User::query()->findOrFail($validated['selectedUserId']);

        DB::transaction(function () use ($user, $validated): void {
            $user->syncRoles($validated['userRoles'] ?? []);
            $user->syncPermissions($validated['userPermissions'] ?? []);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->userRoles = $user->fresh()->getRoleNames()->values()->toArray();
        $this->userPermissions = $user->fresh()->getDirectPermissions()->pluck('name')->values()->toArray();

        $this->toast()->success('Success', 'User assignments updated successfully.')->send();
    }

    #[Computed]
    public function userSelectOptions(): array
    {
        return User::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(User $user) => [
                    'label' => sprintf('%s (%s)', $user->name, $user->email),
                    'value' => $user->id,
                ],
            )
            ->toArray();
    }

    #[Computed]
    public function roleSelectOptions(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(Role $role) => [
                    'label' => $role->name,
                    'value' => $role->name,
                ],
            )
            ->toArray();
    }

    #[Computed]
    public function permissionSelectOptions(): array
    {
        return Permission::query()
            ->orderBy('name')
            ->get()
            ->map(
                fn(Permission $permission) => [
                    'label' => $permission->name,
                    'value' => $permission->name,
                ],
            )
            ->toArray();
    }
};
?>

<div class="space-y-6 py-8 max-w-5xl">
    <div>
        <h1 class="text-xl font-bold dark:text-white">Assignments Center</h1>
        <p class="text-sm text-zinc-600 dark:text-zinc-300">Manage direct user assignment of roles and permissions.</p>
    </div>

    <div
        class="space-y-6 rounded-lg border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-800">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">User Assignments</h2>

        <div>
            <x-select.styled wire:model.live="selectedUserId" label="Select User" placeholder="Choose user" searchable
                :options="$this->userSelectOptions" select="label:label|value:value" />
        </div>

        @if ($selectedUserId)
            <div class="space-y-6 rounded-md bg-zinc-50 p-5 dark:bg-zinc-900/40">
                <div>
                    <x-select.styled wire:key="roles-select-{{ $selectedUserId }}" wire:model="userRoles" label="Roles"
                        placeholder="Select roles" multiple searchable :options="$this->roleSelectOptions"
                        select="label:label|value:value" />

                    <div class="mt-3">
                        <span class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Currently Assigned
                            Roles:</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($userRoles as $role)
                                <x-badge :text="$role" color="primary" light sm />
                            @empty
                                <span class="text-sm italic text-zinc-500">No roles assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <hr class="border-zinc-200 dark:border-zinc-700">

                <div>
                    <x-select.styled wire:key="permissions-select-{{ $selectedUserId }}" wire:model="userPermissions"
                        label="Direct Permissions" placeholder="Select direct permissions" multiple searchable
                        :options="$this->permissionSelectOptions" select="label:label|value:value" />

                    <div class="mt-3">
                        <span class="mb-2 block text-sm font-medium text-zinc-700 dark:text-zinc-300">Currently Assigned
                            Permissions:</span>
                        <div class="flex flex-wrap gap-2">
                            @forelse ($userPermissions as $permission)
                                <x-badge :text="$permission" color="emerald" light />
                            @empty
                                <span class="text-sm italic text-zinc-500">No direct permissions assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-button color="primary" wire:click="confirmSave" sm>Save User Assignments</x-button>
                </div>
            </div>
        @else
            <div
                class="rounded-md border border-dashed border-zinc-300 bg-zinc-50 p-8 text-center text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-400">
                Please select a user from the dropdown above to manage assignments.
            </div>
        @endif
    </div>
</div>
