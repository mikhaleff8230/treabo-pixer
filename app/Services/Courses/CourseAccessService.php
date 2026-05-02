<?php

namespace App\Services\Courses;

use Carbon\Carbon;
use Marvel\Database\Models\Course;
use Marvel\Database\Models\Lesson;
use Marvel\Database\Models\ProductSubscription;
use Marvel\Database\Models\User;

class CourseAccessService
{
    public function getActiveSubscription(?User $user, Course $course): ?ProductSubscription
    {
        if (!$user || !$course->required_product_id) {
            return null;
        }

        return ProductSubscription::query()
            ->where('user_id', $user->id)
            ->where('product_id', $course->required_product_id)
            ->active()
            ->orderByDesc('expires_at')
            ->first();
    }

    public function canAccessCourse(?User $user, Course $course): bool
    {
        if (!$course->required_product_id) {
            return true;
        }

        if (!$user) {
            return false;
        }

        return $this->getActiveSubscription($user, $course) !== null;
    }

    public function lessonAvailableAt(Lesson $lesson, ?ProductSubscription $subscription): Carbon
    {
        if ($subscription && $subscription->starts_at) {
            $start = $subscription->starts_at;
        } else {
            $course = $lesson->relationLoaded('course') ? $lesson->course : $lesson->course()->first();
            $start = $course?->created_at ?? now();
        }

        return $start->copy()->addDays((int) $lesson->drip_days);
    }

    public function canAccessLesson(?User $user, Lesson $lesson): bool
    {
        $course = $lesson->relationLoaded('course') ? $lesson->course : $lesson->course()->first();
        if (!$course) {
            return false;
        }

        if (!$this->canAccessCourse($user, $course)) {
            return false;
        }

        if (!$course->required_product_id) {
            return true;
        }

        $subscription = $this->getActiveSubscription($user, $course);
        if (!$subscription || !$subscription->starts_at) {
            return false;
        }

        return now()->greaterThanOrEqualTo($this->lessonAvailableAt($lesson, $subscription));
    }
}
