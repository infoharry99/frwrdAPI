<?php

namespace App\Console\Commands;

use App\Services\TutorCruncherService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncInvoicesCommand extends Command
{
    protected $signature = 'sync:invoices {branchId? : Specific branch ID to sync (1017 or 28866)}';
    protected $description = 'Sync invoices from TutorCruncher API into branch cache tables';

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
            $tableName = "proforma_invoices_branch_{$branchId}";
            $this->info("🚀 Starting Invoice sync for Branch {$branchId} into {$tableName}...");

            try {
                $endpoint = '/proforma-invoices/';
                $nextUrl = $endpoint;

                while ($nextUrl) {
                    $response = $this->tutorCruncher->get($nextUrl, [], (string)$branchId, 'Performainvoices');
                    $invoices = $response['results'] ?? [];

                    foreach ($invoices as $inv) {
                        DB::table($tableName)->updateOrInsert(
                            ['id' => $inv['id']],
                            [
                                'client' => $inv['client'] ?? null,
                                'amount' => $inv['amount'] ?? 0,
                                'status' => $inv['status'] ?? null,
                                'description' => $inv['description'] ?? null,
                                'raw_data' => json_encode($inv),
                                'updated_at' => now(),
                            ]
                        );
                    }

                    $nextUrl = !empty($response['next']) ? str_replace('https://app.tutorcruncher.com/api', '', $response['next']) : null;
                }

                $this->info("✅ Completed Invoice sync for Branch {$branchId}");
            } catch (\Throwable $e) {
                $this->error("❌ Branch {$branchId} invoice sync failed: " . $e->getMessage());
                Log::error("Branch {$branchId} invoice sync error: " . $e->getMessage());
            }
        }

        return 0;
    }
}
