<?php

namespace App\Http\Controllers;

use App\Models\DowntimeEvent;
use App\Models\MasterProduction;
use App\Models\ProductionRecord;
use App\Models\ShiftAssignment;
use Illuminate\Http\Request;

class AdminDataController extends Controller
{
    public function resetOperationalData(Request $request)
    {
        $resetMasters = filter_var($request->input('resetMasters', true), FILTER_VALIDATE_BOOL);

        ProductionRecord::query()->delete();
        DowntimeEvent::query()->delete();
        ShiftAssignment::query()->delete();

        if ($resetMasters) {
            MasterProduction::query()->delete();
        }

        return response()->json([
            'message' => 'Data operasional berhasil direset.',
            'data' => [
                'resetMasters' => $resetMasters,
            ],
        ]);
    }
}
