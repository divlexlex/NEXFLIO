<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes an immutable audit_logs row for every create/update/delete/restore
 * on the model. Hidden attributes (passwords, tokens) are never recorded.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            static::writeAudit($model, 'created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $original = array_intersect_key($model->getOriginal(), $changes);

            static::writeAudit($model, 'updated', $original, $changes);
        });

        static::deleted(function (Model $model) {
            static::writeAudit($model, 'deleted', $model->getAttributes(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                static::writeAudit($model, 'restored', null, $model->getAttributes());
            });
        }
    }

    protected static function writeAudit(Model $model, string $event, ?array $old, ?array $new): void
    {
        $hidden = array_flip($model->getHidden());

        AuditLog::create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $old !== null ? array_diff_key($old, $hidden) : null,
            'new_values' => $new !== null ? array_diff_key($new, $hidden) : null,
        ]);
    }
}
