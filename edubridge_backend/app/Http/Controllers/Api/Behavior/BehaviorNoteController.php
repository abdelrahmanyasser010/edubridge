<?php

namespace App\Http\Controllers\Api\Behavior;

use App\Actions\Behavior\BehaviorNoteManager;
use App\Http\Requests\Behavior\StoreBehaviorNoteRequest;
use App\Http\Requests\Behavior\StoreBehaviorRecommendationRequest;
use App\Http\Requests\Behavior\TransitionBehaviorNoteRequest;
use App\Http\Resources\Behavior\BehaviorNoteResource;
use App\Models\BehaviorNote;
use App\Models\BehaviorRecommendation;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Teacher;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BehaviorNoteController
{
    public function store(StoreBehaviorNoteRequest $request, BehaviorNoteManager $manager): JsonResponse
    {
        Gate::authorize('create', BehaviorNote::class);

        return ApiResponse::data(
            (new BehaviorNoteResource($manager->create($this->currentTeacher(), $request->validated())))->resolve($request),
            Response::HTTP_CREATED,
        );
    }

    public function publish(TransitionBehaviorNoteRequest $request, int $note, BehaviorNoteManager $manager): JsonResponse
    {
        $note = BehaviorNote::query()->findOrFail($note);
        Gate::authorize('review', $note);

        return ApiResponse::data((new BehaviorNoteResource($manager->publish($note, $this->currentUserId(), $request->validated('note'))))->resolve($request));
    }

    public function reject(TransitionBehaviorNoteRequest $request, int $note, BehaviorNoteManager $manager): JsonResponse
    {
        $note = BehaviorNote::query()->findOrFail($note);
        Gate::authorize('review', $note);

        return ApiResponse::data((new BehaviorNoteResource($manager->reject($note, $this->currentUserId(), $request->validated('note'))))->resolve($request));
    }

    public function acknowledge(TransitionBehaviorNoteRequest $request, int $note, BehaviorNoteManager $manager): JsonResponse
    {
        $note = BehaviorNote::query()->findOrFail($note);
        Gate::authorize('acknowledge', $note);

        return ApiResponse::data((new BehaviorNoteResource($manager->acknowledge($note, $this->currentUserId(), $request->validated('note'))))->resolve($request));
    }

    public function resolve(TransitionBehaviorNoteRequest $request, int $note, BehaviorNoteManager $manager): JsonResponse
    {
        $note = BehaviorNote::query()->findOrFail($note);
        Gate::authorize('resolve', $note);

        return ApiResponse::data((new BehaviorNoteResource($manager->resolve($note, $this->currentUserId(), $request->validated('note'))))->resolve($request));
    }

    public function recommend(StoreBehaviorRecommendationRequest $request, int $note): JsonResponse
    {
        $note = BehaviorNote::query()->findOrFail($note);
        Gate::authorize('recommend', $note);

        BehaviorRecommendation::query()->create([
            'behavior_note_id' => $note->id,
            'created_by_central_user_id' => $this->currentUserId(),
            'body' => $request->validated('body'),
            'status' => BehaviorRecommendation::STATUS_PUBLISHED,
        ]);

        return ApiResponse::data((new BehaviorNoteResource($note->refresh()->load(['timeline', 'recommendations'])))->resolve($request), Response::HTTP_CREATED);
    }

    public function parentIndex(int $student): JsonResponse
    {
        $student = $this->parentStudent($student);

        return ApiResponse::data(BehaviorNoteResource::collection(
            BehaviorNote::query()
                ->with(['timeline', 'recommendations'])
                ->where('student_id', $student->id)
                ->whereIn('status', [BehaviorNote::STATUS_PUBLISHED, BehaviorNote::STATUS_ACKNOWLEDGED, BehaviorNote::STATUS_RESOLVED])
                ->orderByDesc('published_at')
                ->get(),
        )->resolve());
    }

    private function currentTeacher(): Teacher
    {
        $teacher = Teacher::query()
            ->where('central_user_id', $this->currentUserId())
            ->where('status', Teacher::STATUS_ACTIVE)
            ->first();

        return $teacher ?? throw new NotFoundHttpException;
    }

    private function parentStudent(int $student): Student
    {
        $student = Student::query()->findOrFail($student);

        $owned = StudentParent::query()
            ->join('parents', 'parents.id', '=', 'student_parent.parent_id')
            ->where('student_parent.student_id', $student->id)
            ->where('student_parent.status', StudentParent::STATUS_ACTIVE)
            ->where('parents.central_user_id', $this->currentUserId())
            ->where('parents.status', 'active')
            ->whereDate('student_parent.valid_from', '<=', now()->toDateString())
            ->where(fn ($query) => $query
                ->whereNull('student_parent.valid_until')
                ->orWhereDate('student_parent.valid_until', '>=', now()->toDateString()))
            ->exists();

        if (! $owned) {
            throw new NotFoundHttpException;
        }

        return $student;
    }

    private function currentUserId(): int
    {
        $user = request()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        return (int) $user->id;
    }
}
