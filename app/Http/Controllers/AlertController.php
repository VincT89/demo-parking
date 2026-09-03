<?php

namespace App\Http\Controllers;

use App\Services\AlertService;
use App\Models\Parking;

class AlertController extends Controller
{
    public function index()
    {
        $parkings = Parking::active()->get();
        if ($parkings->isEmpty()) {
            abort(404, 'Nessun parcheggio attivo configurato nel sistema.');
        }

        $alerts = (new AlertService())->getAlertsForParkings($parkings);
        return view('alerts', compact('alerts'));
    }

    public function dismiss($id)
    {
        $dismissed = session('dismissed_alerts', []);
        if (!in_array($id, $dismissed)) {
            $dismissed[] = $id;
            session(['dismissed_alerts' => $dismissed]);
            if (auth()->check()) {
                cache()->forget('alert_count_' . auth()->id());
            }
        }
        return back();
    }
}