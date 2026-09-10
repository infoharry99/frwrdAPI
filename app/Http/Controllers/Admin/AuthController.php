<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\JwtHelper;
use App\Http\Controllers\AuthController as BaseAuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends BaseAuthController
{
    /**
     * POST /api/admin/login
     */
    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json([
                'success' => false,
                'message' => 'Email and password required',
            ], 400);
        }

        try {
            $admin = null;
            if (Schema::hasTable('admins')) {
                $admin = DB::table('admins')->where('email', $email)->first();
            }

            if ($admin) {
                $isMatch = Hash::check($password, $admin->password) || ($password === $admin->password);
                if (!$isMatch) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid email or password',
                    ], 401);
                }

                $token = JwtHelper::generateToken($admin->id ?? 1, 604800);

                return response()->json([
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => $admin->id ?? 1,
                        'name' => $admin->name ?? 'Admin',
                        'email' => $admin->email,
                        'branch_id' => $admin->branch_id ?? '1017',
                        'role' => 'admin',
                    ],
                ]);
            }

            // Fallback default admin credentials if table empty or user not in admins table
            if (($email === 'admin@gmail.com' || $email === 'admin@myfrwrd.com' || str_contains(strtolower($email), 'admin') || str_contains(strtolower($email), 'superadmin')) &&
                ($password === '123456' || $password === 'admin123')) {
                $token = JwtHelper::generateToken('admin_1', 604800);

                return response()->json([
                    'success' => true,
                    'token' => $token,
                    'user' => [
                        'id' => 1,
                        'name' => 'Admin User',
                        'email' => $email,
                        'branch_id' => '1017',
                        'role' => 'admin',
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password',
            ], 401);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server Error',
            ], 500);
        }
    }

    /**
     * Helper for paginated & searched table queries
     */
    protected function queryBranchTable(string $table, Request $request, string $dataKey = 'data')
    {
        $page = (int)$request->query('page', 1);
        $limit = 100;
        $offset = ($page - 1) * $limit;

        $status = $request->query('status', 'all');
        $search = $request->query('search', '');

        $query = DB::table($table);

        if ($status !== 'all' && Schema::hasColumn($table, 'status')) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search, $table) {
                if (Schema::hasColumn($table, 'first_name')) {
                    $q->where('first_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'last_name')) {
                    $q->orWhere('last_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'email')) {
                    $q->orWhere('email', 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'display_id')) {
                    $q->orWhere('display_id', 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'client_name')) {
                    $q->orWhere('client_name', 'like', "%{$search}%");
                }
                if (Schema::hasColumn($table, 'client_email')) {
                    $q->orWhere('client_email', 'like', "%{$search}%");
                }
            });
        }

        $total = $query->count();
        $rows = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'count' => $total,
            'total' => $total,
            'currentPage' => $page,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int)ceil($total / $limit),
            'data' => $rows,
            $dataKey => $rows,
        ]);
    }

    public function getBranch1017Clients(Request $request)
    {
        return $this->queryBranchTable('clients_branch_1017', $request, 'clients');
    }

    public function getBranch28866Clients(Request $request)
    {
        return $this->queryBranchTable('clients_branch_28866', $request, 'clients');
    }

    public function getBranch1017Tutors(Request $request)
    {
        return $this->queryBranchTable('tutors_branch_1017', $request, 'tutors');
    }

    public function getBranch28866Tutors(Request $request)
    {
        return $this->queryBranchTable('tutors_branch_28866', $request, 'tutors');
    }

    public function getBranch1017Students(Request $request)
    {
        return $this->queryBranchTable('students_branch_1017', $request, 'students');
    }

    public function getBranch28866Students(Request $request)
    {
        return $this->queryBranchTable('students_branch_28866', $request, 'students');
    }

    public function getBranch1017Invoices(Request $request)
    {
        return $this->queryBranchTable('proforma_invoices_branch_1017', $request, 'invoices');
    }

    public function getBranch28866Invoices(Request $request)
    {
        return $this->queryBranchTable('proforma_invoices_branch_28866', $request, 'invoices');
    }

    /**
     * Builder 1: Clients Dashboard
     */
    protected function buildClientsDashboard($clients)
    {
        $totalClients = count($clients);
        $liveClients = 0;
        $dormantClients = 0;
        $currentMonth = (int)date('m');
        $currentYear = (int)date('Y');
        $newClientsThisMonth = 0;
        $totalInvoiceBalance = 0;
        $totalAvailableBalance = 0;
        $countryDistribution = [];
        $timezoneDistribution = [];
        $monthlyClientTrend = [];
        $topClientsList = [];

        foreach ($clients as $c) {
            $status = strtolower($c->status ?? '');
            if ($status === 'live') {
                $liveClients++;
            } elseif ($status === 'dormant') {
                $dormantClients++;
            }

            $created = !empty($c->date_created) ? strtotime($c->date_created) : null;
            if ($created) {
                if ((int)date('m', $created) === $currentMonth && (int)date('Y', $created) === $currentYear) {
                    $newClientsThisMonth++;
                }
                $key = date('Y-m', $created);
                $monthlyClientTrend[$key] = ($monthlyClientTrend[$key] ?? 0) + 1;
            }

            $invBal = (float)($c->invoice_balance ?? 0);
            $availBal = (float)($c->available_balance ?? 0);
            $totalInvoiceBalance += $invBal;
            $totalAvailableBalance += $availBal;

            $country = !empty($c->country) ? $c->country : 'Unknown';
            $countryDistribution[$country] = ($countryDistribution[$country] ?? 0) + 1;

            $timezone = !empty($c->timezone) ? $c->timezone : 'Unknown';
            $timezoneDistribution[$timezone] = ($timezoneDistribution[$timezone] ?? 0) + 1;

            $topClientsList[] = [
                'id' => $c->id ?? null,
                'name' => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                'email' => $c->email ?? '',
                'status' => $c->status ?? '',
                'invoice_balance' => $invBal,
                'available_balance' => $availBal,
            ];
        }

        usort($topClientsList, fn($a, $b) => $b['invoice_balance'] <=> $a['invoice_balance']);
        $topClients = array_slice($topClientsList, 0, 10);

        $averageInvoiceBalance = $totalClients > 0 ? $totalInvoiceBalance / $totalClients : 0;
        $liveClientRate = $totalClients > 0 ? (float)number_format(($liveClients / $totalClients) * 100, 2) : 0;

        return [
            'overview' => [
                'totalClients' => $totalClients,
                'liveClients' => $liveClients,
                'dormantClients' => $dormantClients,
                'newClientsThisMonth' => $newClientsThisMonth,
                'totalInvoiceBalance' => (float)number_format($totalInvoiceBalance, 2, '.', ''),
                'totalAvailableBalance' => (float)number_format($totalAvailableBalance, 2, '.', ''),
                'averageInvoiceBalance' => (float)number_format($averageInvoiceBalance, 2, '.', ''),
                'liveClientRate' => $liveClientRate,
            ],
            'statusDistribution' => [
                'live' => $liveClients,
                'dormant' => $dormantClients,
            ],
            'countryDistribution' => $countryDistribution,
            'timezoneDistribution' => $timezoneDistribution,
            'monthlyClientTrend' => $monthlyClientTrend,
            'topClients' => $topClients,
        ];
    }

    /**
     * Builder 2: Students Dashboard
     */
    protected function buildStudentsDashboard($students)
    {
        $totalStudents = count($students);

        $currentMonth = (int)date('m');
        $currentYear = (int)date('Y');

        $newStudentsThisMonth = 0;
        $studentsWithPhoto = 0;
        $studentsWithPayingClient = 0;
        $academicYearDistribution = [];
        $locationDistribution = [];
        $monthlyStudentTrend = [];

        foreach ($students as $student) {
            $created = !empty($student->date_created) ? strtotime($student->date_created) : null;
            if ($created) {
                if ((int)date('m', $created) === $currentMonth && (int)date('Y', $created) === $currentYear) {
                    $newStudentsThisMonth++;
                }
                $key = date('Y-m', $created);
                $monthlyStudentTrend[$key] = ($monthlyStudentTrend[$key] ?? 0) + 1;
            }

            if (!empty($student->photo)) {
                $studentsWithPhoto++;
            }
            if (!empty($student->paying_client)) {
                $studentsWithPayingClient++;
            }

            $year = !empty($student->academic_year) ? $student->academic_year : 'Unknown';
            $academicYearDistribution[$year] = ($academicYearDistribution[$year] ?? 0) + 1;

            $location = !empty($student->country) ? $student->country : (!empty($student->timezone) ? $student->timezone : 'Unknown');
            $locationDistribution[$location] = ($locationDistribution[$location] ?? 0) + 1;
        }

        $studentsWithoutPhoto = $totalStudents - $studentsWithPhoto;
        $studentsWithoutPayingClient = $totalStudents - $studentsWithPayingClient;

        $photoCoverageRate = $totalStudents > 0 ? (float)number_format(($studentsWithPhoto / $totalStudents) * 100, 2) : 0;
        $payingClientRate = $totalStudents > 0 ? (float)number_format(($studentsWithPayingClient / $totalStudents) * 100, 2) : 0;

        $recentStudents = [];
        $sortedStudents = collect($students)->sortByDesc('date_created')->take(10);
        foreach ($sortedStudents as $s) {
            $recentStudents[] = [
                'id' => $s->id ?? null,
                'name' => trim(($s->first_name ?? '') . ' ' . ($s->last_name ?? '')),
                'academic_year' => $s->academic_year ?? 'N/A',
                'email' => $s->email ?? '',
                'date_created' => $s->date_created ?? null,
            ];
        }

        return [
            'overview' => [
                'totalStudents' => $totalStudents,
                'newStudentsThisMonth' => $newStudentsThisMonth,
                'studentsWithPhoto' => $studentsWithPhoto,
                'studentsWithoutPhoto' => $studentsWithoutPhoto,
                'studentsWithPayingClient' => $studentsWithPayingClient,
                'studentsWithoutPayingClient' => $studentsWithoutPayingClient,
                'photoCoverageRate' => $photoCoverageRate,
                'payingClientRate' => $payingClientRate,
            ],
            'academicYearDistribution' => $academicYearDistribution,
            'locationDistribution' => $locationDistribution,
            'monthlyStudentTrend' => $monthlyStudentTrend,
            'recentStudents' => $recentStudents,
        ];
    }

    /**
     * Builder 3: Tutors Dashboard
     */
    protected function buildTutorDashboard($tutors)
    {
        $totalTutors = count($tutors);

        $approvedTutors = 0;
        $pendingTutors = 0;
        $dormantTutors = 0;
        $rejectedTutors = 0;
        $totalRevenue = 0;
        $totalRating = 0;
        $statusDistribution = [];
        $subjectDistribution = [];
        $qualificationDistribution = [];
        $countryDistribution = [];
        $monthlyTutorTrend = [];
        $topTutorsList = [];
        $topRatedTutorsList = [];

        $currentMonth = (int)date('m');
        $currentYear = (int)date('Y');
        $newTutorsThisMonth = 0;

        foreach ($tutors as $tutor) {
            $status = strtolower($tutor->status ?? 'pending');
            if ($status === 'approved') $approvedTutors++;
            elseif ($status === 'pending') $pendingTutors++;
            elseif ($status === 'dormant') $dormantTutors++;
            elseif ($status === 'rejected') $rejectedTutors++;

            $statusDistribution[$status] = ($statusDistribution[$status] ?? 0) + 1;
            $rev = (float)($tutor->amount_owed ?? 0);
            $totalRevenue += $rev;
            $rat = (float)($tutor->review_rating ?? 0);
            $totalRating += $rat;

            $created = !empty($tutor->date_created) ? strtotime($tutor->date_created) : null;
            if ($created) {
                if ((int)date('m', $created) === $currentMonth && (int)date('Y', $created) === $currentYear) {
                    $newTutorsThisMonth++;
                }
                $key = date('Y-m', $created);
                $monthlyTutorTrend[$key] = ($monthlyTutorTrend[$key] ?? 0) + 1;
            }

            $country = !empty($tutor->country) ? $tutor->country : 'Unknown';
            $countryDistribution[$country] = ($countryDistribution[$country] ?? 0) + 1;

            if (!empty($tutor->skills)) {
                $skills = is_array($tutor->skills) ? $tutor->skills : json_decode($tutor->skills, true);
                if (is_array($skills)) {
                    foreach ($skills as $sk) {
                        $subj = is_array($sk) ? ($sk['subject']['name'] ?? ($sk['subject'] ?? 'Unknown')) : 'Unknown';
                        $subjectDistribution[$subj] = ($subjectDistribution[$subj] ?? 0) + 1;

                        $level = is_array($sk) ? ($sk['qual_level']['name'] ?? ($sk['qual_level'] ?? 'Unknown')) : 'Unknown';
                        $qualificationDistribution[$level] = ($qualificationDistribution[$level] ?? 0) + 1;
                    }
                }
            }

            $tName = trim(($tutor->first_name ?? '') . ' ' . ($tutor->last_name ?? ''));
            $topTutorsList[] = [
                'id' => $tutor->id ?? null,
                'name' => $tName,
                'email' => $tutor->email ?? '',
                'rating' => $rat,
                'revenue' => $rev,
                'status' => $tutor->status ?? '',
            ];
            $topRatedTutorsList[] = [
                'id' => $tutor->id ?? null,
                'name' => $tName,
                'email' => $tutor->email ?? '',
                'rating' => $rat,
                'status' => $tutor->status ?? '',
            ];
        }

        usort($topTutorsList, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        $topTutors = array_slice($topTutorsList, 0, 10);

        usort($topRatedTutorsList, fn($a, $b) => $b['rating'] <=> $a['rating']);
        $topRatedTutors = array_slice($topRatedTutorsList, 0, 10);

        $averageRevenuePerTutor = $totalTutors > 0 ? $totalRevenue / $totalTutors : 0;
        $averageRating = $totalTutors > 0 ? $totalRating / $totalTutors : 0;
        $activeTutorRate = $totalTutors > 0 ? (float)number_format(($approvedTutors / $totalTutors) * 100, 2) : 0;

        return [
            'overview' => [
                'totalTutors' => $totalTutors,
                'approvedTutors' => $approvedTutors,
                'pendingTutors' => $pendingTutors,
                'dormantTutors' => $dormantTutors,
                'rejectedTutors' => $rejectedTutors,
                'newTutorsThisMonth' => $newTutorsThisMonth,
                'totalRevenue' => (float)number_format($totalRevenue, 2, '.', ''),
                'averageRevenuePerTutor' => (float)number_format($averageRevenuePerTutor, 2, '.', ''),
                'averageRating' => (float)number_format($averageRating, 2, '.', ''),
                'activeTutorRate' => $activeTutorRate,
            ],
            'statusDistribution' => [
                'approved' => $approvedTutors,
                'pending' => $pendingTutors,
                'dormant' => $dormantTutors,
                'rejected' => $rejectedTutors,
            ],
            'subjectDistribution' => $subjectDistribution,
            'qualificationDistribution' => $qualificationDistribution,
            'countryDistribution' => $countryDistribution,
            'monthlyTutorTrend' => $monthlyTutorTrend,
            'topTutors' => $topTutors,
            'topRatedTutors' => $topRatedTutors,
        ];
    }

    /**
     * Builder 4: Invoice Dashboard
     */
    protected function buildInvoiceDashboard($invoices)
    {
        $totalInvoices = count($invoices);
        $totalRevenue = 0;
        $paidInvoices = [];
        $unpaidInvoices = [];
        $voidInvoices = [];
        $paidAmount = 0;
        $outstandingBalance = 0;
        $revenueByClient = [];
        $monthlyRevenueTrend = [];
        $highRiskList = [];

        foreach ($invoices as $inv) {
            $amount = (float)($inv->amount ?? 0);
            $stillToPay = (float)($inv->still_to_pay ?? 0);
            $status = strtolower($inv->status ?? '');

            $totalRevenue += $amount;
            $outstandingBalance += $stillToPay;

            if ($status === 'paid') {
                $paidInvoices[] = $inv;
                $paidAmount += $amount;
            } elseif ($status === 'unpaid') {
                $unpaidInvoices[] = $inv;
            } elseif ($status === 'void') {
                $voidInvoices[] = $inv;
            }

            $clientName = !empty($inv->client_name) ? $inv->client_name : 'Unknown';
            $revenueByClient[$clientName] = ($revenueByClient[$clientName] ?? 0) + $amount;

            $sent = !empty($inv->date_sent) ? strtotime($inv->date_sent) : null;
            if ($sent) {
                $key = date('Y-m', $sent);
                $monthlyRevenueTrend[$key] = ($monthlyRevenueTrend[$key] ?? 0) + $amount;
            }

            if ($stillToPay > 1000) {
                $highRiskList[] = $inv;
            }
        }

        usort($highRiskList, fn($a, $b) => (float)($b->still_to_pay ?? 0) <=> (float)($a->still_to_pay ?? 0));
        $highRiskCustomers = array_slice($highRiskList, 0, 10);

        $averageInvoiceValue = $totalInvoices > 0 ? $totalRevenue / $totalInvoices : 0;
        $collectionRate = $totalRevenue > 0 ? (float)number_format(($paidAmount / $totalRevenue) * 100, 2) : 0;

        return [
            'overview' => [
                'totalInvoices' => $totalInvoices,
                'totalRevenue' => (float)number_format($totalRevenue, 2, '.', ''),
                'paidInvoices' => count($paidInvoices),
                'unpaidInvoices' => count($unpaidInvoices),
                'voidInvoices' => count($voidInvoices),
                'outstandingBalance' => (float)number_format($outstandingBalance, 2, '.', ''),
                'averageInvoiceValue' => (float)number_format($averageInvoiceValue, 2, '.', ''),
                'collectionRate' => $collectionRate,
            ],
            'statusDistribution' => [
                'Paid' => count($paidInvoices),
                'Unpaid' => count($unpaidInvoices),
                'Void' => count($voidInvoices),
            ],
            'revenueByClient' => $revenueByClient,
            'monthlyRevenueTrend' => $monthlyRevenueTrend,
            'highRiskCustomers' => $highRiskCustomers,
        ];
    }

    /**
     * Builder 5: Payments Dashboard
     */
    protected function buildPaymentsDashboard($invoices)
    {
        $paidInvoices = [];
        $totalPaymentsReceived = 0;
        $totalDays = 0;
        $paymentsByMonth = [];
        $unpaidCount = 0;

        foreach ($invoices as $inv) {
            $status = strtolower($inv->status ?? '');
            if ($status === 'unpaid') {
                $unpaidCount++;
            }

            if ($status === 'paid' && !empty($inv->date_paid)) {
                $paidInvoices[] = $inv;
                $amount = (float)($inv->amount ?? 0);
                $totalPaymentsReceived += $amount;

                $sent = !empty($inv->date_sent) ? strtotime($inv->date_sent) : null;
                $paid = strtotime($inv->date_paid);
                if ($sent && $paid && $paid >= $sent) {
                    $totalDays += ($paid - $sent) / (60 * 60 * 24);
                }

                $key = date('Y-m', $paid);
                $paymentsByMonth[$key] = ($paymentsByMonth[$key] ?? 0) + $amount;
            }
        }

        $averagePaymentTime = count($paidInvoices) > 0 ? (float)number_format($totalDays / count($paidInvoices), 2) : 0;

        return [
            'overview' => [
                'totalPaymentsReceived' => (float)number_format($totalPaymentsReceived, 2, '.', ''),
                'totalPaidInvoices' => count($paidInvoices),
                'averagePaymentTime' => $averagePaymentTime,
            ],
            'paymentsByMonth' => $paymentsByMonth,
            'refundsIssued' => 0,
            'failedPayments' => 0,
            'alerts' => [
                'unpaidInvoices' => $unpaidCount,
            ],
        ];
    }

    /**
     * Builder 6: Appointments Dashboard
     */
    protected function buildAppointmentsDashboard($appointments)
    {
        $totalAppointments = count($appointments);
        $completed = [];
        $cancelled = [];
        $planned = [];
        $totalUnits = 0;
        $onlineLessons = 0;
        $lessonTopics = [];
        $monthlyLessonsTrend = [];
        $serviceDistribution = [];
        $upcomingList = [];

        $now = time();

        foreach ($appointments as $apt) {
            $status = strtolower($apt->status ?? '');
            if ($status === 'complete') {
                $completed[] = $apt;
            } elseif ($status === 'cancelled') {
                $cancelled[] = $apt;
            } elseif ($status === 'planned') {
                $planned[] = $apt;
            }

            $totalUnits += (float)($apt->units ?? 0);

            $loc = strtolower($apt->location ?? '');
            if (empty($loc) || $loc === 'online') {
                $onlineLessons++;
            }

            $topic = !empty($apt->topic) ? $apt->topic : 'Unknown';
            $lessonTopics[$topic] = ($lessonTopics[$topic] ?? 0) + 1;

            $service = !empty($apt->service_name) ? $apt->service_name : 'Unknown';
            $serviceDistribution[$service] = ($serviceDistribution[$service] ?? 0) + 1;

            $startTime = !empty($apt->start_time) ? strtotime($apt->start_time) : null;
            if ($startTime) {
                $key = date('Y-m', $startTime);
                $monthlyLessonsTrend[$key] = ($monthlyLessonsTrend[$key] ?? 0) + 1;

                if ($startTime >= $now && $status === 'planned') {
                    $tutorName = '-';
                    if (!empty($apt->cjas)) {
                        $tutorsArr = is_array($apt->cjas) ? $apt->cjas : json_decode($apt->cjas, true);
                        if (is_array($tutorsArr)) {
                            $names = array_filter(array_map(fn($t) => $t['name'] ?? null, $tutorsArr));
                            if (!empty($names)) {
                                $tutorName = implode(', ', $names);
                            }
                        }
                    }
                    $upcomingList[] = [
                        'id' => $apt->id ?? null,
                        'tutor' => $tutorName,
                        'topic' => $apt->topic ?? '',
                        'service' => $apt->service_name ?? '',
                        'start_time' => $apt->start_time ?? null,
                        'finish_time' => $apt->finish_time ?? null,
                        'status' => $apt->status ?? '',
                    ];
                }
            }
        }

        usort($upcomingList, fn($a, $b) => strtotime($a['start_time']) <=> strtotime($b['start_time']));
        $latestUpcomingAppointments = array_slice($upcomingList, 0, 5);

        $offlineLessons = $totalAppointments - $onlineLessons;
        $averageLessonDuration = $totalAppointments > 0 ? (float)number_format($totalUnits / $totalAppointments, 2) : 0;
        $completionRate = $totalAppointments > 0 ? (float)number_format((count($completed) / $totalAppointments) * 100, 2) : 0;
        $cancellationRate = $totalAppointments > 0 ? (float)number_format((count($cancelled) / $totalAppointments) * 100, 2) : 0;

        return [
            'overview' => [
                'totalAppointments' => $totalAppointments,
                'completedLessons' => count($completed),
                'plannedLessons' => count($planned),
                'cancelledLessons' => count($cancelled),
                'completionRate' => $completionRate,
                'cancellationRate' => $cancellationRate,
                'averageLessonDuration' => $averageLessonDuration,
                'onlineLessons' => $onlineLessons,
                'offlineLessons' => $offlineLessons,
            ],
            'lessonTopics' => $lessonTopics,
            'monthlyLessonsTrend' => $monthlyLessonsTrend,
            'serviceDistribution' => $serviceDistribution,
            'latestUpcomingAppointments' => $latestUpcomingAppointments,
        ];
    }

    /**
     * Builder 7: Client Single Dashboard
     */
    protected function buildClientDashboard($clientId, $branchId)
    {
        $clientTable = "clients_branch_{$branchId}";
        $studentTable = "students_branch_{$branchId}";
        $tutorTable = "tutors_branch_{$branchId}";
        $appointmentTable = "appointments_branch_{$branchId}";
        $invoiceTable = "proforma_invoices_branch_{$branchId}";

        $client = DB::table($clientTable)->where('id', $clientId)->first();
        if (!$client) {
            throw new \Exception("Client not found");
        }

        $students = DB::table($studentTable)->where('paying_client', 'like', "%\"id\":{$clientId}%")->get();
        
        $aptQuery = DB::table($appointmentTable);
        if (Schema::hasColumn($appointmentTable, 'is_deleted')) {
            $aptQuery->where('is_deleted', 0);
        }
        $appointments = $aptQuery->where('rcras', 'like', "%\"paying_client\":{$clientId}%")->get();

        $invoices = DB::table($invoiceTable)->where('client_id', $clientId)->get();

        $tutorIds = [];
        foreach ($appointments as $a) {
            if (!empty($a->cjas)) {
                $cjasArr = is_array($a->cjas) ? $a->cjas : json_decode($a->cjas, true);
                if (is_array($cjasArr)) {
                    foreach ($cjasArr as $t) {
                        if (!empty($t['contractor']) && !in_array($t['contractor'], $tutorIds)) {
                            $tutorIds[] = $t['contractor'];
                        }
                    }
                }
            }
        }

        $tutors = [];
        if (!empty($tutorIds)) {
            $tutors = DB::table($tutorTable)->whereIn('id', $tutorIds)->get();
        }

        $paidAmount = 0;
        $outstandingAmount = 0;
        foreach ($invoices as $inv) {
            $status = strtolower($inv->status ?? '');
            if ($status === 'paid') {
                $paidAmount += (float)($inv->amount ?? 0);
            }
            $outstandingAmount += (float)($inv->still_to_pay ?? 0);
        }

        $upcomingLessons = [];
        foreach ($appointments as $apt) {
            if (strtolower($apt->status ?? '') === 'planned') {
                $upcomingLessons[] = $apt;
            }
        }
        $upcomingLessons = array_slice($upcomingLessons, 0, 5);

        $sortedInvoices = collect($invoices)->sortByDesc('date_sent')->take(5)->values()->all();

        return [
            'client' => $client,
            'overview' => [
                'students' => count($students),
                'tutors' => count($tutors),
                'lessons' => count($appointments),
                'upcomingLessons' => count($upcomingLessons),
                'invoiceBalance' => array_sum(array_map(fn($x) => (float)($x->still_to_pay ?? 0), iterator_to_array($invoices))),
                'payments' => $paidAmount,
                'invoices' => count($invoices),
            ],
            'paymentOverview' => [
                'paid' => $paidAmount,
                'outstanding' => $outstandingAmount,
            ],
            'students' => $students,
            'tutors' => $tutors,
            'upcomingLessons' => $upcomingLessons,
            'recentInvoices' => $sortedInvoices,
        ];
    }

    // Dashboard Endpoints
    public function getBranch1017StudentsDashboard()
    {
        $students = DB::table('students_branch_1017')->get();
        $dashboard = $this->buildStudentsDashboard($students);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866StudentsDashboard()
    {
        $students = DB::table('students_branch_28866')->get();
        $dashboard = $this->buildStudentsDashboard($students);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017TutorsDashboard()
    {
        $tutors = DB::table('tutors_branch_1017')->get();
        $dashboard = $this->buildTutorDashboard($tutors);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866TutorsDashboard()
    {
        $tutors = DB::table('tutors_branch_28866')->get();
        $dashboard = $this->buildTutorDashboard($tutors);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017ClientsDashboard()
    {
        $clients = DB::table('clients_branch_1017')->get();
        $dashboard = $this->buildClientsDashboard($clients);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866ClientsDashboard()
    {
        $clients = DB::table('clients_branch_28866')->get();
        $dashboard = $this->buildClientsDashboard($clients);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017InvoicesDashboard()
    {
        $invoices = DB::table('proforma_invoices_branch_1017')->get();
        $dashboard = $this->buildInvoiceDashboard($invoices);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866InvoicesDashboard()
    {
        $invoices = DB::table('proforma_invoices_branch_28866')->get();
        $dashboard = $this->buildInvoiceDashboard($invoices);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017PaymentsDashboard()
    {
        $invoices = DB::table('proforma_invoices_branch_1017')->get();
        $dashboard = $this->buildPaymentsDashboard($invoices);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866PaymentsDashboard()
    {
        $invoices = DB::table('proforma_invoices_branch_28866')->get();
        $dashboard = $this->buildPaymentsDashboard($invoices);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017AppointmentsDashboard()
    {
        $appointments = DB::table('appointments_branch_1017')->get();
        $dashboard = $this->buildAppointmentsDashboard($appointments);
        return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
    }

    public function getBranch28866AppointmentsDashboard()
    {
        $appointments = DB::table('appointments_branch_28866')->get();
        $dashboard = $this->buildAppointmentsDashboard($appointments);
        return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
    }

    public function getBranch1017ClientDashboard($clientId)
    {
        try {
            $dashboard = $this->buildClientDashboard($clientId, '1017');
            return response()->json(array_merge(['success' => true, 'branch_id' => 1017], $dashboard));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function getBranch28866ClientDashboard($clientId)
    {
        try {
            $dashboard = $this->buildClientDashboard($clientId, '28866');
            return response()->json(array_merge(['success' => true, 'branch_id' => 28866], $dashboard));
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Student / Client sub-data endpoints
    public function getClientStudents1017($clientId)
    {
        $row = DB::table('clients_branch_1017')->where('id', $clientId)->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $students = json_decode($row->paid_recipients ?? '[]', true) ?? [];

        return response()->json([
            'success' => true,
            'total' => count($students),
            'students' => $students,
            'data' => $students,
        ]);
    }

    public function getClientStudents28866($clientId)
    {
        $row = DB::table('clients_branch_28866')->where('id', $clientId)->first();
        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found',
            ], 404);
        }

        $students = json_decode($row->paid_recipients ?? '[]', true) ?? [];

        return response()->json([
            'success' => true,
            'total' => count($students),
            'students' => $students,
            'data' => $students,
        ]);
    }

    // Dropdowns
    public function getAllClientsDropdown1017()
    {
        $clients = DB::table('clients_branch_1017')
            ->select('id', 'first_name', 'last_name', 'email', 'status')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($clients),
            'clients' => $clients,
            'data' => $clients,
        ]);
    }

    public function getAllClientsDropdown28866()
    {
        $clients = DB::table('clients_branch_28866')
            ->select('id', 'first_name', 'last_name', 'email', 'status')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($clients),
            'clients' => $clients,
            'data' => $clients,
        ]);
    }

    public function getAllTutorsDropdown1017()
    {
        $tutors = DB::table('tutors_branch_1017')
            ->select('id', 'first_name', 'last_name', 'email', 'status')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($tutors),
            'tutors' => $tutors,
            'data' => $tutors,
        ]);
    }

    public function getAllTutorsDropdown28866()
    {
        $tutors = DB::table('tutors_branch_28866')
            ->select('id', 'first_name', 'last_name', 'email', 'status')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($tutors),
            'tutors' => $tutors,
            'data' => $tutors,
        ]);
    }

    public function getAllStudentsDropdown1017()
    {
        $students = DB::table('students_branch_1017')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($students),
            'students' => $students,
            'data' => $students,
        ]);
    }

    public function getAllStudentsDropdown28866()
    {
        $students = DB::table('students_branch_28866')
            ->select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => count($students),
            'students' => $students,
            'data' => $students,
        ]);
    }

    // Appointments list & filter
    public function getBranch1017Appointments(Request $request)
    {
        $query = DB::table('appointments_branch_1017');
        if (Schema::hasColumn('appointments_branch_1017', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }
        $query->orderBy('start_time', 'desc');
        $appointments = $query->get();

        return response()->json([
            'success' => true,
            'branch_id' => 1017,
            'count' => count($appointments),
            'appointments' => $appointments,
            'data' => $appointments,
        ]);
    }

    public function getBranch28866Appointments(Request $request)
    {
        $query = DB::table('appointments_branch_28866');
        if (Schema::hasColumn('appointments_branch_28866', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }
        $query->orderBy('start_time', 'desc');
        $appointments = $query->get();

        return response()->json([
            'success' => true,
            'branch_id' => 28866,
            'count' => count($appointments),
            'appointments' => $appointments,
            'data' => $appointments,
        ]);
    }

    public function filterAppointments1017(Request $request)
    {
        $contractor = $request->query('contractor');
        $recipient = $request->query('recipient');
        $client = $request->query('client');

        $query = DB::table('appointments_branch_1017');
        if (Schema::hasColumn('appointments_branch_1017', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if ($contractor) {
            $query->where('cjas', 'like', '%"contractor":' . $contractor . '%');
        }
        if ($recipient) {
            $query->where('rcras', 'like', '%"recipient":' . $recipient . '%');
        }
        if ($client) {
            $query->where('rcras', 'like', '%"paying_client":' . $client . '%');
        }

        $query->orderBy('start_time', 'desc');
        $appointments = $query->get();

        return response()->json([
            'success' => true,
            'count' => count($appointments),
            'appointments' => $appointments,
            'data' => $appointments,
        ]);
    }

    public function filterAppointments28866(Request $request)
    {
        $contractor = $request->query('contractor');
        $recipient = $request->query('recipient');
        $client = $request->query('client');

        $query = DB::table('appointments_branch_28866');
        if (Schema::hasColumn('appointments_branch_28866', 'is_deleted')) {
            $query->where('is_deleted', 0);
        }

        if ($contractor) {
            $query->where('cjas', 'like', '%"contractor":' . $contractor . '%');
        }
        if ($recipient) {
            $query->where('rcras', 'like', '%"recipient":' . $recipient . '%');
        }
        if ($client) {
            $query->where('rcras', 'like', '%"paying_client":' . $client . '%');
        }

        $query->orderBy('start_time', 'desc');
        $appointments = $query->get();

        return response()->json([
            'success' => true,
            'count' => count($appointments),
            'appointments' => $appointments,
            'data' => $appointments,
        ]);
    }

    public function getAllClients()
    {
        $clients = DB::table('client')->get();
        return response()->json($clients);
    }
}
