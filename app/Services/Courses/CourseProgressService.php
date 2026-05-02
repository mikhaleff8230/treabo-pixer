<?php

namespace App\Services\Courses;

use Marvel\Database\Models\Course;
use Marvel\Database\Models\LessonProgress;

class CourseProgressService
{
    public function markLessonComplete(int $userId, int $lessonId): LessonProgress
    {
        return LessonProgress::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'lesson_id' => $lessonId,
            ],
            [
                'completed_at' => now(),
                'progress_percent' => 100,
            ]
        );
    }

    /**
     * @return array{completed: int, total: int, percent: int}
     */
    public function getCourseProgress(int $userId, Course $course): array
    {
        $lessonIds = $course->lessons()->pluck('id');
        $total = $lessonIds->count();

        $completed = LessonProgress::query()
            ->where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->whereNotNull('completed_at')
            ->count();

        $percent = $total > 0 ? (int) round(100 * $completed / $total) : 0;

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => $percent,
        ];
    }
}
