<?php

namespace App\Console\Commands;

use App\Services\TutorCruncherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncClientsCommand extends Command
{
    protected $signature = 'sync:clients {branchId? : Specific branch ID to sync (1017 or 28866)}';
    protected $description = 'Sync clients from TutorCruncher API into branch cache tables';

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
            $tableName = "clients_branch_{$branchId}";
            $this->info("🚀 Starting Client sync for Branch {$branchId} into {$tableName}...");

            try {
                $endpoint = '/clients/';
                $nextUrl = $endpoint;

                while ($nextUrl) {
                    $response = $this->tutorCruncher->get($nextUrl, [], (string)$branchId, 'ClientCreate');
                    $clients = $response['results'] ?? [];

                    foreach ($clients as $client) {
                        DB::table($tableName)->updateOrInsert(
                            ['id' => $client['id']],
                            [
                                'first_name' => $client['first_name'] ?? null,
                                'last_name' => $client['last_name'] ?? null,
                                'email' => $client['email'] ?? null,
                                'mobile' => $client['mobile'] ?? null,
                                'phone' => $client['phone'] ?? null,
                                'street' => $client['street'] ?? null,
                                'state' => $client['state'] ?? null,
                                'town' => $client['town'] ?? null,
                                'country' => $client['country'] ?? null,
                                'postcode' => $client['postcode'] ?? null,
                                'timezone' => $client['timezone'] ?? null,
                                'title' => $client['title'] ?? null,
                                'status' => $client['status'] ?? null,
                                'date_created' => isset($client['date_created']) ? date('Y-m-d H:i:s', strtotime($client['date_created'])) : null,
                                'invoice_balance' => $client['invoice_balance'] ?? 0,
                                'available_balance' => $client['available_balance'] ?? 0,
                                'paid_recipients' => json_encode($client['paid_recipients'] ?? []),
                                'labels' => json_encode($client['labels'] ?? []),
                                'extra_attrs' => json_encode($client['extra_attrs'] ?? []),
                                'raw_data' => json_encode($client),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    $nextUrl = !empty($response['next']) ? str_replace('https://app.tutorcruncher.com/api', '', $response['next']) : null;
                }

                $this->info("✅ Completed Client sync for Branch {$branchId}");
            } catch (\Throwable $e) {
                $this->error("❌ Branch {$branchId} sync failed: " . $e->getMessage());
                Log::error("Branch {$branchId} client sync error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
