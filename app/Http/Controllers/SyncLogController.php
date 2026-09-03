<?php

namespace App\Http\Controllers;

use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    public function index(Request $request)
    {
        $query = SyncLog::with(['platform', 'parkingListing.parking']);

        if ($request->has('platform_id') && $request->platform_id != '') {
            $query->where('platform_id', $request->platform_id);
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $logs = $query->latest()->paginate(20)->withQueryString();

        return view('sync-logs.index', compact('logs'));
    }
}
