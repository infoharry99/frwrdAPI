<?php

namespace App\Console\Commands;

use App\Mail\PackageExpiryMail;
use App\Models\EmailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PackageExpiryReminderCommand extends Command
{
    protected $signature = 'cron:package-expiry';
    protected $description = 'Send reminder emails 7, 4, and 1 days before package expiry';

    public function handle()
    {
        $this->info("⏰ Package Expiry Reminder running at " . now()->toIso8601String());

        try {
            $records = DB::table('client_student_appointments')
                ->select(
                    'client_id',
                    'client_email',
                    DB::raw('MAX(last_appointment_date) as last_class_date'),
                    DB::raw('MAX(reminder_7_sent) as reminder_7_sent'),
                    DB::raw('MAX(reminder_4_sent) as reminder_4_sent'),
                    DB::raw('MAX(reminder_1_sent) as reminder_1_sent')
                )
                ->groupBy('client_id', 'client_email')
                ->get();

            $today = now()->startOfDay();

            foreach ($records as $row) {
                if (empty($row->last_class_date) || empty($row->client_email)) continue;

                $lastDate = \Carbon\Carbon::parse($row->last_class_date)->startOfDay();
                $diffDays = (int) $today->diffInDays($lastDate, false);

                // 7 Days Reminder
                if ($diffDays === 7 && empty($row->reminder_7_sent)) {
                    $this->sendExpiryReminder($row->client_email, $row->client_id, 7);
                    DB::table('client_student_appointments')
                        ->where('client_id', $row->client_id)
                        ->update(['reminder_7_sent' => 1]);
                }

                // 4 Days Reminder
                if ($diffDays === 4 && empty($row->reminder_4_sent)) {
                    $this->sendExpiryReminder($row->client_email, $row->client_id, 4);
                    DB::table('client_student_appointments')
                        ->where('client_id', $row->client_id)
                        ->update(['reminder_4_sent' => 1]);
                }

                // 1 Day Reminder
                if ($diffDays === 1 && empty($row->reminder_1_sent)) {
                    $this->sendExpiryReminder($row->client_email, $row->client_id, 1);
                    DB::table('client_student_appointments')
                        ->where('client_id', $row->client_id)
                        ->update(['reminder_1_sent' => 1]);
                }
            }

            $this->info("✅ Package Expiry Reminder completed.");
            return 0;
        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            Log::error("Package expiry cron error: " . $e->getMessage());
            return 1;
        }
    }

    protected function sendExpiryReminder(string $email, $clientId, int $daysLeft): void
    {
        try {
            Mail::to($email)->send(new PackageExpiryMail($daysLeft));

            EmailLog::create([
                'client_id' => $clientId,
                'email' => $email,
                'subject' => "📅 Class Ending Soon – {$daysLeft} Day" . ($daysLeft > 1 ? 's' : '') . " Left",
                'type' => "reminder_{$daysLeft}_days",
                'status' => 'sent',
                'created_at' => now(),
            ]);

            $this->info("📧 Email sent to {$email} ({$daysLeft} days)");
        } catch (\Throwable $e) {
            EmailLog::create([
                'client_id' => $clientId,
                'email' => $email,
                'subject' => "📅 Class Ending Soon – {$daysLeft} Day" . ($daysLeft > 1 ? 's' : '') . " Left",
                'type' => "reminder_{$daysLeft}_days",
                'status' => 'failed',
                'error' => $e->getMessage(),
                'created_at' => now(),
            ]);
            Log::error("Failed to send reminder email to {$email}: " . $e->getMessage());
        }
    }
}
