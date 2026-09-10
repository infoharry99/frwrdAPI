<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ClientPackageData;
use App\Models\ClientPackageDataTwo;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentFulfillmentService
{
    protected TutorCruncherService $tutorCruncher;

    public function __construct(TutorCruncherService $tutorCruncher)
    {
        $this->tutorCruncher = $tutorCruncher;
    }

    /**
     * Parse ISO date from key like "contractor-sub-YYYY-MM-DD-HHmm"
     */
    protected function parseIsoFromKey(?string $key): ?string
    {
        if (!$key) return null;
        try {
            $parts = explode('-', $key);
            if (count($parts) < 5) return null;
            $year = $parts[count($parts) - 4];
            $month = $parts[count($parts) - 3];
            $day = $parts[count($parts) - 2];
            $time = $parts[count($parts) - 1];

            if (!str_contains($time, ':') && strlen($time) === 4) {
                $time = substr($time, 0, 2) . ':' . substr($time, 2);
            }

            $dateStr = "{$year}-{$month}-{$day} {$time}:00";
            $dt = new \DateTime($dateStr, new \DateTimeZone('Asia/Dubai'));
            return $dt->format(\DateTime::ATOM);
        } catch (\Throwable $e) {
            Log::error('parseIsoFromKey error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Process payment fulfillment idempotently.
     */
    public function processPaymentSuccess(string $refNo, $rawResponse = null): array
    {
        Log::info("⚡ [PaymentFulfillment] Processing payment success for ref: {$refNo}");

        $payment = Payment::where('ref_no', $refNo)->first();

        if (!$payment) {
            throw new \Exception("Payment record with ref_no {$refNo} not found.");
        }

        // Idempotency check
        if ($payment->status === 'SUCCESS') {
            Log::info("✅ [PaymentFulfillment] Payment {$refNo} is already SUCCESS. Skipping.");
            return ['success' => true, 'alreadyProcessed' => true];
        }

        $payload = is_array($payment->booking_payload)
            ? $payment->booking_payload
            : (json_decode($payment->booking_payload ?? '{}', true) ?: []);

        $clientId = $payment->client_id ?? ($payload['clientid'] ?? ($payload['userData']['clientid'] ?? null));
        $branchId = (string)($payment->branch_id ?? ($payload['branchid'] ?? '1017'));
        $userFullName = trim(($payload['userData']['first_name'] ?? '') . ' ' . ($payload['userData']['last_name'] ?? ''));
        $username = $payload['username'] ?? ($userFullName ?: 'Client');
        $discount = (float)($payload['discount'] ?? 0);
        $lessonData = $payload['lessonData'] ?? [];
        $packageFormData = $payload['packageFormData'] ?? [];
        $bookdata = $payload['bookingdata'] ?? ($payload['bookdata'] ?? []);
        $dashboard = $payload['dashboard'] ?? null;
        $totalLessons = $payload['totallessons'] ?? '0';
        $studentDetails = $payload['userData']['studentdetails'] ?? ($payload['studentdetails'] ?? []);

        // Parse student IDs
        $studentIds = $payment->student_ids ?? [];
        if (!is_array($studentIds)) {
            $studentIds = json_decode((string)$studentIds, true) ?: [];
        }
        if (empty($studentIds) && !empty($studentDetails) && is_array($studentDetails)) {
            $studentIds = array_filter(array_column($studentDetails, 'id'));
        }

        $packageId = $lessonData['packageNames']['id'] ?? null;
        $rescheduleMap = [2 => 0, 3 => 1, 4 => 3];
        $rescheduleCount = $rescheduleMap[$packageId] ?? 0;

        $durationDays = (int)($lessonData['packageNames']['duration_days'] ?? 0);
        $currentDate = new \DateTime();
        $formattedCurrentDate = $currentDate->format('Y-m-d');
        $expiryDate = null;
        if ($durationDays > 0) {
            $expiry = clone $currentDate;
            $expiry->modify("+{$durationDays} days");
            $expiryDate = $expiry->format('Y-m-d');
        }

        // Format sessions list
        $updatedContractorList = [];
        if (is_array($bookdata)) {
            foreach ($bookdata as $b) {
                $startDate = $this->parseIsoFromKey($b['key'] ?? null);
                if ($startDate) {
                    $updatedContractorList[] = [
                        'contractor' => $b['tutorId'] ?? null,
                        'contractorPrice' => $b['tutorPrice'] ?? null,
                        'subject' => $b['subject'] ?? null,
                        'start' => $startDate,
                    ];
                }
            }
        }

        $rawTotal = $lessonData['packageNames']['total'] ?? (string)($payment->amount ?? 0);
        $total = (float) str_replace(',', '', (string)$rawTotal);
        $packageType = strtoupper($lessonData['packageName'] ?? '');
        $divisor = match ($packageType) {
            'SILVER' => 6,
            'GOLD' => 12,
            'PLATINUM' => 24,
            default => 1,
        };

        $discountedTotal = max(0, $total - $discount);
        $dividedTotal = number_format($discountedTotal / $divisor, 2, '.', '');

        // Group sessions by contractor + subject
        $groupedData = [];
        foreach ($updatedContractorList as $session) {
            $key = ($session['contractor'] ?? '') . '_' . ($session['subject'] ?? '');
            $groupedData[$key][] = $session;
        }

        // Create services and appointments on TutorCruncher
        foreach ($groupedData as $sessions) {
            $contractor = $sessions[0]['contractor'] ?? null;
            $subject = $sessions[0]['subject'] ?? null;
            $contractorPrice = $sessions[0]['contractorPrice'] ?? $dividedTotal;
            $quantity = count($sessions);
            $branchType = $packageFormData['branch'] ?? '';

            $rcrs = array_map(fn($id) => ['recipient' => (int)$id], $studentIds);
            $serviceName = ($branchType === 'online')
                ? "{$username}-1-1-{$subject}-{$quantity}/online"
                : "{$username}-1-1-{$subject}-{$quantity}";

            $serviceData = [
                'name' => $serviceName,
                'dft_charge_type' => 'hourly',
                'dft_charge_rate' => $dividedTotal,
                'dft_contractor_rate' => $contractorPrice,
                'conjobs' => [['contractor' => (int)$contractor]],
                'rcrs' => $rcrs,
            ];

            Log::info("📦 [PaymentFulfillment] Creating service for contractor {$contractor}: {$serviceName}");
            $serviceRes = $this->tutorCruncher->post('/services/', $serviceData, $branchId, 'Services');
            $serviceId = $serviceRes['id'] ?? null;

            $studentName = explode(' ', trim($packageFormData['name1'] ?? ($packageFormData['name2'] ?? 'student')))[0];
            $program = $packageFormData['program'] ?? '';
            preg_match('/\d+/', $program, $matches);
            $programCode = !empty($matches) ? "Y{$matches[0]}" : '';

            $appointmentPayload = [];
            foreach ($sessions as $index => $s) {
                $startDt = new \DateTime($s['start']);
                $finishDt = (clone $startDt)->modify('+1 hour');

                $appointmentPayload[] = [
                    'topic' => "{$studentName}-{$programCode}-{$subject}-" . ($index + 1),
                    'start' => $startDt->format(\DateTime::ATOM),
                    'finish' => $finishDt->format(\DateTime::ATOM),
                    'status' => 'planned',
                    'service' => $serviceId,
                    'rcras' => array_map(fn($id) => ['recipient' => (int)$id, 'charge_rate' => $dividedTotal], $studentIds),
                    'cjas' => [['contractor' => (int)$contractor, 'pay_rate' => $dividedTotal]],
                ];
            }

            foreach ($appointmentPayload as $appt) {
                try {
                    $apptRes = $this->tutorCruncher->post('/appointments/', $appt, $branchId, 'Appointment');
                    $apptId = $apptRes['id'] ?? null;

                    // Save local appointment record
                    DB::table('appointment')->insert([
                        'client_id' => $clientId,
                        'branch_id' => $branchId,
                        'pkg_id' => $packageId,
                        'appoinmet_id' => $apptId,
                        'start_date' => $formattedCurrentDate,
                        'expiry_date' => $expiryDate,
                        'start' => $appt['start'],
                        'finish' => $appt['finish'],
                        'topic' => $appt['topic'],
                        'status' => 'planned',
                        'service' => $serviceId,
                        'rcras' => json_encode($appt['rcras']),
                        'cjas' => json_encode($appt['cjas']),
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $apptErr) {
                    Log::error("❌ Error creating appointment on TutorCruncher: " . $apptErr->getMessage());
                }
            }
        }

        // Create Proforma Invoice & Take Payment
        if ($clientId) {
            try {
                $proformaPayload = [
                    'amount' => $total,
                    'client' => (int)$clientId,
                    'raise_behaviour' => 'raise-and-send',
                    'description' => 'Credit Request',
                ];
                $invoiceRes = $this->tutorCruncher->post('/proforma-invoices/', $proformaPayload, $branchId, 'Performainvoices');
                $invoiceId = $invoiceRes['id'] ?? null;

                if ($invoiceId) {
                    $takePaymentPayload = [
                        'amount' => $total,
                        'method' => 'cash',
                        'send_receipt' => 'True',
                    ];
                    $this->tutorCruncher->post("/proforma-invoices/{$invoiceId}/take-payment/", $takePaymentPayload, $branchId, 'Performainvoices');
                }
            } catch (\Throwable $invErr) {
                Log::error("❌ Error creating proforma invoice / taking payment: " . $invErr->getMessage());
            }
        }

        // Delete free package if dashboard === 1
        if ($dashboard == 1 && $clientId) {
            try {
                ClientPackageDataTwo::where('clientid', $clientId)->delete();
            } catch (\Throwable $e) {
                Log::error("❌ Error deleting free package: " . $e->getMessage());
            }
        }

        // Save into client_package_data
        if ($clientId) {
            try {
                ClientPackageData::create([
                    'clientid' => $clientId,
                    'packageForm' => ['lessonData' => $lessonData, 'packageFormData' => $packageFormData],
                    'bookingdata' => ['bookdata' => $bookdata],
                    'purches_status' => (string)$rescheduleCount,
                    'booked_classess' => count($bookdata),
                    'total_classess' => (int)$totalLessons,
                    'remaining_classess' => 8,
                    'completed_classess' => 0,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $pkgErr) {
                Log::error("❌ Error saving client package data: " . $pkgErr->getMessage());
            }
        }

        // Update Payment status to SUCCESS
        $payment->status = 'SUCCESS';
        $payment->raw_response = array_merge($payment->raw_response ?? [], [
            'processed_at' => now()->toIso8601String(),
            'fulfillment_result' => 'SUCCESS',
        ]);
        $payment->save();

        Log::info("🎉 [PaymentFulfillment] Payment {$refNo} completed and marked SUCCESS.");

        return [
            'success' => true,
            'refNo' => $refNo,
            'clientId' => $clientId,
        ];
    }
}
