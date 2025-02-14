<?php

namespace App\Http\Controllers;

use App\Jobs\DailyReconciliationJob;
use Illuminate\Http\Request;

class ReconciliationController extends Controller
{
    public function dispatchDailyJob(Request $request)
    {

        $request->validate([
            'super_admin_password' => 'required|string',
        ]);

        if ($request->super_admin_password !== env('SUPER_ADMIN_PASSWORD')) {
            return response()->json(['error' => 'Invalid super admin password.'], 401);
        }

        // Dispatch the job to reconcile transactions
        dispatch(new DailyReconciliationJob());

        return response()->json(['message' => 'Daily reconciliation job dispatched successfully.']);
    }
}
