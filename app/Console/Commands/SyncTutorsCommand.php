<?php

namespace App\Console\Commands;

use App\Services\TutorCruncherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncTutorsCommand extends Command
{
    protected $signature = 'sync:tutors {branchId? : Specific branch ID to sync (1017 or 28866)}';
    protected $description = 'Sync tutors from TutorCruncher API into local branch cache tables';

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
            $tableName = "tutors_branch_{$branchId}";
            $this->info("🚀 Starting Tutor sync for Branch {$branchId} into {$tableName}...");

            try {
                $endpoint = '/contractors/';
                $nextUrl = $endpoint;

                while ($nextUrl) {
                    $response = $this->tutorCruncher->get($nextUrl, [], (string)$branchId, 'Contractors');
                    $contractors = $response['results'] ?? [];

                    foreach ($contractors as $contractor) {
                        $detail = $this->tutorCruncher->get("/contractors/{$contractor['id']}/", [], (string)$branchId, 'Contractors');
                        if (empty($detail['id'])) continue;

                        $this->saveTutor($tableName, $detail);
                    }

                    $nextUrl = !empty($response['next']) ? str_replace('https://app.tutorcruncher.com/api', '', $response['next']) : null;
                }

                $this->info("✅ Completed Tutor sync for Branch {$branchId}");
            } catch (\Throwable $e) {
                $this->error("❌ Branch {$branchId} sync failed: " . $e->getMessage());
                Log::error("Branch {$branchId} tutor sync error: " . $e->getMessage());
            }
        }

        return 0;
    }

    protected function saveTutor(string $tableName, array $tutor): void
    {
        DB::table($tableName)->updateOrInsert(
            ['id' => $tutor['id']],
            [
                'first_name' => $tutor['first_name'] ?? null,
                'last_name' => $tutor['last_name'] ?? null,
                'email' => $tutor['email'] ?? null,
                'mobile' => $tutor['mobile'] ?? null,
                'phone' => $tutor['phone'] ?? null,
                'street' => $tutor['street'] ?? null,
                'state' => $tutor['state'] ?? null,
                'town' => $tutor['town'] ?? null,
                'country' => $tutor['country'] ?? null,
                'postcode' => $tutor['postcode'] ?? null,
                'timezone' => $tutor['timezone'] ?? null,
                'title' => $tutor['title'] ?? null,
                'latitude' => $tutor['latitude'] ?? null,
                'longitude' => $tutor['longitude'] ?? null,
                'photo' => $tutor['photo'] ?? null,
                'status' => $tutor['status'] ?? null,
                'default_rate' => $tutor['default_rate'] ?? null,
                'review_rating' => $tutor['review_rating'] ?? null,
                'review_duration' => $tutor['review_duration'] ?? null,
                'calendar_colour' => $tutor['calendar_colour'] ?? null,
                'amount_owed' => $tutor['work_done_details']['amount_owed'] ?? 0,
                'amount_paid' => $tutor['work_done_details']['amount_paid'] ?? 0,
                'total_paid_hours' => $tutor['work_done_details']['total_paid_hours'] ?? 0,
                'date_created' => isset($tutor['date_created']) ? date('Y-m-d H:i:s', strtotime($tutor['date_created'])) : null,
                'qualifications' => json_encode($tutor['qualifications'] ?? []),
                'skills' => json_encode($tutor['skills'] ?? []),
                'institutions' => json_encode($tutor['institutions'] ?? []),
                'received_notifications' => json_encode($tutor['received_notifications'] ?? []),
                'labels' => json_encode($tutor['labels'] ?? []),
                'extra_attrs' => json_encode($tutor['extra_attrs'] ?? []),
                'raw_data' => json_encode($tutor),
                'updated_at' => now(),
            ]
        );
    }
}
