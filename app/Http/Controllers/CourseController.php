<?php

namespace App\Http\Controllers;

use App\Services\Courses\CourseAccessService;
use App\Services\Courses\CourseProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Marvel\Database\Models\Course;
use Marvel\Database\Models\Lesson;
use Marvel\Database\Models\LessonProgress;
use Marvel\Database\Models\User;

class CourseController extends Controller
{
    public function __construct(
        protected CourseAccessService $access,
        protected CourseProgressService $progress
    ) {
    }

    protected function optionalUser(Request $request): ?User
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }
        $accessToken = PersonalAccessToken::findToken($token);
        $model = $accessToken?->tokenable;

        return $model instanceof User ? $model : null;
    }

    public function index(): JsonResponse
    {
        $courses = Course::query()
            ->withCount('lessons')
            ->orderBy('id')
            ->get();

        return response()->json(['courses' => $courses]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $course = Course::query()->with('lessons')->findOrFail($id);
        $user = $this->optionalUser($request);

        $subscription = $this->access->getActiveSubscription($user, $course);

        $lessonIds = $course->lessons->pluck('id');
        $progressMap = [];
        if ($user && $lessonIds->isNotEmpty()) {
            $progressMap = LessonProgress::query()
                ->where('user_id', $user->id)
                ->whereIn('lesson_id', $lessonIds)
                ->get()
                ->keyBy('lesson_id')
                ->all();
        }

        $canCourse = $this->access->canAccessCourse($user, $course);

        $lessons = $course->lessons->map(function (Lesson $lesson) use ($user, $subscription, $progressMap, $course, $canCourse) {
            $completed = isset($progressMap[$lesson->id]) && $progressMap[$lesson->id]->completed_at;
            $unlocked = $canCourse && $this->access->canAccessLesson($user, $lesson);
            $availableAt = $this->access->lessonAvailableAt($lesson, $subscription);
            $showDate = !$course->required_product_id || $subscription !== null;

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'position' => $lesson->position,
                'content_type' => $lesson->content_type,
                'locked' => !$unlocked,
                'completed' => (bool) $completed,
                'available_at' => $showDate ? $availableAt->format('Y-m-d') : null,
            ];
        });

        return response()->json([
            'course' => $course->only(['id', 'title', 'description', 'required_product_id', 'created_at']),
            'lessons' => $lessons,
        ]);
    }

    public function lesson(Request $request, int $id): JsonResponse
    {
        $lesson = Lesson::query()->with('course')->findOrFail($id);
        $user = $this->optionalUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$this->access->canAccessLesson($user, $lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content_type' => $lesson->content_type,
                'content_url' => $lesson->content_url,
                'content_body' => $lesson->content_body,
            ],
        ]);
    }

    public function completeLesson(Request $request, int $id): JsonResponse
    {
        $lesson = Lesson::query()->with('course')->findOrFail($id);
        $user = $this->optionalUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$this->access->canAccessLesson($user, $lesson)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $row = $this->progress->markLessonComplete($user->id, $lesson->id);

        return response()->json([
            'success' => true,
            'progress' => [
                'lesson_id' => $lesson->id,
                'completed_at' => $row->completed_at?->toIso8601String(),
            ],
        ]);
    }

    public function courseProgress(Request $request, int $id): JsonResponse
    {
        $course = Course::query()->with('lessons')->findOrFail($id);
        $user = $this->optionalUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!$this->access->canAccessCourse($user, $course)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($this->progress->getCourseProgress($user->id, $course));
    }
}
