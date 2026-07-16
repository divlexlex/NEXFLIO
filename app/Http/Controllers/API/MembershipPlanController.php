<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MembershipPlan;

class MembershipPlanController extends Controller
{
    public function index()
    {
        return MembershipPlan::where('status', 'active')->get();
    }
}
