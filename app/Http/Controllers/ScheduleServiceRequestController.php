<?php

namespace App\Http\Controllers;

use App\Models\ScheduleServiceRequest;
use App\Services\ScheduleWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleServiceRequestController extends Controller
{
    public function __construct(private readonly ScheduleWorkflowService $workflowService)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_ids' => ['required', 'array', 'min:1'],
            'schedule_ids.*' => ['integer', 'exists:schedules,id'],
            'requesting_college_id' => ['required', 'integer', 'exists:colleges,id'],
            'servicing_college_id' => ['required', 'integer', 'exists:colleges,id', 'different:requesting_college_id'],
        ]);

        $serviceRequest = $this->workflowService->createServiceRequest(
            (int) $validated['requesting_college_id'],
            (int) $validated['servicing_college_id'],
            array_map('intval', $validated['schedule_ids']),
        );

        return response()->json([
            'message' => 'Service request submitted successfully.',
            'service_request' => $serviceRequest->load(['schedules.sections', 'schedules.subject', 'requestingCollege', 'servicingCollege']),
        ], 201);
    }

    public function incoming(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'servicing_college_id' => ['required', 'integer', 'exists:colleges,id'],
            'status' => ['nullable', 'in:pending,accepted,rejected,assigned_to_dept,dept_submitted,completed'],
        ]);

        $requests = ScheduleServiceRequest::query()
            ->with(['schedules.sections', 'schedules.subject', 'requestingCollege', 'servicingCollege', 'assignedDepartment'])
            ->where('servicing_college_id', (int) $validated['servicing_college_id'])
            ->when(filled($validated['status'] ?? null), fn ($query) => $query->where('status', $validated['status']))
            ->latest()
            ->get();

        return response()->json([
            'incoming_requests' => $requests,
        ]);
    }

    public function respond(Request $request, int $serviceRequestId): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:accept,reject'],
        ]);

        $serviceRequest = $this->workflowService->respondToServiceRequest(
            $serviceRequestId,
            $validated['action'] === 'accept'
        );

        return response()->json([
            'message' => 'Service request updated.',
            'service_request' => $serviceRequest->load(['schedules.sections', 'schedules.subject', 'requestingCollege', 'servicingCollege']),
        ]);
    }

    public function assignDepartment(Request $request, int $serviceRequestId): JsonResponse
    {
        $validated = $request->validate([
            'assigned_department_id' => ['required', 'integer', 'exists:departments,id'],
        ]);

        $serviceRequest = $this->workflowService->assignToDepartment(
            $serviceRequestId,
            (int) $validated['assigned_department_id'],
        );

        return response()->json([
            'message' => 'Service request delegated to department.',
            'service_request' => $serviceRequest->load(['schedules.sections', 'schedules.subject', 'assignedDepartment']),
        ]);
    }
}
