<?php

namespace App\Console\Commands;

use App\Services\TutorCruncherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncAppointmentsCommand extends Command
{
    protected $signature = 'sync:appointments {branchId? : Specific branch ID to sync (1017 or 28866)}';
    protected $description = 'Sync appointments from TutorCruncher API into branch cache tables';

    protected TutorCruncherService $tutorCruncher;

    public function __construct(TutorCruncherService $tutorCruncher)
    {
        parent::__construct();
        $this->tutorCruncher = $tutorCruncher;
    }

    public function handle()
    {
        $branchArg = $this->argument('branchId');
        $branches = $branchArg ? [(int)$branchArg] : [1017, 28866];

        foreach ($branches as $branchId) {
            $tableName = "appointments_branch_{$branchId}";
            $this->info("🚀 Starting Appointment sync for Branch {$branchId} into {$tableName}...");

            try {
                $endpoint = '/appointments/';
                $nextUrl = $endpoint;

                while ($nextUrl) {
                    $response = $this->tutorCruncher->get($nextUrl, [], (string)$branchId, 'Appointment');
                    $appointments = $response['results'] ?? [];

                    foreach ($appointments as $appt) {
                        DB::table($tableName)->updateOrInsert(
                            ['id' => $appt['id']],
                            [
                                'start' => isset($appt['start']) ? date('Y-m-d H:i:s', strtotime($appt['start'])) : null,
                                'finish' => isset($appt['finish']) ? date('Y-m-d H:i:s', strtotime($appt['finish'])) : null,
                                'units' => (float)($appt['units'] ?? 0),
                                'topic' => $appt['topic'] ?? null,
                                'status' => $appt['status'] ?? null,
                                'is_deleted' => $appt['is_deleted'] ?? false,
                                'location' => json_encode($appt['location'] ?? []),
                                'rcras' => json_encode($appt['rcras'] ?? []),
                                'cjas' => json_encode($appt['cjas'] ?? []),
                                'service' => json_encode($appt['service'] ?? []),
                                'raw_data' => json_encode($appt),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    $nextUrl = !empty($response['next']) ? str_replace('https://app.tutorcruncher.com/api', '', $response['next']) : null;
                }

                $this->info("✅ Completed Appointment sync for Branch {$branchId}");
            } catch (\Throwable $e) {
                $this->error("❌ Branch {$branchId} sync failed: " . $e->getMessage());
                Log::error("Branch {$branchId} appointment sync error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
