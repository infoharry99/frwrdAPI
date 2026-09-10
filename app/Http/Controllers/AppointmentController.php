<?php

namespace App\Http\Controllers;

use App\Services\TutorCruncherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppointmentController extends Controller
{
    protected TutorCruncherService $tutorCruncher;

    public function __construct(TutorCruncherService $tutorCruncher)
    {
        $this->tutorCruncher = $tutorCruncher;
    }

    /**
     * GET /api/appointment
     */
    public function saveAllAppointments(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $data = $this->tutorCruncher->get('/appointments/', $request->query(), $branchId, 'Appointment');
            $appointmentsList = $data['results'] ?? [];

            foreach ($appointmentsList as $app) {
                $full = $this->tutorCruncher->get("/appointments/{$app['id']}", [], $branchId, 'Appointment');
                if (empty($full['id'])) continue;

                DB::table('appointment')->updateOrInsert(
                    ['id' => $full['id']],
                    [
                        'start' => isset($full['start']) ? date('Y-m-d H:i:s', strtotime($full['start'])) : null,
                        'finish' => isset($full['finish']) ? date('Y-m-d H:i:s', strtotime($full['finish'])) : null,
                        'units' => (float)($full['units'] ?? 0),
                        'topic' => $full['topic'] ?? null,
                        'status' => $full['status'] ?? null,
                        'is_deleted' => $full['is_deleted'] ?? false,
                        'location' => json_encode($full['location'] ?? []),
                        'rcras' => json_encode($full['rcras'] ?? []),
                        'cjas' => json_encode($full['cjas'] ?? []),
                        'service' => json_encode($full['service'] ?? []),
                    ]
                );
            }

            return response()->json(['message' => 'All appointments saved in single table.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/allappointment
     */
    public function GetallAppointments(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');

            if ($branchId && Schema::hasTable("appointments_branch_{$branchId}")) {
                $rows = DB::table("appointments_branch_{$branchId}")->get();
                return response()->json($rows);
            }

            $table = Schema::hasTable('appointment_all') ? 'appointment_all' : 'appointment';
            $query = DB::table($table);
            if ($branchId && Schema::hasColumn($table, 'branch_id')) {
                $query->where('branch_id', (string) $branchId);
            }

            $rows = $query->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch appointments'], 500);
        }
    }
}
