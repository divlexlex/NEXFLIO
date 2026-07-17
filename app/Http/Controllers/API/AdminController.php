<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AdminController extends Controller
{
    public function getLogs()
    {
        $logs = AuditLog::with('user:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($logs);
    }
}
