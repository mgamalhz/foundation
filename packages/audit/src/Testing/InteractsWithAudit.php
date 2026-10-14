<?php

namespace Mgamalhz\Audit\Testing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert;

/**
 * Assertions for activity logged with spatie/laravel-activitylog.
 *
 *     $this->assertActivityLogged($order, 'refunded', by: $admin, properties: ['reason' => 'damaged']);
 *     $this->assertActivityNotLogged($order, 'refunded');
 */
trait InteractsWithAudit
{
    /**
     * @param  string  $event  the activity's `event` (e.g. "refunded")
     * @param  Model|false|null  $by  a Model = that causer, false = no causer (the system), null = don't check
     * @param  array<string, mixed>|null  $properties  subset that must be present in `properties`
     */
    public function assertActivityLogged(?Model $subject, string $event, Model|false|null $by = null, ?array $properties = null): Model
    {
        $query = $this->activityQuery($subject)->where('event', $event);

        if ($by instanceof Model) {
            $query->where('causer_type', $by->getMorphClass())->where('causer_id', $by->getKey());
        } elseif ($by === false) {
            $query->whereNull('causer_id');
        }

        $matches = $query->get()->filter(
            fn (Model $activity) => $properties === null
                || array_intersect_key($activity->properties?->toArray() ?? [], $properties) == $properties
        );

        $logged = $this->activityQuery($subject)->pluck('event')->filter()->unique()->implode(', ') ?: 'none';

        Assert::assertNotEmpty(
            $matches,
            "Expected activity [{$event}] but none matched. Events logged".($subject ? ' for this subject' : '').": {$logged}."
        );

        return $matches->last();
    }

    public function assertActivityNotLogged(?Model $subject, string $event): void
    {
        Assert::assertSame(
            0,
            $this->activityQuery($subject)->where('event', $event)->count(),
            "Expected no activity [{$event}], but it was logged."
        );
    }

    private function activityQuery(?Model $subject): Builder
    {
        /** @var class-string<Model> $model */
        $model = config('activitylog.activity_model');
        $query = $model::query();

        if ($subject !== null) {
            $query->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey());
        }

        return $query;
    }
}
