<?php

namespace App\Console\Commands;

use App\Services\TutorCruncherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncStudentsCommand extends Command
{
    protected $signature = 'sync:students {branchId? : Specific branch ID to sync (1017 or 28866)}';
    protected $description = 'Sync students from TutorCruncher API into branch cache tables';

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
            $tableName = "students_branch_{$branchId}";
            $this->info("🚀 Starting Student sync for Branch {$branchId} into {$tableName}...");

            try {
                $endpoint = '/students/';
                $nextUrl = $endpoint;

                while ($nextUrl) {
                    $response = $this->tutorCruncher->get($nextUrl, [], (string)$branchId, 'Recipients');
                    $students = $response['results'] ?? [];

                    foreach ($students as $student) {
                        DB::table($tableName)->updateOrInsert(
                            ['id' => $student['id']],
                            [
                                'first_name' => $student['first_name'] ?? null,
                                'last_name' => $student['last_name'] ?? null,
                                'email' => $student['email'] ?? null,
                                'mobile' => $student['mobile'] ?? null,
                                'phone' => $student['phone'] ?? null,
                                'street' => $student['street'] ?? null,
                                'state' => $student['state'] ?? null,
                                'town' => $student['town'] ?? null,
                                'country' => $student['country'] ?? null,
                                'postcode' => $student['postcode'] ?? null,
                                'timezone' => $student['timezone'] ?? null,
                                'title' => $student['title'] ?? null,
                                'status' => $student['status'] ?? null,
                                'date_created' => isset($student['date_created']) ? date('Y-m-d H:i:s', strtotime($student['date_created'])) : null,
                                'raw_data' => json_encode($student),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    $nextUrl = !empty($response['next']) ? str_replace('https://app.tutorcruncher.com/api', '', $response['next']) : null;
                }

                $this->info("✅ Completed Student sync for Branch {$branchId}");
            } catch (\Throwable $e) {
                $this->error("❌ Branch {$branchId} sync failed: " . $e->getMessage());
                Log::error("Branch {$branchId} student sync error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
