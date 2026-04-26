<div class="space-y-4">
    <div class="grid gap-4 md:grid-cols-2">
        <x-input label="First Name" wire:model="form.first_name" required />
        <x-input label="Middle Name" wire:model="form.middle_name" />
        <x-input label="Last Name" wire:model="form.last_name" required />
        <x-input label="Email" wire:model="form.email" type="email" required />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-select.styled label="Account Type" wire:model.live="form.type" :options="$this->typeOptions"
            select="label:label|value:value" required />
        <x-toggle label="Active account" wire:model="form.is_active" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <x-select.styled label="Roles" wire:model="form.roles" :options="$roles" select="label:label|value:value"
            multiple searchable required />
        <x-select.styled label="Direct Permissions" wire:model="form.direct_permissions" :options="$permissions"
            select="label:label|value:value" multiple searchable />
    </div>

    @if ($form->requiresAssignment())
        <div class="border-l-4 border-primary-200 bg-primary-50 p-4 dark:border-primary-900 dark:bg-primary-950">
            <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Academic Assignment</h3>
            <div class="grid gap-4 md:grid-cols-3">
                <x-select.styled label="Campus" wire:model.live="form.campus_id" :options="$this->campuses"
                    select="label:label|value:value" required />
                <x-select.styled label="College" wire:model.live="form.college_id" :options="$colleges"
                    select="label:label|value:value" required />
                <x-select.styled label="Department" wire:model="form.department_id" :options="$departments"
                    select="label:label|value:value" :required="$form->requiresFacultyProfile()" />
            </div>
        </div>
    @endif

    @if ($form->requiresFacultyProfile())
        <div class="border-l-4 border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950">
            <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Faculty Details</h3>
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Academic Rank" wire:model="form.academic_rank" />
                <x-input label="Contact Number" wire:model="form.contactno" />
                <x-select.styled label="Sex" wire:model="form.sex" :options="[['label' => 'Male', 'value' => 'Male'], ['label' => 'Female', 'value' => 'Female']]"
                    select="label:label|value:value" />
                <x-input label="Birthday" wire:model="form.birthday" type="date" />
            </div>
            <x-textarea label="Address" wire:model="form.address" />
        </div>
    @endif

    @if ($form->requiresEmployeeProfile())
        <div class="border-l-4 border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-900 dark:bg-emerald-950">
            <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Employee Details</h3>
            <x-input label="Position" wire:model="form.position" required />
        </div>
    @endif
</div>
