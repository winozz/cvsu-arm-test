<?php

use App\Livewire\Forms\Admin\SubjectForm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Program;
use App\Models\Subject;
use App\Models\SubjectAssignment;
use App\Models\SubjectAssignmentRequest;
use App\Models\SubjectUserAction;
use App\Support\SubjectDuplicateDetector;
use App\Traits\CanManage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use TallStackUi\Traits\Interactions;

new class extends Component {
    use CanManage, Interactions;

    public Campus $campus;

    public ?College $college = null;

    public SubjectForm $subjectForm;

    public bool $subjectModal = false;

    public bool $requestModal = false;

    public bool $isEditingSubject = false;

    public ?int $pendingSubjectSubmissionId = null;

    public ?int $requestSubjectId = null;

    public string $requestType = SubjectAssignmentRequest::TYPE_ASSIGN;

    public ?int $targetCampusId = null;

    public ?int $targetCollegeId = null;

    public ?string $subjectDuplicateConflictType = null;

    public array $subjectExactDuplicateConflicts = [];

    public array $subjectSimilarDuplicateConflicts = [];

    public bool $subjectSimilarityConfirmed = false;

    public function mount(): void
    {
        $this->ensureCanManage('subjects.view');
        $this->resolveManagedScope();
    }

    #[Computed]
    public function stats(): array
    {
        return [
            'assigned' => Subject::query()
                ->where('status', Subject::STATUS_SUBMITTED)
                ->whereHas('subjectAssignments', fn (Builder $query) => $this->applyManagedScope($query))
                ->count(),
            'catalog' => Subject::query()
                ->where('status', Subject::STATUS_SUBMITTED)
                ->count(),
            'drafts' => Subject::query()
                ->where('status', Subject::STATUS_DRAFT)
                ->where('created_by', auth()->id())
                ->count(),
        ];
    }

    #[Computed]
    public function programOptions(): array
    {
        return $this->managedProgramsQuery()
            ->orderBy('code')
            ->get(['programs.id', 'programs.code', 'programs.title'])
            ->map(fn (Program $program) => [
                'label' => trim($program->code.' - '.$program->title, ' -'),
                'value' => $program->id,
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function requestCampusOptions(): array
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (Campus $campus) => [
                'label' => trim($campus->code.' - '.$campus->name, ' -'),
                'value' => $campus->id,
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function requestCollegeOptions(): array
    {
        if (! filled($this->targetCampusId) || ! $this->targetCampusUsesCollegeScope()) {
            return [];
        }

        return College::query()
            ->where('campus_id', $this->targetCampusId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (College $college) => [
                'label' => trim($college->code.' - '.$college->name, ' -'),
                'value' => $college->id,
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function managedScopeLabel(): string
    {
        if ($this->isMainCampusScope()) {
            return trim($this->campus->code.' / '.$this->college?->code, ' /');
        }

        return $this->campus->code;
    }

    #[Computed]
    public function requestSubjectLabel(): string
    {
        if (! filled($this->requestSubjectId)) {
            return '-';
        }

        $subject = Subject::query()->find($this->requestSubjectId);

        return $subject?->display_name ?? '-';
    }

    public function updatedTargetCampusId(): void
    {
        if (! $this->targetCampusUsesCollegeScope()) {
            $this->targetCollegeId = null;
        }
    }

    public function openCreateSubjectModal(): void
    {
        $this->ensureCanManage('subjects.create');

        $this->resetValidation();
        $this->subjectForm->resetForm();
        $this->isEditingSubject = false;
        $this->resetSubjectDuplicateState();
        $this->subjectModal = true;
    }

    #[On('openEditSubjectModal')]
    public function openEditSubjectModal(Subject $subject): void
    {
        $this->ensureCanManage('subjects.update');

        $subject = $this->findManagedDraftSubject($subject->id);

        $this->resetValidation();
        $this->subjectForm->setSubject($subject);
        $this->isEditingSubject = true;
        $this->resetSubjectDuplicateState();
        $this->subjectModal = true;
    }

    public function closeSubjectModal(): void
    {
        $this->subjectModal = false;
        $this->isEditingSubject = false;
        $this->resetValidation();
        $this->resetSubjectDuplicateState();
        $this->subjectForm->resetForm();
    }

    public function reopenSubjectModal(): void
    {
        $this->subjectModal = true;
    }

    public function confirmSaveDraftSubject(): void
    {
        $this->ensureCanManage($this->isEditingSubject ? 'subjects.update' : 'subjects.create');

        $this->subjectForm->validateForm();
        $this->assertSelectedProgramsAreAllowed($this->subjectForm->program_ids);
        $this->subjectModal = false;

        $title = $this->isEditingSubject ? 'Save draft changes?' : 'Save draft subject?';
        $description = $this->isEditingSubject
            ? 'Your draft subject will be updated and remain visible only to you until submission.'
            : 'This subject will be saved as a draft and remain visible only to you until submission.';
        $confirm = $this->isEditingSubject ? 'Yes, save draft' : 'Yes, create draft';

        $this->dialog()
            ->question($title, $description)
            ->confirm($confirm, 'saveSubjectDraft')
            ->cancel('Cancel', 'reopenSubjectModal')
            ->send();
    }

    public function saveSubjectDraft(): void
    {
        $this->ensureCanManage($this->isEditingSubject ? 'subjects.update' : 'subjects.create');

        try {
            $validated = $this->subjectForm->validateForm();
            $programIds = $this->validatedProgramIds();
            $successMessage = $this->isEditingSubject
                ? 'Draft subject updated successfully.'
                : 'Draft subject created successfully.';

            DB::transaction(function () use ($validated, $programIds): void {
                if ($this->isEditingSubject) {
                    $subject = $this->findManagedDraftSubject($this->subjectForm->subject->id);
                    $subject->update($this->subjectForm->payload($validated));
                    $subject->programs()->sync($programIds);
                    $this->recordSubjectAction($subject, 'draft_updated', 'Draft subject updated.');
                } else {
                    $subject = Subject::create(array_merge(
                        $this->subjectForm->payload($validated),
                        [
                            'status' => Subject::STATUS_DRAFT,
                            'created_by' => auth()->id(),
                        ]
                    ));

                    $subject->programs()->sync($programIds);
                    $this->recordSubjectAction($subject, 'draft_created', 'Draft subject created.');
                }
            });

            $this->closeSubjectModal();
            $this->refreshSubjectTables();
            $this->toast()->success('Success', $successMessage)->send();
        } catch (ValidationException $e) {
            $this->reopenSubjectModal();

            throw $e;
        } catch (\Throwable $e) {
            $this->reopenSubjectModal();
            Log::error('Subject Draft Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while saving the draft subject.')->send();
        }
    }

    #[On('confirmSubmitSubject')]
    public function confirmSubmitSubject(Subject $subject): void
    {
        $this->ensureCanManage('subjects.update');

        $subject = $this->findManagedDraftSubject($subject->id);
        $this->pendingSubjectSubmissionId = $subject->id;

        $conflicts = SubjectDuplicateDetector::findConflicts($subject, $subject->code, $subject->title);

        if ($conflicts['exact'] !== []) {
            $this->subjectDuplicateConflictType = 'exact';
            $this->subjectExactDuplicateConflicts = $conflicts['exact'];
            $this->subjectSimilarDuplicateConflicts = $conflicts['similar'];

            $this->dialog()
                ->warning('Exact Duplicate Subject Found', SubjectDuplicateDetector::exactWarningMessage($conflicts['exact'], $conflicts['similar']))
                ->confirm('Okay', 'noopSubjectAction')
                ->send();

            return;
        }

        if ($conflicts['similar'] !== []) {
            $this->subjectDuplicateConflictType = 'similar';
            $this->subjectExactDuplicateConflicts = [];
            $this->subjectSimilarDuplicateConflicts = $conflicts['similar'];

            $this->dialog()
                ->warning('Possible Duplicate Subject', SubjectDuplicateDetector::similarWarningMessage($conflicts['similar']))
                ->confirm('Proceed anyway', 'proceedWithSimilarSubjectSubmission')
                ->cancel('Cancel')
                ->send();

            return;
        }

        $this->resetSubjectDuplicateState();
        $this->openSubmitSubjectDialog();
    }

    public function proceedWithSimilarSubjectSubmission(): void
    {
        $this->subjectSimilarityConfirmed = true;
        $this->openSubmitSubjectDialog();
    }

    public function openSubmitSubjectDialog(): void
    {
        $subject = $this->pendingSubjectSubmissionId
            ? $this->findManagedDraftSubject($this->pendingSubjectSubmissionId)
            : null;

        abort_unless($subject, 404);

        $this->dialog()
            ->question(
                'Submit Subject?',
                'Submitting '.e($subject->display_name).' will lock the subject record from further edits and assign it to your current scope.'
            )
            ->confirm('Yes, submit', 'submitPendingSubject')
            ->cancel('Cancel')
            ->send();
    }

    public function submitPendingSubject(): void
    {
        $this->ensureCanManage('subjects.update');

        $subjectId = $this->pendingSubjectSubmissionId;
        abort_unless(filled($subjectId), 404);

        try {
            DB::transaction(function () use ($subjectId): void {
                $subject = $this->findManagedDraftSubject((int) $subjectId);

                if (! $this->subjectSimilarityConfirmed) {
                    $conflicts = SubjectDuplicateDetector::findConflicts($subject, $subject->code, $subject->title);

                    if ($conflicts['exact'] !== []) {
                        throw ValidationException::withMessages([
                            'subject' => 'Duplicate subject submission was blocked.',
                        ]);
                    }
                }

                $subject->update([
                    'status' => Subject::STATUS_SUBMITTED,
                    'submitted_by' => auth()->id(),
                    'submitted_at' => now(),
                ]);

                $this->createManagedAssignment($subject);
                $this->recordSubjectAction($subject, 'submitted', 'Draft subject submitted.');
            });

            $this->pendingSubjectSubmissionId = null;
            $this->resetSubjectDuplicateState();
            $this->refreshSubjectTables();
            $this->toast()->success('Success', 'Subject submitted successfully.')->send();
        } catch (ValidationException $e) {
            $this->toast()->error('Duplicate detected', 'The subject matches an existing submitted subject and could not be submitted.')->send();

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Subject Submission Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'An unexpected error occurred while submitting the subject.')->send();
        }
    }

    #[On('confirmDeleteSubjectDraft')]
    public function confirmDeleteSubjectDraft(Subject $subject): void
    {
        $this->ensureCanManage('subjects.delete');

        $subject = $this->findManagedDraftSubject($subject->id);

        $this->dialog()
            ->question('Move Draft to Trash?', 'Are you sure you want to move '.e($subject->display_name).' to trash?')
            ->confirm('Yes, move to trash', 'deleteSubjectDraft', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    public function deleteSubjectDraft(int $id): void
    {
        $this->ensureCanManage('subjects.delete');

        try {
            $subject = $this->findManagedDraftSubject($id);
            $subject->delete();
            $this->recordSubjectAction($subject, 'draft_deleted', 'Draft subject moved to trash.');
            $this->refreshSubjectTables();
            $this->toast()->success('Deleted', 'Draft subject moved to trash.')->send();
        } catch (\Throwable $e) {
            Log::error('Draft Subject Deletion Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to delete the draft subject.')->send();
        }
    }

    #[On('confirmRestoreSubjectDraft')]
    public function confirmRestoreSubjectDraft(int $subject): void
    {
        $this->ensureCanManage('subjects.restore');

        $subject = $this->findManagedDraftSubject($subject, true);

        $this->dialog()
            ->question('Restore Draft?', 'Are you sure you want to restore '.e($subject->display_name).' from trash?')
            ->confirm('Yes, restore', 'restoreSubjectDraft', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    public function restoreSubjectDraft(int $id): void
    {
        $this->ensureCanManage('subjects.restore');

        try {
            $subject = $this->findManagedDraftSubject($id, true);
            $subject->restore();
            $this->recordSubjectAction($subject, 'draft_restored', 'Draft subject restored from trash.');
            $this->refreshSubjectTables();
            $this->toast()->success('Restored', 'Draft subject restored successfully.')->send();
        } catch (\Throwable $e) {
            Log::error('Draft Subject Restore Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to restore the draft subject.')->send();
        }
    }

    #[On('confirmAssignSubject')]
    public function confirmAssignSubject(Subject $subject): void
    {
        $this->ensureCanManage('subjects.update');

        $subject = $this->findVisibleSubmittedSubject($subject->id);
        abort_if($subject->subjectAssignments()->exists(), 403);

        $this->dialog()
            ->question('Assign Subject?', 'Assign '.e($subject->display_name).' to '.e($this->managedScopeLabel()).'?')
            ->confirm('Yes, assign', 'assignSubject', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    public function assignSubject(int $id): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            DB::transaction(function () use ($id): void {
                $subject = $this->findVisibleSubmittedSubject($id);
                abort_if($subject->subjectAssignments()->exists(), 403);
                $this->createManagedAssignment($subject);
                $this->recordSubjectAction($subject, 'self_assigned', 'Subject assigned to the current scope.');
            });

            $this->refreshSubjectTables();
            $this->toast()->success('Assigned', 'Subject assigned to your scope successfully.')->send();
        } catch (\Throwable $e) {
            Log::error('Subject Assignment Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to assign the subject to your scope.')->send();
        }
    }

    #[On('confirmUnassignSubject')]
    public function confirmUnassignSubject(Subject $subject): void
    {
        $this->ensureCanManage('subjects.update');

        $subject = $this->findManagedAssignedSubject($subject->id);

        $this->dialog()
            ->question('Unassign Subject?', 'Remove '.e($subject->display_name).' from '.e($this->managedScopeLabel()).' only?')
            ->confirm('Yes, unassign', 'unassignSubject', $subject->id)
            ->cancel('Cancel')
            ->send();
    }

    public function unassignSubject(int $id): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            DB::transaction(function () use ($id): void {
                $subject = $this->findManagedAssignedSubject($id);
                $this->managedAssignmentQuery($subject->id)->delete();
                $this->recordSubjectAction($subject, 'self_unassigned', 'Subject unassigned from the current scope.');
            });

            $this->refreshSubjectTables();
            $this->toast()->success('Updated', 'Subject unassigned from your scope.')->send();
        } catch (\Throwable $e) {
            Log::error('Subject Unassignment Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to unassign the subject from your scope.')->send();
        }
    }

    #[On('openSubjectRequestModal')]
    public function openSubjectRequestModal(Subject $subject, string $type): void
    {
        $this->ensureCanManage('subjects.update');

        $subject = $this->findManagedAssignedSubject($subject->id);

        $this->requestSubjectId = $subject->id;
        $this->requestType = $type;
        $this->targetCampusId = null;
        $this->targetCollegeId = null;
        $this->resetValidation();
        $this->requestModal = true;
    }

    public function closeRequestModal(): void
    {
        $this->requestModal = false;
        $this->requestSubjectId = null;
        $this->requestType = SubjectAssignmentRequest::TYPE_ASSIGN;
        $this->targetCampusId = null;
        $this->targetCollegeId = null;
        $this->resetValidation();
    }

    public function reopenRequestModal(): void
    {
        $this->requestModal = true;
    }

    public function confirmSaveSubjectRequest(): void
    {
        $this->ensureCanManage('subjects.update');

        $this->validateSubjectRequestInput();
        $this->requestModal = false;

        $title = $this->requestType === SubjectAssignmentRequest::TYPE_TRANSFER
            ? 'Send transfer request?'
            : 'Send assign request?';

        $this->dialog()
            ->question($title, 'The target scope will be able to accept or reject this request.')
            ->confirm('Yes, send request', 'saveSubjectRequest')
            ->cancel('Cancel', 'reopenRequestModal')
            ->send();
    }

    public function saveSubjectRequest(): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            $validated = $this->validateSubjectRequestInput();
            $subject = $this->findManagedAssignedSubject((int) $this->requestSubjectId);
            $targetScope = $this->normalizeTargetScope($validated['targetCampusId'], $validated['targetCollegeId'] ?? null);

            if ($targetScope['campus_id'] === $this->campus->id && $targetScope['college_id'] === $this->managedCollegeId()) {
                throw ValidationException::withMessages([
                    'targetCampusId' => 'Please select a different target scope.',
                ]);
            }

            if ($this->scopeAssignmentExists($subject->id, $targetScope['campus_id'], $targetScope['college_id'])) {
                throw ValidationException::withMessages([
                    'targetCampusId' => 'That scope is already assigned to this subject.',
                ]);
            }

            $hasDuplicatePendingRequest = SubjectAssignmentRequest::query()
                ->where('status', SubjectAssignmentRequest::STATUS_PENDING)
                ->where('subject_id', $subject->id)
                ->where('request_type', $this->requestType)
                ->where('source_campus_id', $this->campus->id)
                ->where('source_college_id', $this->managedCollegeId())
                ->where('target_campus_id', $targetScope['campus_id'])
                ->where('target_college_id', $targetScope['college_id'])
                ->exists();

            if ($hasDuplicatePendingRequest) {
                throw ValidationException::withMessages([
                    'targetCampusId' => 'A matching pending request already exists.',
                ]);
            }

            DB::transaction(function () use ($subject, $targetScope): void {
                SubjectAssignmentRequest::create([
                    'subject_id' => $subject->id,
                    'request_type' => $this->requestType,
                    'status' => SubjectAssignmentRequest::STATUS_PENDING,
                    'source_campus_id' => $this->campus->id,
                    'source_college_id' => $this->managedCollegeId(),
                    'target_campus_id' => $targetScope['campus_id'],
                    'target_college_id' => $targetScope['college_id'],
                    'requested_by' => auth()->id(),
                ]);

                $this->recordSubjectAction($subject, 'request_created', ucfirst($this->requestType).' request created.');
            });

            $this->closeRequestModal();
            $this->refreshSubjectTables();
            $this->toast()->success('Request sent', 'Subject request sent successfully.')->send();
        } catch (ValidationException $e) {
            $this->reopenRequestModal();

            throw $e;
        } catch (\Throwable $e) {
            $this->reopenRequestModal();
            Log::error('Subject Request Save Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to save the subject request.')->send();
        }
    }

    #[On('confirmAcceptSubjectRequest')]
    public function confirmAcceptSubjectRequest(SubjectAssignmentRequest $request): void
    {
        $this->ensureCanManage('subjects.update');

        $request = $this->findIncomingSubjectRequest($request->id);

        $this->dialog()
            ->question('Accept Request?', 'Accept the '.$request->request_type_label.' request for '.e($request->subject?->display_name ?? 'this subject').'?')
            ->confirm('Yes, accept', 'acceptSubjectRequest', $request->id)
            ->cancel('Cancel')
            ->send();
    }

    public function acceptSubjectRequest(int $id): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            DB::transaction(function () use ($id): void {
                $request = $this->findIncomingSubjectRequest($id);

                if (! $this->scopeAssignmentExists($request->subject_id, $request->target_campus_id, $request->target_college_id)) {
                    SubjectAssignment::create([
                        'subject_id' => $request->subject_id,
                        'campus_id' => $request->target_campus_id,
                        'college_id' => $request->target_college_id,
                    ]);
                }

                if ($request->request_type === SubjectAssignmentRequest::TYPE_TRANSFER) {
                    SubjectAssignment::query()
                        ->where('subject_id', $request->subject_id)
                        ->where('campus_id', $request->source_campus_id)
                        ->when(
                            filled($request->source_college_id),
                            fn (Builder $query) => $query->where('college_id', $request->source_college_id),
                            fn (Builder $query) => $query->whereNull('college_id')
                        )
                        ->delete();
                }

                $request->update([
                    'status' => SubjectAssignmentRequest::STATUS_ACCEPTED,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                ]);

                $this->recordSubjectAction($request->subject, 'request_accepted', ucfirst($request->request_type).' request accepted.');
            });

            $this->refreshSubjectTables();
            $this->toast()->success('Accepted', 'Subject request accepted successfully.')->send();
        } catch (\Throwable $e) {
            Log::error('Subject Request Accept Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to accept the subject request.')->send();
        }
    }

    #[On('confirmRejectSubjectRequest')]
    public function confirmRejectSubjectRequest(SubjectAssignmentRequest $request): void
    {
        $this->ensureCanManage('subjects.update');

        $request = $this->findIncomingSubjectRequest($request->id);

        $this->dialog()
            ->question('Reject Request?', 'Reject the '.$request->request_type_label.' request for '.e($request->subject?->display_name ?? 'this subject').'?')
            ->confirm('Yes, reject', 'rejectSubjectRequest', $request->id)
            ->cancel('Cancel')
            ->send();
    }

    public function rejectSubjectRequest(int $id): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            $request = $this->findIncomingSubjectRequest($id);
            $request->update([
                'status' => SubjectAssignmentRequest::STATUS_REJECTED,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $this->recordSubjectAction($request->subject, 'request_rejected', ucfirst($request->request_type).' request rejected.');
            $this->refreshSubjectTables();
            $this->toast()->success('Rejected', 'Subject request rejected successfully.')->send();
        } catch (\Throwable $e) {
            Log::error('Subject Request Reject Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to reject the subject request.')->send();
        }
    }

    #[On('confirmCancelSubjectRequest')]
    public function confirmCancelSubjectRequest(SubjectAssignmentRequest $request): void
    {
        $this->ensureCanManage('subjects.update');

        $request = $this->findOutgoingSubjectRequest($request->id);

        $this->dialog()
            ->question('Cancel Request?', 'Cancel the pending '.$request->request_type_label.' request for '.e($request->subject?->display_name ?? 'this subject').'?')
            ->confirm('Yes, cancel', 'cancelSubjectRequest', $request->id)
            ->cancel('Keep Request')
            ->send();
    }

    public function cancelSubjectRequest(int $id): void
    {
        $this->ensureCanManage('subjects.update');

        try {
            $request = $this->findOutgoingSubjectRequest($id);
            $request->update([
                'status' => SubjectAssignmentRequest::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);

            $this->recordSubjectAction($request->subject, 'request_cancelled', ucfirst($request->request_type).' request cancelled.');
            $this->refreshSubjectTables();
            $this->toast()->success('Cancelled', 'Subject request cancelled successfully.')->send();
        } catch (\Throwable $e) {
            Log::error('Subject Request Cancel Failed: '.$e->getMessage());
            $this->toast()->error('Error', 'Failed to cancel the subject request.')->send();
        }
    }

    public function noopSubjectAction(): void
    {
        // Dialog acknowledge action.
    }

    protected function resolveManagedScope(): void
    {
        $user = auth()
            ->guard()
            ->user()
            ?->loadMissing(['employeeProfile.campus', 'employeeProfile.college', 'facultyProfile.campus', 'facultyProfile.college']);
        $profile = $user?->employeeProfile ?? $user?->facultyProfile;

        abort_unless($profile?->campus, 403);

        $this->campus = $profile->campus;

        if ($this->isMainCampus($this->campus)) {
            abort_unless(filled($profile?->college_id) && $profile?->college, 403);
            $this->college = $profile->college;

            return;
        }

        $this->college = $profile?->college ?: $this->resolveEquivalentCollegeForCampus($this->campus);

        abort_unless($this->college, 403);
    }

    protected function isMainCampusScope(): bool
    {
        return $this->isMainCampus($this->campus);
    }

    protected function isMainCampus(Campus $campus): bool
    {
        $campusName = str($campus->name)->lower()->squish()->toString();
        $campusCode = str($campus->code)->lower()->replace([' ', '.', '-'], '')->toString();

        return $campusName === 'main campus' || $campusCode === 'cvsumain';
    }

    protected function resolveEquivalentCollegeForCampus(Campus $campus): ?College
    {
        return College::query()
            ->where('campus_id', $campus->id)
            ->orderBy('name')
            ->first();
    }

    protected function managedProgramsQuery(): Builder
    {
        abort_unless($this->college, 403);

        return Program::query()
            ->whereHas('colleges', fn (Builder $query) => $query->whereKey($this->college->id));
    }

    protected function assertSelectedProgramsAreAllowed(array $programIds): void
    {
        $allowedProgramIds = $this->managedProgramsQuery()
            ->pluck('programs.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalidProgramIds = collect($programIds)
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => in_array($id, $allowedProgramIds, true))
            ->values()
            ->all();

        if ($invalidProgramIds !== []) {
            throw ValidationException::withMessages([
                'subjectForm.program_ids' => 'One or more selected programs are not available for your scope.',
            ]);
        }
    }

    protected function validatedProgramIds(): array
    {
        $programIds = collect($this->subjectForm->program_ids)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertSelectedProgramsAreAllowed($programIds);

        return $programIds;
    }

    protected function resetSubjectDuplicateState(): void
    {
        $this->subjectDuplicateConflictType = null;
        $this->subjectExactDuplicateConflicts = [];
        $this->subjectSimilarDuplicateConflicts = [];
        $this->subjectSimilarityConfirmed = false;
    }

    public function managedCollegeId(): ?int
    {
        return $this->isMainCampusScope() ? $this->college?->id : null;
    }

    protected function applyManagedScope(Builder $query): Builder
    {
        return $query
            ->where('campus_id', $this->campus->id)
            ->when(
                filled($this->managedCollegeId()),
                fn (Builder $builder) => $builder->where('college_id', $this->managedCollegeId()),
                fn (Builder $builder) => $builder->whereNull('college_id')
            );
    }

    protected function managedAssignmentQuery(int $subjectId): Builder
    {
        return SubjectAssignment::query()
            ->where('subject_id', $subjectId)
            ->where('campus_id', $this->campus->id)
            ->when(
                filled($this->managedCollegeId()),
                fn (Builder $builder) => $builder->where('college_id', $this->managedCollegeId()),
                fn (Builder $builder) => $builder->whereNull('college_id')
            );
    }

    protected function scopeAssignmentExists(int $subjectId, int $campusId, ?int $collegeId): bool
    {
        return SubjectAssignment::query()
            ->where('subject_id', $subjectId)
            ->where('campus_id', $campusId)
            ->when(
                filled($collegeId),
                fn (Builder $builder) => $builder->where('college_id', $collegeId),
                fn (Builder $builder) => $builder->whereNull('college_id')
            )
            ->exists();
    }

    protected function createManagedAssignment(Subject $subject): void
    {
        if ($this->managedAssignmentQuery($subject->id)->exists()) {
            return;
        }

        SubjectAssignment::create([
            'subject_id' => $subject->id,
            'campus_id' => $this->campus->id,
            'college_id' => $this->managedCollegeId(),
        ]);
    }

    protected function findManagedDraftSubject(int $id, bool $includeTrashed = false): Subject
    {
        $query = Subject::query()
            ->whereKey($id)
            ->where('status', Subject::STATUS_DRAFT)
            ->where('created_by', auth()->id());

        if ($includeTrashed) {
            $query->withTrashed();
        }

        return $query->firstOrFail();
    }

    protected function findVisibleSubmittedSubject(int $id): Subject
    {
        return Subject::query()
            ->whereKey($id)
            ->where('status', Subject::STATUS_SUBMITTED)
            ->firstOrFail();
    }

    protected function findManagedAssignedSubject(int $id): Subject
    {
        return Subject::query()
            ->whereKey($id)
            ->where('status', Subject::STATUS_SUBMITTED)
            ->whereHas('subjectAssignments', fn (Builder $query) => $this->applyManagedScope($query))
            ->firstOrFail();
    }

    protected function findIncomingSubjectRequest(int $id): SubjectAssignmentRequest
    {
        return SubjectAssignmentRequest::query()
            ->with('subject')
            ->whereKey($id)
            ->where('status', SubjectAssignmentRequest::STATUS_PENDING)
            ->where('target_campus_id', $this->campus->id)
            ->when(
                filled($this->managedCollegeId()),
                fn (Builder $builder) => $builder->where('target_college_id', $this->managedCollegeId()),
                fn (Builder $builder) => $builder->whereNull('target_college_id')
            )
            ->firstOrFail();
    }

    protected function findOutgoingSubjectRequest(int $id): SubjectAssignmentRequest
    {
        return SubjectAssignmentRequest::query()
            ->with('subject')
            ->whereKey($id)
            ->where('status', SubjectAssignmentRequest::STATUS_PENDING)
            ->where('requested_by', auth()->id())
            ->where('source_campus_id', $this->campus->id)
            ->when(
                filled($this->managedCollegeId()),
                fn (Builder $builder) => $builder->where('source_college_id', $this->managedCollegeId()),
                fn (Builder $builder) => $builder->whereNull('source_college_id')
            )
            ->firstOrFail();
    }

    protected function validateSubjectRequestInput(): array
    {
        return $this->validate([
            'requestSubjectId' => ['required', Rule::exists('subjects', 'id')->where(fn ($query) => $query->where('status', Subject::STATUS_SUBMITTED))],
            'requestType' => ['required', Rule::in([SubjectAssignmentRequest::TYPE_ASSIGN, SubjectAssignmentRequest::TYPE_TRANSFER])],
            'targetCampusId' => ['required', 'integer', 'exists:campuses,id'],
            'targetCollegeId' => [
                Rule::requiredIf(fn () => $this->targetCampusUsesCollegeScope()),
                'nullable',
                'integer',
                Rule::exists('colleges', 'id')->where(fn ($query) => $query->where('campus_id', $this->targetCampusId)),
            ],
        ]);
    }

    public function targetCampusUsesCollegeScope(): bool
    {
        if (! filled($this->targetCampusId)) {
            return false;
        }

        $campus = Campus::query()->find($this->targetCampusId);

        return $campus ? $this->isMainCampus($campus) : false;
    }

    protected function normalizeTargetScope(int $targetCampusId, ?int $targetCollegeId): array
    {
        if (! $this->targetCampusUsesCollegeScope()) {
            return [
                'campus_id' => $targetCampusId,
                'college_id' => null,
            ];
        }

        return [
            'campus_id' => $targetCampusId,
            'college_id' => $targetCollegeId,
        ];
    }

    protected function recordSubjectAction(Subject $subject, string $action, ?string $description = null): void
    {
        SubjectUserAction::create([
            'subject_id' => $subject->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
        ]);
    }

    protected function refreshSubjectTables(): void
    {
        $this->dispatch('pg:eventRefresh-assignedSubjectsTable');
        $this->dispatch('pg:eventRefresh-allSubjectsTable');
        $this->dispatch('pg:eventRefresh-subjectRequestsIncomingTable');
        $this->dispatch('pg:eventRefresh-subjectRequestsOutgoingTable');
    }
};
?>

<div class="space-y-6">
    <div class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-bold dark:text-white">Subjects</h1>
                <x-badge :text="$this->managedScopeLabel" color="primary" round />
            </div>
            <p class="italic text-zinc-600 dark:text-zinc-200">
                Manage shared subjects for your current scope, submit drafts, and handle assignment requests.
            </p>
        </div>

        @can('subjects.create')
            <x-button wire:click="openCreateSubjectModal" sm color="primary" icon="plus" text="New Subject Draft" />
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Assigned to My Scope</p>
                    <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $this->stats['assigned'] }}</p>
                </div>

                <div class="rounded-lg bg-blue-50 p-2 text-blue-600 dark:bg-blue-950/40 dark:text-blue-300">
                    <x-icon icon="book-open" class="h-5 w-5" />
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Submitted Catalog</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-600">{{ $this->stats['catalog'] }}</p>
                </div>

                <div class="rounded-lg bg-emerald-50 p-2 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300">
                    <x-icon icon="check-circle" class="h-5 w-5" />
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">My Drafts</p>
                    <p class="mt-1 text-2xl font-bold text-amber-600">{{ $this->stats['drafts'] }}</p>
                </div>

                <div class="rounded-lg bg-amber-50 p-2 text-amber-600 dark:bg-amber-950/40 dark:text-amber-300">
                    <x-icon icon="document-text" class="h-5 w-5" />
                </div>
            </div>
        </x-card>
    </div>

    <x-card>
        <div class="flex flex-col gap-2 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">Assigned to My Scope</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Drafts you created and submitted subjects currently assigned to {{ $this->managedScopeLabel }}.
                </p>
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.college-admin.assigned-subjects-table
                :campus-id="$campus->id"
                :college-id="$this->managedCollegeId()"
                :user-id="auth()->id()"
            />
        </div>
    </x-card>

    <x-card>
        <div class="flex flex-col gap-2 border-b border-zinc-200 pb-4 md:flex-row md:items-start md:justify-between">
            <div class="space-y-1">
                <h2 class="text-lg font-semibold dark:text-white">All Subjects</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    Submitted subjects in the shared catalog and the scopes they are currently assigned to.
                </p>
            </div>
        </div>

        <div class="p-6">
            <livewire:tables.college-admin.all-subjects-table
                :campus-id="$campus->id"
                :college-id="$this->managedCollegeId()"
            />
        </div>
    </x-card>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <div class="flex flex-col gap-2 border-b border-zinc-200 pb-4">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold dark:text-white">Incoming Requests</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Requests that your scope can accept or reject.
                    </p>
                </div>
            </div>

            <div class="p-6">
                <livewire:tables.college-admin.subject-requests-table
                    direction="incoming"
                    :campus-id="$campus->id"
                    :college-id="$this->managedCollegeId()"
                    :user-id="auth()->id()"
                />
            </div>
        </x-card>

        <x-card>
            <div class="flex flex-col gap-2 border-b border-zinc-200 pb-4">
                <div class="space-y-1">
                    <h2 class="text-lg font-semibold dark:text-white">Outgoing Requests</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                        Pending requests from your scope. Only the requester can cancel them.
                    </p>
                </div>
            </div>

            <div class="p-6">
                <livewire:tables.college-admin.subject-requests-table
                    direction="outgoing"
                    :campus-id="$campus->id"
                    :college-id="$this->managedCollegeId()"
                    :user-id="auth()->id()"
                />
            </div>
        </x-card>
    </div>

    <x-modal wire="subjectModal" title="{{ $isEditingSubject ? 'Edit Draft Subject' : 'New Subject Draft' }}" size="4xl">
        <div class="space-y-4">
            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Subject Code" wire:model="subjectForm.code" hint="Use a short catalog code like MATH101." />
                <x-input label="Subject Title" wire:model="subjectForm.title" />
            </div>

            <x-textarea label="Description" wire:model="subjectForm.description" hint="Optional short description for this subject." />

            <div class="grid gap-4 md:grid-cols-2">
                <x-input label="Lecture Units" type="number" min="0" wire:model="subjectForm.lecture_units" />
                <x-input label="Laboratory Units" type="number" min="0" wire:model="subjectForm.laboratory_units" />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <x-toggle wire:model="subjectForm.is_credit" label="Credit subject" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $subjectForm->is_credit ? 'This subject will be counted as credit.' : 'This subject will be treated as non-credit.' }}
                    </p>
                </div>

                <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <x-toggle wire:model="subjectForm.is_active" label="Subject is active" />
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $subjectForm->is_active ? 'This subject can be assigned after submission.' : 'This subject will stay inactive after submission.' }}
                    </p>
                </div>
            </div>

            <x-select.styled
                label="Programs"
                wire:model="subjectForm.program_ids"
                :options="$this->programOptions"
                select="label:label|value:value"
                multiple
                searchable
                hint="Only programs available to your current scope can be attached to this draft."
            />

            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/40 dark:text-zinc-300">
                Draft subjects are visible only to you. Once submitted, the subject becomes read-only and is assigned to {{ $this->managedScopeLabel }}.
            </div>
        </div>

        <x-slot:footer>
            @canany(['subjects.create', 'subjects.update'])
                <x-button flat text="Cancel" wire:click="closeSubjectModal" sm />
                <x-button color="primary" text="Save Draft" wire:click="confirmSaveDraftSubject" sm />
            @endcanany
        </x-slot:footer>
    </x-modal>

    <x-modal wire="requestModal" title="Subject Request" size="3xl">
        <div class="space-y-4">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900/40">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Subject</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->requestSubjectLabel }}</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <x-select.styled
                    label="Target Campus"
                    wire:model.live="targetCampusId"
                    :options="$this->requestCampusOptions"
                    select="label:label|value:value"
                    searchable
                />

                @if ($this->targetCampusUsesCollegeScope())
                    <x-select.styled
                        label="Target College"
                        wire:model="targetCollegeId"
                        :options="$this->requestCollegeOptions"
                        select="label:label|value:value"
                        searchable
                    />
                @endif
            </div>

            <div class="rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Request Type</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $requestType === \App\Models\SubjectAssignmentRequest::TYPE_TRANSFER
                        ? 'Transfer will move the subject assignment from your scope to the selected target once accepted.'
                        : 'Assign will keep your scope assignment and ask the selected target to add the same subject.' }}
                </p>
            </div>
        </div>

        <x-slot:footer>
            @can('subjects.update')
                <x-button flat text="Cancel" wire:click="closeRequestModal" sm />
                <x-button color="primary" text="Send Request" wire:click="confirmSaveSubjectRequest" sm />
            @endcan
        </x-slot:footer>
    </x-modal>
</div>
