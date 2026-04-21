<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="tallstackui_darkTheme()">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <tallstackui:script />
    @livewireStyles

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @livewireStyles
</head>

<body class="font-sans antialiased" x-cloak x-data="{ name: @js(auth()->user()->name) }" x-on:name-updated.window="name = $event.detail.name"
    x-bind:class="{ 'dark bg-gray-800': darkTheme, 'bg-gray-50': !darkTheme }">

    {{-- MAIN LAYOUT --}}
    <x-layout>
        {{-- TOAST & DIALOG --}}
        <x-slot:top>
            <x-dialog />
            <x-toast />
        </x-slot:top>

        {{-- DASHBOARD HEADER SLOT --}}
        <x-slot:header>
            <x-layout.header>
                <x-slot:left>
                </x-slot:left>
                <x-slot:right>
                    <x-dropdown>
                        <x-slot:action class="flex items-center justify-center">

                            <button x-on:click="show = !show"
                                class="flex items-center gap-2 px-2 py-1 transition-opacity cursor-pointer hover:opacity-80 focus:outline-none">

                                {{-- Show name on larger screens --}}
                                <span class="hidden text-sm font-bold md:block text-zinc-700 dark:text-zinc-200"
                                    x-text="name"></span>

                                {{-- The Avatar serves as the visual trigger --}}
                                <x-avatar sm background="3aa13a " color="fff" :model="auth()->user()"
                                    class="cursor-pointer border-2 border-emerald-600" />
                            </button>
                        </x-slot:action>
                        <x-slot:header>
                            <x-theme-switch block />
                        </x-slot:header>
                        {{-- <x-dropdown.items icon="user-circle" text="My Profile" /> --}}

                        {{-- Logout Button --}}
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown.items icon="arrow-left-on-rectangle" :text="__('Logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();" separator />
                        </form>
                    </x-dropdown>
                </x-slot:right>
            </x-layout.header>
        </x-slot:header>

        {{-- DASHBOARD SIDEBAR SLOT --}}
        <x-slot:menu>
            <x-side-bar>
                <x-slot:brand>
                    <div class="my-4 flex items-center justify-center">
                        <img src="{{ asset('/images/cvsu-logo.png') }}" class="w-10" />
                    </div>
                </x-slot:brand>

                {{-- Dashboard Menu --}}
                <x-side-bar.item text="Dashboard" icon="home" :current="request()->routeIs(
                    'admin.dashboard',
                    'faculty.dashboard',
                    'college-admin.dashboard',
                    'department-admin.dashboard',
                )" :route="route('dashboard.resolve')" />

                {{-- FACULTY LINKS --}}
                @if (auth()->user()?->can('faculty_schedules.view') && auth()->user()?->employeeProfile()->exists())
                    {{-- Teaching Links --}}
                    <x-side-bar.item text="Faculty" opened>
                        <x-side-bar.item text="Schedules & Subjects" icon="clipboard-document-list" />
                    </x-side-bar.item>
                @endif

                {{-- COLLEGE ADMIN LINKS --}}
                @if (auth()->user()?->can('departments.view') && auth()->user()?->employeeProfile()->exists())
                    <x-side-bar.item text="College" opened>
                        <x-side-bar.item text="Departments" icon="briefcase" :current="request()->routeIs('college-admin.departments', 'college-admin.departments.*')" :route="route('college-admin.departments')" />


                        @can('programs.view')
                            <x-side-bar.item text="Programs" icon="academic-cap" :current="request()->routeIs('college-admin.programs', 'college-admin.programs.*')" :route="route('college-admin.programs')" />
                        @endcan

                        @can('subjects.view')
                            <x-side-bar.item text="Subjects" icon="book-open" />
                        @endcan
                    </x-side-bar.item>
                @endif

                {{-- DEPARTMENT ADMIN LINKS --}}
                {{-- @canany(['schedules.view', 'faculty_profiles.view']) --}}

                @if (auth()->user()
                        ?->can(['schedules.view', 'faculty_profiles.view', 'rooms.view']) &&
                        auth()->user()?->employeeProfile()->exists())
                    <x-side-bar.item text="Department" opened>

                        @can('schedules.view')
                            <x-side-bar.item text="Schedules" icon="calendar-days" />
                        @endcan


                        @can('faculty_profiles.view')
                            <x-side-bar.item text="Faculty" icon="identification" :current="request()->routeIs(
                                'department-admin.faculty-profiles',
                                'department-admin.faculty-profiles.*',
                            )" :route="route('department-admin.faculty-profiles')" />
                        @endcan

                        @can('rooms.menu')
                            <x-side-bar.item text="Rooms" icon="building-office" :current="request()->routeIs('department-admin.rooms', 'department-admin.rooms.*')" :route="route('department-admin.rooms')" />
                        @endcan

                    </x-side-bar.item>
                @endif
                {{-- @endcanany --}}

                {{-- SUPERADMIN ADMIN LINKS --}}
                @canany(['campuses.view', 'users.view', 'roles.view', 'permissions.view', 'assignments.manage'])
                    {{-- Campuses Links --}}
                    @can('campuses.view')
                        <x-side-bar.item text="Campuses/Colleges" opened>
                            <x-side-bar.item text="Campuses" icon="building-library" :current="request()->routeIs('admin.campuses', 'admin.campuses.*')" :route="route('admin.campuses')" />
                        </x-side-bar.item>
                    @endcan

                    {{-- User Management Links --}}
                    @canany(['users.view', 'roles.view', 'permissions.view', 'assignments.manage'])
                        <x-side-bar.item text="System Management" opened>
                            @can('users.view')
                                <x-side-bar.item text="User Accounts" icon="users" :current="request()->routeIs('admin.users', 'admin.users.*')" :route="route('admin.users')" />
                            @endcan
                            @can('roles.view')
                                <x-side-bar.item text="Roles" icon="shield-check" :current="request()->routeIs('admin.roles', 'admin.roles.*')" :route="route('admin.roles')" />
                            @endcan
                            @can('permissions.view')
                                <x-side-bar.item text="Permissions" icon="key" :current="request()->routeIs('admin.permissions', 'admin.permissions.*')" :route="route('admin.permissions')" />
                            @endcan
                            @can('assignments.manage')
                                <x-side-bar.item text="Assignments" icon="link" :current="request()->routeIs('admin.assignments', 'admin.assignments.*')" :route="route('admin.assignments')" />
                            @endcan
                        </x-side-bar.item>
                    @endcanany
                @endcanany
            </x-side-bar>
        </x-slot:menu>

        {{-- MAIN CONTENTS --}}
        <main class="max-w-7xl mx-auto">
            {{ $slot }}
        </main>
    </x-layout>

    @livewireScripts
</body>

</html>
