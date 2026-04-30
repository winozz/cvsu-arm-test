<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleServiceRequest;
use Illuminate\Support\Facades\DB;

class ScheduleWorkflowService
{
    /**
     * Create a service request with a batch of schedule codes.
     *
     * @param  array<int>  $scheduleIds
     */
    public function createServiceRequest(int $requestingCollegeId, int $servicingCollegeId, array $scheduleIds): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($requestingCollegeId, $servicingCollegeId, $scheduleIds): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->create([
                'requesting_college_id' => $requestingCollegeId,
                'servicing_college_id' => $servicingCollegeId,
                'status' => 'pending',
            ]);

            foreach ($scheduleIds as $scheduleId) {
                $schedule = Schedule::query()->lockForUpdate()->findOrFail((int) $scheduleId);
                $schedule->update(['status' => 'pending_service_acceptance']);
                $serviceRequest->schedules()->attach($scheduleId);
            }

            return $serviceRequest->fresh(['schedules']);
        });
    }

    /** Servicing college admin accepts or rejects an incoming request. */
    public function respondToServiceRequest(int $serviceRequestId, bool $accept): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($serviceRequestId, $accept): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            $serviceRequest->status = $accept ? 'accepted' : 'rejected';
            $serviceRequest->save();

            $newScheduleStatus = $accept ? 'pending_plotting' : 'draft';

            $serviceRequest->schedules()->each(function (Schedule $schedule) use ($newScheduleStatus): void {
                $schedule->update(['status' => $newScheduleStatus]);
            });

            return $serviceRequest->fresh(['schedules']);
        });
    }

    /** Servicing college admin delegates the entire request batch to a department. */
    public function assignToDepartment(int $serviceRequestId, int $departmentId): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($serviceRequestId, $departmentId): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            $serviceRequest->assigned_department_id = $departmentId;
            $serviceRequest->status = 'assigned_to_dept';
            $serviceRequest->save();

            $serviceRequest->schedules()->each(function (Schedule $schedule) use ($departmentId): void {
                $schedule->department_id = $departmentId;
                $schedule->status = 'pending_plotting';
                $schedule->save();
            });

            return $serviceRequest->fresh(['schedules', 'assignedDepartment']);
        });
    }

    /**
     * Department admin removes a single schedule from an unsubmitted request.
     * The schedule reverts to draft status.
     */
    public function removeScheduleFromRequest(int $serviceRequestId, int $scheduleId): void
    {
        DB::transaction(function () use ($serviceRequestId, $scheduleId): void {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            abort_if(
                in_array($serviceRequest->status, ['dept_submitted', 'completed'], true),
                422,
                'Cannot remove a schedule after it has been submitted.'
            );

            $serviceRequest->schedules()->detach($scheduleId);

            Schedule::query()->find($scheduleId)?->update(['status' => 'draft', 'department_id' => null]);
        });
    }

    /** Department admin submits the plotted schedules back to the servicing college admin for review. */
    public function deptSubmit(int $serviceRequestId): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($serviceRequestId): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            abort_if(
                $serviceRequest->status !== 'assigned_to_dept',
                422,
                'Request must be in assigned_to_dept status to submit.'
            );
            $serviceRequest->status = 'dept_submitted';
            $serviceRequest->save();

            return $serviceRequest->fresh(['schedules']);
        });
    }

    /** Requesting college admin cancels an outgoing request that has not yet been assigned to a department. */
    public function cancelRequest(int $serviceRequestId): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($serviceRequestId): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            abort_if(
                ! in_array($serviceRequest->status, ['pending', 'accepted'], true),
                422,
                'Only pending or accepted requests can be cancelled.'
            );
            $serviceRequest->status = 'cancelled';
            $serviceRequest->save();

            $serviceRequest->schedules()->each(function (Schedule $schedule): void {
                $schedule->update(['status' => 'draft']);
            });

            return $serviceRequest->fresh(['schedules']);
        });
    }

    /** Servicing college admin verifies the submission and marks the request as completed. */
    public function completeRequest(int $serviceRequestId): ScheduleServiceRequest
    {
        return DB::transaction(function () use ($serviceRequestId): ScheduleServiceRequest {
            $serviceRequest = ScheduleServiceRequest::query()->lockForUpdate()->findOrFail($serviceRequestId);
            abort_if(
                $serviceRequest->status !== 'dept_submitted',
                422,
                'Request must be in dept_submitted status to complete.'
            );
            $serviceRequest->status = 'completed';
            $serviceRequest->save();

            $serviceRequest->schedules()->each(function (Schedule $schedule): void {
                $schedule->update(['status' => 'plotted']);
            });

            return $serviceRequest->fresh(['schedules']);
        });
    }
}
