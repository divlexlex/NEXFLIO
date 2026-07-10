<?php

namespace App\Observers;

use App\Models\Appointment;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    public function created(Appointment $appointment)
    {
        $this->logAction($appointment, 'created', null, $appointment->toArray());
    }

    public function updated(Appointment $appointment)
    {
        // I-capture ang changes (before vs after)
        $this->logAction($appointment, 'updated', $appointment->getOriginal(), $appointment->getChanges());
    }

    public function deleted(Appointment $appointment)
    {
        $this->logAction($appointment, 'deleted', $appointment->toArray(), null);
    }

    protected function logAction($model, $event, $old, $new)
    {
        AuditLog::create([
            'user_id' => Auth::id() ?? 3, // Default to 1 kung walang logged-in user
            'event' => $event,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->id,
            'old_values' => $old ? json_encode($old) : null,
            'new_values' => $new ? json_encode($new) : null,
        ]);
    }
}