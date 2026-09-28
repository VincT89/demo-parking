<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        return view('management.activity', ['logs' => ActivityLog::latest('id')->paginate(30)]);
    }
}
