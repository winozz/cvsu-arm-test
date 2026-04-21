<?php

use App\Livewire\Forms\Admin\UserForm;
use App\Models\User;
use App\Support\UserManagement\UserAccountWriter;
use App\Traits\CanManage;
use App\Traits\HasCascadingLocationSelects;
use App\Traits\ResolvesUserFormOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new #[Layout('layouts.app')] class extends Component
{
    use CanManage;
    use HasCascadingLocationSelects;
    use Interactions;
    use ResolvesUserFormOptions;

    public User $user;

    public UserForm $form;

    public bool $isEditing = false;

    public array $colleges = [];

    public array $departments = [];

    public function mount(User $user): void
    {
        $this->ensureCanManage('users.view');
        $this->loadUser($user);
    }

    public function updatedFormType(string $value): void
    {
        if ($value !== 'standard') {
            return;
        }

        $this->form->clearAssignment();
        $this->colleges = [];
        $this->departments = [];
    }

    public function startEditing(): void
    {
        $this->ensureCanManage('users.update');

        $this->resetValidation();
        $this->form->setUser($this->user);
        $this->refreshAssignmentOptions();
        $this->isEditing = true;
    }

    public function confirmEdit(): void
    {
        $this->ensureCanManage('users.update');

        if ($this->isEditing) {
            $this->cancelEditing();

            return;
        }

        $this->dialog()
            ->question('Enable Editing?', 'Do you want to modify this user account and profile?')
            ->confirm('Yes', 'startEditing')
            ->cancel('Cancel')
            ->send();
    }

    public function cancelEditing(): void
    {
        $this->ensureCanManage('users.update');

        $this->resetValidation();
        $this->loadUser($this->user->fresh());
        $this->isEditing = false;
    }

    public function save(): void
    {
        $this->ensureCanManage('users.update');

        try {
            $this->form->validateForm();
            $savedUser = app(UserAccountWriter::class)->save($this->form, $this->user);

            $this->syncLoadedUserState($savedUser);
            $this->isEditing = false;

            $this->toast()->success('Success', 'User updated successfully.')->send();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('User update failed', [
                'user_id' => $this->user->id,
                'error' => $exception->getMessage(),
            ]);

            $this->toast()->error('Error', 'Unable to save the user right now.')->send();
        }
    }

    public function confirmSave(): void
    {
        $this->ensureCanManage('users.update');

        if ($this->form->type === 'standard' && ($this->user->facultyProfile || $this->user->employeeProfile)) {
            $this->dialog()
                ->warning('Save Changes?', 'Saving as a standard user will archive the linked faculty and employee profile records.')
                ->confirm('Continue', 'save')
                ->cancel('Cancel')
                ->send();

            return;
        }

        $this->dialog()
            ->question('Save Changes?', 'Apply the updates to this user account now?')
            ->confirm('Save', 'save')
            ->cancel('Cancel')
            ->send();
    }

    public function assignmentPath(): string
    {
        $profile = $this->user->facultyProfile ?? $this->user->employeeProfile;

        if (! $profile) {
            return 'Not assigned';
        }

        $path = collect([
            $profile->campus?->name,
            $profile->college?->name,
            $profile->department?->name,
        ])->filter()->implode(' / ');

        return $path !== '' ? $path : 'Not assigned';
    }

    protected function loadUser(User $user): void
    {
        $this->syncLoadedUserState($user->load($this->userRelations()));
    }

    protected function syncLoadedUserState(User $user): void
    {
        $this->user = $user;
        $this->form->setUser($this->user);
        $this->refreshAssignmentOptions();
    }

    protected function userRelations(): array
    {
        return [
            'roles',
            'facultyProfile.campus',
            'facultyProfile.college',
            'facultyProfile.department',
            'employeeProfile.campus',
            'employeeProfile.college',
            'employeeProfile.department',
        ];
    }
};
?>

