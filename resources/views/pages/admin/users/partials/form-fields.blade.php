@php
    $profileTypeOptions = [
        ['label' => 'Standard User', 'value' => 'standard'],
        ['label' => 'Faculty', 'value' => 'faculty'],
        ['label' => 'Employee', 'value' => 'employee'],
        ['label' => 'Faculty + Employee', 'value' => 'dual'],
    ];
@endphp

<div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(280px,1fr)]">
    <div class="space-y-6">
        <div class="space-y-4">
            <div>
                <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Account Details</h3>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Keep account identity, access, and profile type in sync.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <x-input label="First Name" wire:model="form.first_name" placeholder="Juan" />
                <x-input label="Middle Name" wire:model="form.middle_name" placeholder="Dela" />
                <x-input label="Last Name" wire:model="form.last_name" placeholder="Cruz" />

                <x-input class="md:col-span-2 xl:col-span-3" label="Email Address" type="email"
                    wire:model="form.email" placeholder="juan@example.com" />

                <div class="md:col-span-2 xl:col-span-3">
                    <x-select.styled label="Roles" wire:model="form.roles" multiple searchable
                        hint="Choose one or more roles for this account" placeholder="Select roles"
                        :options="$this->availableRoles" select="label:label|value:value" />
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <x-select.styled label="Direct Permissions" wire:model="form.direct_permissions" multiple searchable
                        hint="Optional direct permissions on top of role-based access"
                        placeholder="Select direct permissions" :options="$this->availablePermissions"
                        select="label:label|value:value" />
                </div>

                <x-select.styled label="Profile Type" wire:model.live="form.type" :options="$profileTypeOptions"
                    select="label:label|value:value" />

                <div class="md:col-span-2">
                    <x-toggle wire:model="form.is_active" label="Account is active" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $form->is_active ? 'This user can sign in when their roles and profile allow it.' : 'This user will be blocked from sign in while inactive.' }}
                    </p>
                </div>
            </div>
        </div>

        @if ($form->requiresAssignment())
            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Assignment & Profile</h3>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Link the account to the correct campus structure and profile records.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-select.styled label="Campus" wire:model.live="form.campus_id" :options="$this->campuses"
                        select="label:label|value:value" />

                    <x-select.styled wire:key="user-college-select-{{ $form->campus_id ?: 'none' }}" label="College"
                        wire:model.live="form.college_id" :options="$colleges" :disabled="empty($colleges)"
                        select="label:label|value:value" />

                    <x-select.styled wire:key="user-department-select-{{ $form->college_id ?: 'none' }}"
                        :label="$form->requiresFacultyProfile() ? 'Department' : 'Department (Optional)'"
                        :hint="$form->requiresFacultyProfile()
                            ? 'Faculty-linked profiles require a department assignment.'
                            : 'Leave this blank if the employee is not assigned to a department.'"
                        wire:model="form.department_id" :options="$departments" :disabled="empty($departments)"
                        :required="$form->requiresFacultyProfile()" select="label:label|value:value" />

                    @if ($form->requiresFacultyProfile())
                        <x-input label="Academic Rank" wire:model="form.academic_rank" placeholder="Instructor I" />
                        <x-input label="Contact Number" wire:model="form.contactno" placeholder="09171234567" />
                        <x-select.styled label="Sex" wire:model="form.sex" :options="['Male', 'Female']" />
                        <x-input label="Birthday" type="date" wire:model="form.birthday" />

                        <div class="md:col-span-2 xl:col-span-3">
                            <x-textarea label="Address" wire:model="form.address" />
                        </div>
                    @endif

                    @if ($form->requiresEmployeeProfile())
                        <x-input label="Position" wire:model="form.position"
                            placeholder="Department Administrator" />
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900/40">
            <h3 class="text-sm font-semibold text-zinc-700 dark:text-zinc-200">Profile Summary</h3>

            <div class="mt-4 flex flex-wrap gap-2">
                <x-badge :text="$form->profileTypeLabel()" color="slate" light />
                <x-badge :text="$form->is_active ? 'Active' : 'Inactive'" :color="$form->is_active ? 'emerald' : 'red'"
                    light />
            </div>

            <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                Roles control access. The profile type controls which linked faculty or employee records stay active.
            </p>
        </div>

        @if ($form->type === 'standard' && $form->user && ($form->user->facultyProfile || $form->user->employeeProfile))
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                Saving as a standard user will archive the linked faculty and employee profile records.
            </div>
        @elseif ($form->type === 'dual')
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                This user keeps both faculty and employee profiles. Academic rank and position will be stored together.
            </div>
        @elseif ($form->type === 'faculty')
            <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-800 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">
                Faculty users need a department assignment to keep dashboard access and profile data consistent.
            </div>
        @elseif ($form->type === 'employee')
            <div class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800 dark:border-teal-700 dark:bg-teal-900/30 dark:text-teal-200">
                Employee users can stay at the college level or be linked to a department when needed.
            </div>
        @else
            <div class="rounded-lg border border-zinc-200 bg-white p-4 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                Standard users keep only account access data and do not maintain linked faculty or employee records.
            </div>
        @endif
    </div>
</div>
