<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Read-only forensic trail viewer — there are deliberately no edit or delete
 * affordances anywhere on this surface.
 */
class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user:id,name')
            ->when($request->query('event'), fn ($query, $event) => $query->where('event', $event))
            ->when($request->query('model'), function ($query, $model) {
                $query->where('auditable_type', 'like', "%{$model}%");
            })
            ->when($request->query('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->query('from'), fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($query, $to) => $query->whereDate('created_at', '<=', $to))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'events' => ['created', 'updated', 'deleted', 'restored'],
        ]);
    }
}