<div class="space-y-6 py-8">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="flex items-start gap-4">
            @if ($user->avatar)
                <img src="{{ $user->avatar }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover" />
            @else
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 text-lg font-semibold text-primary-700 dark:bg-zinc-700 dark:text-zinc-100">
                    {{ strtoupper($user->initials()) }}
                </div>
            @endif

            <div class="space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</h1>
                    <x-badge :text="$form->profileTypeLabel()" color="slate" light />
                    <x-badge :text="$user->is_active ? 'Active' : 'Inactive'"
                        :color="$user->is_active ? 'emerald' : 'red'" light />
                </div>

                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</p>

                <div class="flex flex-wrap gap-2">
                    @forelse ($user->roles as $role)
                        <x-badge :text="\Illuminate\Support\Str::headline($role->name)" color="primary" light />
                    @empty
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">No roles assigned.</span>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="flex gap-2">
            @can('users.update')
                @if ($isEditing)
                    <x-button flat text="Cancel" wire:click="cancelEditing" sm />
                    <x-button color="primary" text="Save Changes" wire:click="confirmSave" sm />
                @else
                    <x-button color="primary" text="Edit User" icon="pencil" wire:click="confirmEdit" sm />
                @endif
            @endcan

            <x-button tag="a" href="{{ route('admin.users') }}" outline text="Back" sm />
        </div>
    </div>

    @if ($isEditing)
        <x-card>
            @include('pages.admin.users.partials.form-fields')
        </x-card>
    @else
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
                <x-card>
                    <div class="space-y-6">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Account Overview</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                Core identity, access level, and assignment details.
                            </p>
                        </div>

                        <div class="grid gap-6 md:grid-cols-2">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">First Name</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $form->first_name }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Middle Name</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                    {{ $form->middle_name !== '' ? $form->middle_name : 'Not set' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Last Name</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $form->last_name }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Account Status</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Profile Type</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $form->profileTypeLabel() }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Assignment</p>
                                <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">{{ $this->assignmentPath() }}</p>
                            </div>
                        </div>

                        @if ($user->facultyProfile)
                            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Faculty Profile</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        Faculty-specific details linked to this account.
                                    </p>
                                </div>

                                <div class="grid gap-6 md:grid-cols-2">
                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Academic Rank</p>
                                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                            {{ $form->academic_rank !== '' ? $form->academic_rank : 'Not set' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Contact Number</p>
                                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                            {{ $form->contactno !== '' ? $form->contactno : 'Not set' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Sex</p>
                                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                            {{ $form->sex !== '' ? $form->sex : 'Not set' }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Birthday</p>
                                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                            {{ $form->birthday !== '' ? \Illuminate\Support\Carbon::parse($form->birthday)->format('M d, Y') : 'Not set' }}
                                        </p>
                                    </div>

                                    <div class="md:col-span-2">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Address</p>
                                        <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                            {{ $form->address !== '' ? $form->address : 'Not set' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($user->employeeProfile)
                            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                                <div>
                                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Employee Profile</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        Employee-specific details linked to this account.
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Position</p>
                                    <p class="mt-2 text-sm text-zinc-700 dark:text-zinc-100">
                                        {{ $form->position !== '' ? $form->position : 'Not set' }}
                                    </p>
                                </div>
                            </div>
                        @endif
                    </div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card>
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Roles</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                Access inherited from assigned roles.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($user->roles as $role)
                                <x-badge :text="\Illuminate\Support\Str::headline($role->name)" color="primary" light />
                            @empty
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">No roles assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Direct Permissions</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                Permissions granted directly to this account.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($user->getDirectPermissions() as $permission)
                                <x-badge
                                    :text="\Illuminate\Support\Str::headline(str_replace(['.', '_'], ' ', $permission->name))"
                                    color="emerald" light />
                            @empty
                                <span class="text-sm text-zinc-500 dark:text-zinc-400">No direct permissions assigned.</span>
                            @endforelse
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <div class="space-y-4">
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Account Meta</h2>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                Basic timestamps for this user record.
                            </p>
                        </div>

                        <div class="space-y-3 text-sm text-zinc-700 dark:text-zinc-100">
                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Created</p>
                                <p class="mt-1">{{ $user->created_at?->format('M d, Y h:i A') }}</p>
                            </div>

                            <div>
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">Last Updated</p>
                                <p class="mt-1">{{ $user->updated_at?->format('M d, Y h:i A') }}</p>
                            </div>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>
    @endif
</div>
