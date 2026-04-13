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
                    <x-theme-switch />
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

                                {{--
                                <x-icon name="chevron-down" class="w-4 h-4 text-zinc-400" /> --}}
                            </button>
                        </x-slot:action>

                        <x-dropdown.items icon="user-circle" text="My Profile" />

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
                @hasanyrole(['superAdmin', 'faculty'])
                    {{-- Teaching Links --}}
                    <x-side-bar.item text="Faculty" opened>
                        <x-side-bar.item text="Schedules & Subjects" icon="clipboard-document-list" />
                        {{-- <x-side-bar.item text="Grades" icon="check-badge" />
                        <x-side-bar.item text="Teaching History" icon="academic-cap" /> --}}
                    </x-side-bar.item>
                @endhasanyrole

                {{-- COLLEGE ADMIN LINKS --}}
                @hasanyrole(['superAdmin', 'collegeAdmin'])
                    <x-side-bar.item text="College" opened>
                        <x-side-bar.item text="Departments" icon="briefcase" :current="request()->routeIs('college-admin.departments', 'college-admin.departments.*')" :route="route('college-admin.departments')" />
                        <x-side-bar.item text="Courses" icon="academic-cap" />
                    </x-side-bar.item>
                @endhasanyrole

                {{-- DEPARTMENT ADMIN LINKS --}}
                @hasanyrole(['superAdmin', 'deptAdmin'])
                    <x-side-bar.item text="Department" opened>
                        <x-side-bar.item text="Schedules" icon="calendar-days" />
                        <x-side-bar.item text="Faculty" icon="identification" :current="request()->routeIs('admin.faculty-profiles', 'admin.faculty-profiles.*')" :route="route('admin.faculty-profiles')" />
                        <x-side-bar.item text="Courses" icon="academic-cap" />
                        <x-side-bar.item text="Rooms" icon="building-office" />
                    </x-side-bar.item>
                @endhasanyrole

                {{-- SUPERADMIN ADMIN LINKS --}}
                @hasanyrole(['superAdmin'])
                    {{-- Campuses Links --}}
                    <x-side-bar.item text="Campuses/Colleges" opened>
                        <x-side-bar.item text="Campuses" icon="building-library" :current="request()->routeIs('admin.campuses', 'admin.campuses.*')" :route="route('admin.campuses')" />
                    </x-side-bar.item>

                    {{-- User Management Links --}}
                    <x-side-bar.item text="System Management" opened>
                        <x-side-bar.item text="User Accounts" icon="users" :current="request()->routeIs('admin.users', 'admin.users.*')" :route="route('admin.users')" />
                        <x-side-bar.item text="Roles" icon="shield-check" :current="request()->routeIs('admin.roles', 'admin.roles.*')" :route="route('admin.roles')" />
                        <x-side-bar.item text="Permissions" icon="key" :current="request()->routeIs('admin.permissions', 'admin.permissions.*')" :route="route('admin.permissions')" />
                    </x-side-bar.item>
                @endhasrole
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
