<?php

namespace App\Enums;

enum PermissionEnum: string
{
    /**
     * DASHBOARD ACCESS PERMISSION
     */
    case DASHBOARD_VIEW = 'dashboard.view';

    /**
     * GENERAL PERMISSIONS
     */
    case PROFILE_VIEW = 'profile.view';
    case PROFILE_EDIT = 'profile.edit';

    /**
     * COLLEGE LEVEL PERMISSIONS
     */
    // Department Management
    case DEPARTMENT_VIEW = 'departments.view';
    case DEPARTMENT_CREATE = 'departments.create';
    case DEPARTMENT_EDIT = 'departments.edit';
    case DEPARTMENT_DELETE = 'departments.delete';
    case DEPARTMENT_RESTORE = 'departments.restore';

    /**
     * DEPARTMENT LEVEL PERMISSIONS
     */
    // Schedule Management
    case SCHEDULE_VIEW = 'schedules.view';
    case SCHEDULE_CREATE = 'schedules.create';
    case SCHEDULE_EDIT = 'schedules.edit';
    case SCHEDULE_DELETE = 'schedules.delete';
    case SCHEDULE_RESTORE = 'schedules.restore';
    case SCHEDULE_ASSIGN = 'schedules.assign';

    // Faculty Profiles Management
    case FACULTY_PROFILE_VIEW = 'faculty_profiles.view';
    case FACULTY_PROFILE_EDIT = 'faculty_profiles.edit';
    case FACULTY_PROFILE_DELETE = 'faculty_profiles.delete';
    case FACULTY_PROFILE_RESTORE = 'faculty_profiles.restore';

    // Course/Programs Management
    case COURSE_VIEW = 'courses.view';
    case COURSE_CREATE = 'courses.create';
    case COURSE_EDIT = 'courses.edit';
    case COURSE_DELETE = 'courses.delete';
    case COURSE_RESTORE = 'courses.restore';

    // Rooms Management
    case ROOM_VIEW = 'rooms.view';
    case ROOM_CREATE = 'rooms.create';
    case ROOM_EDIT = 'rooms.edit';
    case ROOM_DELETE = 'rooms.delete';
    case ROOM_RESTORE = 'rooms.restore';

    /**
     * FACULTY ROLE PERMISSIONS
     */
    case FACULTY_SCHEDULE_VIEW = 'faculty_schedules.view';

    /**
     * SUPER ADMIN PERMISSIONS
     */
    // Campus Management
    case CAMPUS_VIEW = 'campuses.view';
    case CAMPUS_CREATE = 'campuses.create';
    case CAMPUS_EDIT = 'campuses.edit';
    case CAMPUS_DELETE = 'campuses.delete';
    case CAMPUS_RESTORE = 'campuses.restore';

    // College Management
    case COLLEGE_VIEW = 'colleges.view';
    case COLLEGE_CREATE = 'colleges.create';
    case COLLEGE_EDIT = 'colleges.edit';
    case COLLEGE_DELETE = 'colleges.delete';
    case COLLEGE_RESTORE = 'colleges.restore';

    /**
     * SYSTEM MANAGEMENT PERMISSIONS
     */

    // User Management
    case USER_VIEW = 'users.view';
    case USER_CREATE = 'users.create';
    case USER_EDIT = 'users.edit';
    case USER_DELETE = 'users.delete';
    case USER_RESTORE = 'users.restore';

    // Role Management
    case ROLE_VIEW = 'roles.view';
    case ROLE_CREATE = 'roles.create';
    case ROLE_EDIT = 'roles.edit';
    case ROLE_DELETE = 'roles.delete';
    case ROLE_RESTORE = 'roles.restore';

    // Permission Management
    case PERMISSION_VIEW = 'permissions.view';
    case PERMISSION_CREATE = 'permissions.create';
    case PERMISSION_EDIT = 'permissions.edit';
    case PERMISSION_DELETE = 'permissions.delete';
    case PERMISSION_RESTORE = 'permissions.restore';

    // Assignment
    case ASSIGNMENT_MANAGE = 'assignments.manage';
}
