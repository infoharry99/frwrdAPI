<?php

namespace App\Http\Controllers;

use App\Helpers\JwtHelper;
use App\Mail\ClientWelcomeMail;
use App\Mail\PasswordResetMail;
use App\Models\Client;
use App\Models\Student;
use App\Models\Enquiry;
use App\Models\EmailLog;
use App\Models\LoginLog;
use App\Models\ClientPackageData;
use App\Models\ClientPackageDataTwo;
use App\Services\TutorCruncherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected TutorCruncherService $tutorCruncher;

    public function __construct(TutorCruncherService $tutorCruncher)
    {
        $this->tutorCruncher = $tutorCruncher;
    }

    /**
     * POST /api/login
     */
    public function login(Request $request)
    {
        $email = trim($request->input('email', ''));
        $password = (string)$request->input('password', '');

        if (!$email || !$password) {
            return response()->json(['success' => false, 'message' => 'Email and password required'], 400);
        }

        try {
            $user = Client::where('email', $email)->first();

            if (!$user) {
                // Try case-insensitive search
                $user = Client::where(DB::raw('LOWER(email)'), strtolower($email))->first();
            }

            if (!$user) {
                // Fallback to direct DB query if Eloquent scope/hidden rules differ
                $dbUser = DB::table('client')->where('email', $email)->orWhere(DB::raw('LOWER(email)'), strtolower($email))->first();
                if ($dbUser) {
                    $user = (object)[
                        'clientid' => $dbUser->clientid,
                        'firstname' => $dbUser->firstname ?? '',
                        'lastname' => $dbUser->lastname ?? '',
                        'email' => $dbUser->email,
                        'password' => $dbUser->password ?? '',
                        'studentdetails' => $dbUser->studentdetails ?? null,
                        'status' => $dbUser->status ?? '',
                        'numberofstudent' => $dbUser->numberofstudent ?? 0,
                        'branch_id' => $dbUser->branch_id ?? '',
                        'phone_number' => $dbUser->phone_number ?? '',
                        'how_you_came_to_know' => $dbUser->how_you_came_to_know ?? '',
                        'students' => $dbUser->students ?? null,
                    ];
                }
            }

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            // Check password compatibility (bcrypt, plain text, md5, sha1)
            $userPassword = $user->password ?? '';
            $validPassword = Hash::check($password, $userPassword)
                || ($password === $userPassword)
                || (md5($password) === $userPassword)
                || (sha1($password) === $userPassword);

            if (!$validPassword) {
                return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            // Generate JWT token
            $token = JwtHelper::generateToken((int)$user->clientid, 3600);

            // Log login into login_logs (safely ignorable if schema mismatch)
            try {
                if (Schema::hasTable('login_logs')) {
                    DB::table('login_logs')->insert([
                        'clientid' => $user->clientid,
                        'email' => $user->email,
                    ]);
                }
            } catch (\Throwable $logEx) {
                Log::warning('Login log insert skipped: ' . $logEx->getMessage());
            }

            // Parse studentdetails safely
            $studentdetails = $user->studentdetails ?? [];
            if (is_string($studentdetails)) {
                $parsed = json_decode($studentdetails, true);
                $studentdetails = is_array($parsed) ? $parsed : [];
            } elseif (!is_array($studentdetails)) {
                $studentdetails = [];
            }

            $responseUser = [
                'clientid' => $user->clientid,
                'firstname' => $user->firstname ?? '',
                'lastname' => $user->lastname ?? '',
                'email' => $user->email,
                'studentdetails' => $studentdetails,
                'status' => $user->status ?? '',
                'numberofstudent' => $user->numberofstudent ?? 0,
                'branch_id' => $user->branch_id ?? '',
                'phone_number' => $user->phone_number ?? '',
                'how_you_came_to_know' => $user->how_you_came_to_know ?? '',
                'students' => $user->students ?? null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $responseUser,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/client/profile/{clientid}
     */
    public function getClientProfile($clientid)
    {
        try {
            if (!$clientid) {
                return response()->json(['success' => false, 'message' => 'clientid is required'], 400);
            }

            $user = Client::where('clientid', $clientid)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $parsedStudents = is_array($user->studentdetails)
                ? $user->studentdetails
                : (json_decode($user->studentdetails ?? '[]', true) ?: []);

            $responseUser = [
                'clientid' => $user->clientid,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'status' => $user->status,
                'branch_id' => $user->branch_id,
                'numberofstudent' => $user->numberofstudent,
                'how_you_came_to_know' => $user->how_you_came_to_know,
                'students' => $user->students,
                'studentdetails' => $parsedStudents,
                'is_first_login' => $user->is_first_login,
            ];

            return response()->json([
                'success' => true,
                'user' => $responseUser,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * POST /api/client/mark-first-login
     */
    public function markFirstLoginCompleted(Request $request)
    {
        $clientId = $request->input('clientid');

        if (!$clientId) {
            return response()->json(['success' => false, 'message' => 'clientid is required'], 400);
        }

        try {
            $user = Client::where('clientid', $clientId)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            if ((int)$user->is_first_login === 1) {
                return response()->json([
                    'success' => true,
                    'message' => 'First login already completed',
                ], 200);
            }

            Client::where('clientid', $clientId)->update(['is_first_login' => 1]);

            return response()->json([
                'success' => true,
                'message' => 'First login status updated successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * DELETE /api/delete-student/{clientid}/{student_id}
     */
    public function deleteStudent(Request $request, $clientid, $student_id)
    {
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');

        if (!$student_id || !$clientid || !$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'student_id, clientid & branch_id required',
            ], 400);
        }

        try {
            $client = Client::where('clientid', $clientid)->first();
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $students = is_array($client->studentdetails)
                ? $client->studentdetails
                : (json_decode($client->studentdetails ?? '[]', true) ?: []);

            $studentExists = false;
            foreach ($students as $s) {
                if (is_array($s) && (string)($s['id'] ?? '') === (string)$student_id) {
                    $studentExists = true;
                    break;
                }
            }

            if (!$studentExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student does not belong to this client',
                ], 403);
            }

            try {
                $this->tutorCruncher->delete("/recipients/{$student_id}/", [], (string)$branchId, 'Recipients');
            } catch (\Throwable $tcErr) {
                // proceed even if TutorCruncher call fails or is mocked
            }

            $filteredStudents = array_values(array_filter($students, function ($s) use ($student_id) {
                return is_array($s) && (string)($s['id'] ?? '') !== (string)$student_id;
            }));

            $client->studentdetails = $filteredStudents;
            $client->save();

            Student::where('studentid', $student_id)->delete();

            return response()->json(['success' => true, 'message' => 'Student deleted successfully'], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/forget_password
     */
    public function forgetpassword(Request $request)
    {
        $email = $request->input('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email is required'], 400);
        }

        try {
            $client = Client::where('email', $email)->first();

            if (!$client) {
                return response()->json(['success' => false, 'message' => 'User not found with this email'], 404);
            }

            $newPassword = Str::random(8);
            $client->password = Hash::make($newPassword);
            $client->save();

            // Send password reset email
            try {
                Mail::to($email)->send(new PasswordResetMail($newPassword));
                EmailLog::create([
                    'client_id' => $client->clientid,
                    'email' => $email,
                    'subject' => 'FRWRD Tutors - Password Reset',
                    'type' => 'password_reset',
                    'status' => 'sent',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $mErr) {
                EmailLog::create([
                    'client_id' => $client->clientid,
                    'email' => $email,
                    'subject' => 'FRWRD Tutors - Password Reset',
                    'type' => 'password_reset',
                    'status' => 'failed',
                    'error' => $mErr->getMessage(),
                    'created_at' => now(),
                ]);
            }

            return response()->json(['success' => true, 'message' => 'New password sent to your email']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * POST /api/changepassword/{clientid}
     */
    public function changepassword(Request $request, $clientid)
    {
        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('new_password');

        if (!$currentPassword || !$newPassword) {
            return response()->json(['success' => false, 'message' => 'Current and new password required'], 400);
        }

        try {
            $client = Client::where('clientid', $clientid)->first();

            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            if (!Hash::check($currentPassword, $client->password)) {
                return response()->json(['success' => false, 'message' => 'Incorrect current password'], 400);
            }

            $client->password = Hash::make($newPassword);
            $client->save();

            return response()->json(['success' => true, 'message' => 'Password updated successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * POST /api/oldnewpassword
     */
    public function oldnewpassword(Request $request)
    {
        $clientId = $request->input('clientid');
        $oldPassword = $request->input('oldpassword');
        $newPassword = $request->input('newpassword');

        if (!$clientId || !$oldPassword || !$newPassword) {
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        return $this->changepassword(new Request([
            'current_password' => $oldPassword,
            'new_password' => $newPassword,
        ]), $clientId);
    }

    /**
     * POST /api/clients (Client Signup)
     */
    public function clientAPIPOST(Request $request)
    {
        $user = $request->input('user', []);
        $students = $request->input('students', []);

        if (
            empty($user['first_name']) ||
            empty($user['last_name']) ||
            empty($user['email']) ||
            empty($user['phone_number']) ||
            empty($user['numberofstudent']) ||
            empty($user['branch_id']) ||
            empty($user['how_you_came_to_know'])
        ) {
            return response()->json(['error' => 'Client fields are required.'], 400);
        }

        try {
            // Check if email already exists
            $emailExists = Client::where('email', $user['email'])
                ->where('branch_id', $user['branch_id'])
                ->first();

            if ($emailExists) {
                return response()->json([
                    'message' => 'Email already exists. Please Contact Admin. Please use a different email.',
                    'client' => null,
                ], 400);
            }

            // Create client on TutorCruncher
            $clientPayload = [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'numberofstudent' => $user['numberofstudent'],
                'branch_id' => $user['branch_id'],
                'phone_number' => $user['phone_number'],
                'how_you_came_to_know' => $user['how_you_came_to_know'],
            ];

            $tcRes = $this->tutorCruncher->post('/clients/', $clientPayload, (string)$user['branch_id'], 'ClientCreate');

            if (empty($tcRes['id'])) {
                return response()->json(['error' => 'Failed to create client on TutorCruncher', 'details' => $tcRes], 500);
            }

            $generatedPassword = Str::random(8);
            $hashedPassword = Hash::make($generatedPassword);
            $createdStudents = $user['students'] ?? [];

            // Save to client table
            $client = Client::create([
                'clientid' => $tcRes['id'],
                'firstname' => $tcRes['first_name'] ?? $user['first_name'],
                'lastname' => $tcRes['last_name'] ?? $user['last_name'],
                'email' => $tcRes['email'] ?? $user['email'],
                'phone_number' => $clientPayload['phone_number'],
                'numberofstudent' => $clientPayload['numberofstudent'],
                'studentdetails' => $createdStudents,
                'password' => $hashedPassword,
                'status' => 'new_user',
                'branch_id' => $clientPayload['branch_id'],
                'how_you_came_to_know' => $clientPayload['how_you_came_to_know'],
                'students' => $students,
                'is_first_login' => 0,
            ]);

            // Send welcome email with credentials
            try {
                $fullName = "{$client->firstname} {$client->lastname}";
                Mail::to($client->email)->send(new ClientWelcomeMail($fullName, $client->email, $generatedPassword));
                EmailLog::create([
                    'client_id' => $client->clientid,
                    'email' => $client->email,
                    'subject' => 'Welcome to FRWRD Tutors - Your Login Details Inside',
                    'type' => 'welcome',
                    'status' => 'sent',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $mailErr) {
                Log::error('Welcome email error: ' . $mailErr->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Client registered successfully',
                'client' => $tcRes,
                'data' => $client,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Client signup error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/students
     */
    public function studentCreate(Request $request)
    {
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');

        if (!$branchId) {
            return response()->json(['error' => 'branch_id is required in query'], 400);
        }

        $students = $request->input('students') ?? $request->all();
        if (!is_array($students) || (isset($students['first_name']) && !isset($students[0]))) {
            $students = [$students];
        }

        if (empty($students)) {
            return response()->json(['error' => 'Request must include at least one student.'], 400);
        }

        $results = [];

        foreach ($students as $student) {
            $firstName = $student['first_name'] ?? null;
            $lastName = $student['last_name'] ?? null;
            $payingClient = $student['paying_client'] ?? ($student['client_id'] ?? null);

            if (!$firstName || !$lastName || !$payingClient) {
                $results[] = [
                    'success' => false,
                    'student' => $student,
                    'error' => 'Missing required fields: first_name, last_name, or paying_client.',
                ];
                continue;
            }

            try {
                $client = Client::where('clientid', $payingClient)->first();
                if (!$client) {
                    $results[] = [
                        'success' => false,
                        'student' => $student,
                        'error' => "Client with ID {$payingClient} not found.",
                    ];
                    continue;
                }

                $tcRes = $this->tutorCruncher->post('/recipients/', [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'paying_client' => (int)$payingClient,
                ], (string)$branchId, 'Recipients');

                $studentdetails = is_array($client->studentdetails)
                    ? $client->studentdetails
                    : (json_decode($client->studentdetails ?? '[]', true) ?: []);

                $studentdetails[] = $tcRes;

                $client->studentdetails = $studentdetails;
                $client->save();

                if (!empty($tcRes['id'])) {
                    Student::updateOrInsert(
                        ['studentid' => $tcRes['id']],
                        [
                            'studentfirstname' => $tcRes['first_name'] ?? $firstName,
                            'studentlastname' => $tcRes['last_name'] ?? $lastName,
                            'email' => $tcRes['email'] ?? ($student['email'] ?? ''),
                            'clientid' => $payingClient,
                        ]
                    );
                }

                $results[] = ['success' => true, 'student' => $student, 'response' => $tcRes];
            } catch (\Throwable $e) {
                $results[] = [
                    'success' => false,
                    'student' => $student,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'branch_id' => $branchId,
            'message' => 'Student creation attempted for all records.',
            'results' => $results,
        ]);
    }

    /**
     * POST /api/enquiry
     */
    public function enquiryAPIPOST(Request $request)
    {
        try {
            $enquiry = Enquiry::create($request->all());
            return response()->json(['success' => true, 'data' => $enquiry], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/clientemailsend
     */
    public function clientemailsend(Request $request)
    {
        $email = $request->input('email') ?? $request->input('to');
        $type = $request->input('type', 'support');

        if (!$email) {
            return response()->json(['error' => 'Email is required.'], 400);
        }

        try {
            $client = Client::where('email', $email)->first();
            if (!$client) {
                return response()->json(['error' => 'No client found with this email.'], 404);
            }

            $firstname = $client->firstname ?? 'Client';
            $clientid = $client->clientid ?? null;

            if ($type === 'terms_accepted') {
                $clientSubject = 'Terms & Conditions Accepted – FRWRD Tutors';
                $clientHTML = "
                <div style='background-color:#f4f4ff;padding:24px;'>
                  <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:8px;padding:32px 24px;font-family:Arial, sans-serif;color:#333;'>
                    <h2 style='color:#49479D;'>Hello {$firstname},</h2>
                    <p>Thank you for accepting the <strong>FRWRD Tutors Terms & Conditions</strong>.</p>
                    <p>Your acceptance has been successfully recorded in our system.</p>
                    <br>
                    <p style='font-size:14px; color:#777;'>Warm regards,<br><strong>FRWRD Tutors Support Team</strong></p>
                  </div>
                </div>";
            } else {
                $clientSubject = 'FRWRD Tutors Support';
                $clientHTML = "
                <div style='background-color:#f4f4ff;padding:24px;'>
                  <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:8px;padding:32px 24px;font-family:Arial, sans-serif;color:#333;'>
                    <h2 style='color:#49479D;'>Hello {$firstname},</h2>
                    <p>Thank you for contacting <strong>FRWRD Tutors</strong>.</p>
                    <p>We have successfully received your request and our support team is currently reviewing it.</p>
                    <br>
                    <p style='font-size:14px; color:#777;'>Warm regards,<br><strong>FRWRD Tutors Support Team</strong></p>
                  </div>
                </div>";
            }

            try {
                Mail::html($clientHTML, function ($msg) use ($email, $clientSubject) {
                    $msg->to($email)->subject($clientSubject);
                });
            } catch (\Throwable $mailErr) {
                // Ignore email sending error if SMTP credentials are missing
            }

            try {
                EmailLog::create([
                    'client_id' => $clientid,
                    'email' => $email,
                    'subject' => $clientSubject,
                    'type' => $type,
                    'status' => 'sent',
                    'created_at' => now(),
                ]);
            } catch (\Throwable $logErr) {
                // Ignore log error
            }

            return response()->json(['success' => true, 'message' => 'Email sent']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/clientsdata/{clientid}
     */
    public function getClientById($clientid)
    {
        if (!$clientid) {
            return response()->json(['error' => 'clientid is required in the URL.'], 400);
        }

        try {
            $client = Client::where('clientid', $clientid)->first();
            if (!$client) {
                return response()->json(['error' => 'Client not found.'], 404);
            }

            $studentdetails = [];
            if (!empty($client->studentdetails)) {
                if (is_array($client->studentdetails)) {
                    $studentdetails = $client->studentdetails;
                } else {
                    $parsed = json_decode($client->studentdetails, true);
                    if ($parsed !== null) {
                        $studentdetails = is_array($parsed) && isset($parsed[0]) ? $parsed : [$parsed];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'status' => $client->status,
                'numberofstudent' => $client->numberofstudent,
                'client' => [
                    'clientid' => $client->clientid,
                    'status' => $client->status,
                    'firstname' => $client->firstname,
                    'lastname' => $client->lastname,
                    'email' => $client->email,
                    'numberofstudent' => $client->numberofstudent,
                    'studentdetails' => $studentdetails,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal server error.'], 500);
        }
    }

    /**
     * GET /api/contractorsalldata
     */
    public function GetallContractors(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');

            $query = DB::table('tutors');
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }

            $rows = $query->orderByDesc('created_at')->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Failed to fetch contractors'], 500);
        }
    }

    /**
     * GET /api/contractorsbyid/{id}
     */
    public function GetByIdContractors(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');

            $query = DB::table('tutors')->where('id', $id);
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }

            $row = $query->first();
            if (!$row) {
                return response()->json(['message' => 'Not found'], 404);
            }
            return response()->json($row);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/contractorsavailability/{id}
     */
    public function contractor_availabilityAPIGET(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->get("/contractor_availability/{$id}/", [], $branchId, 'Contractors');
            return response()->json([
                'success' => true,
                'message' => 'Contractor availability fetched successfully.',
                'data' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * GET /api/availabilityalldata
     */
    public function Getallavailability(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $query = DB::table('availabilityslot');
            if ($branchId && Schema::hasColumn('availabilityslot', 'branch_id')) {
                $query->where('branch_id', (string) $branchId);
            }
            $rows = $query->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/filter_tutor
     */
    public function FilterTutor(Request $request)
    {
        $subject = $request->query('subject') ?? $request->input('subject');
        $date = $request->query('date') ?? $request->input('date');
        $time = $request->query('time') ?? $request->input('time');
        $quallevel = $request->query('quallevel') ?? $request->input('quallevel');
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');

        if (!$subject || !$date || !$time) {
            return response()->json([
                'success' => false,
                'message' => 'Subject, date, and time are required.',
            ], 400);
        }

        if (!$branchId) {
            return response()->json([
                'success' => false,
                'message' => 'branch_id is required.',
            ], 400);
        }

        try {
            $rows = DB::table('tutors')
                ->where(function($q) use ($branchId) {
                    $q->where('branch_id', (string) $branchId)
                      ->orWhere('branch_id', (int) $branchId);
                })
                ->get();

            $requestedStr = trim("{$date} {$time}");
            $requestedTs = strtotime($requestedStr);

            $matchedTutors = [];

            foreach ($rows as $tutor) {
                $skills = [];
                $availability = [];

                if (!empty($tutor->skills)) {
                    $skills = is_array($tutor->skills) ? $tutor->skills : json_decode($tutor->skills, true);
                }
                if (!empty($tutor->availability)) {
                    $availability = is_array($tutor->availability) ? $tutor->availability : json_decode($tutor->availability, true);
                }

                if (!is_array($skills) || !is_array($availability)) {
                    continue;
                }

                $hasValidSkill = false;
                foreach ($skills as $skill) {
                    $skillSubject = $skill['subject'] ?? '';
                    $subjectMatch = strtolower(trim($skillSubject)) === strtolower(trim($subject));

                    $yearMatch = true;
                    if ($quallevel && !empty($skill['quallevel'])) {
                        $yearMatch = strtolower(trim($skill['quallevel'])) === strtolower(trim($quallevel));
                    }

                    if ($subjectMatch && $yearMatch) {
                        $hasValidSkill = true;
                        break;
                    }
                }

                if (!$hasValidSkill) {
                    continue;
                }

                $isAvailable = false;
                foreach ($availability as $slot) {
                    if (empty($slot['start']) || empty($slot['finish'])) {
                        continue;
                    }

                    $startTs = strtotime(substr($slot['start'], 0, 16));
                    $finishTs = strtotime(substr($slot['finish'], 0, 16));

                    if ($requestedTs !== false && $startTs !== false && $finishTs !== false) {
                        if ($requestedTs >= $startTs && $requestedTs < $finishTs) {
                            $isAvailable = true;
                            break;
                        }
                    }
                }

                if ($isAvailable) {
                    $matchedTutors[] = $tutor;
                }
            }

            return response()->json([
                'success' => true,
                'data' => array_values($matchedTutors),
            ]);
        } catch (\Throwable $err) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * GET /api/contractors
     */
    public function saveAllContractors(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');

            if (!$branchId) {
                return response()->json(['error' => 'branch_id is required in params'], 400);
            }

            $branch = DB::table('branches')->where('branch_id', $branchId)->first();
            if (!$branch) {
                return response()->json(['error' => 'Branch not found'], 404);
            }

            $apiKeyRow = DB::table('branch_api_key')
                ->where('branch_id', $branchId)
                ->where('action', 'Contractors')
                ->first();

            if (!$apiKeyRow) {
                return response()->json(['error' => 'No Contractors API key found for this branch'], 404);
            }

            $apiKey = $apiKeyRow->api_key;
            $contractorList = DB::table('tutors')->where('branch_id', $branchId)->get();

            $startDate = date('Y-m-d', strtotime('-3 months'));
            $endDate = date('Y-m-d', strtotime('+6 months'));

            foreach ($contractorList as $contractor) {
                usleep(200000); // 200ms delay to prevent TutorCruncher 429 rate limit
                $contractorId = $contractor->id;

                try {
                    $response = Http::withHeaders([
                        'Authorization' => "Token {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])->get("https://app.tutorcruncher.com/api/contractors/{$contractorId}");

                    if (!$response->successful()) {
                        continue;
                    }

                    $fullContractor = $response->json();

                    $availRes = Http::withHeaders([
                        'Authorization' => "Token {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])->get("https://app.tutorcruncher.com/api/contractor_availability/{$contractorId}/", [
                        'start' => $startDate,
                        'finish' => $endDate,
                    ]);

                    $availabilityList = $availRes->successful() ? $availRes->json() : [];
                    $availableOnly = [];

                    if (is_array($availabilityList)) {
                        foreach ($availabilityList as $item) {
                            $item['start'] = date('c', strtotime($item['start'] . ' +4 hours'));
                            $item['finish'] = date('c', strtotime($item['finish'] . ' +4 hours'));
                            $availableOnly[] = $item;
                        }
                    }

                    $qualifications = !empty($fullContractor['qualifications']) ? implode(', ', $fullContractor['qualifications']) : null;

                    $subjects = [];
                    $qualLevels = [];
                    $skillsList = [];

                    if (!empty($fullContractor['skills']) && is_array($fullContractor['skills'])) {
                        foreach ($fullContractor['skills'] as $skill) {
                            if (!empty($skill['subject'])) {
                                $subjects[] = $skill['subject'];
                            }
                            if (!empty($skill['qual_level']['name'])) {
                                $qualLevels[] = $skill['qual_level']['name'];
                            }
                            $skillsList[] = [
                                'subject' => $skill['subject']['name'] ?? ($skill['subject'] ?? null),
                                'quallevel' => $skill['qual_level']['name'] ?? null,
                            ];
                        }
                    }

                    $quallevelData = !empty($qualLevels) ? implode(', ', array_unique($qualLevels)) : null;
                    $subjectData = json_encode($subjects);
                    $skillsData = json_encode($skillsList);
                    $availabilityData = json_encode($availableOnly);

                    DB::statement("
                        INSERT INTO tutors (
                            id, first_name, last_name, photo,
                            qualifications, skills, subject, quallevel, town, country, availability
                        )
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            first_name = VALUES(first_name),
                            last_name = VALUES(last_name),
                            photo = VALUES(photo),
                            qualifications = VALUES(qualifications),
                            skills = VALUES(skills),
                            subject = VALUES(subject),
                            quallevel = VALUES(quallevel),
                            town = VALUES(town),
                            country = VALUES(country),
                            availability = VALUES(availability)
                    ", [
                        $fullContractor['id'],
                        $fullContractor['first_name'] ?? '',
                        $fullContractor['last_name'] ?? '',
                        $fullContractor['photo'] ?? null,
                        $qualifications,
                        $skillsData,
                        $subjectData,
                        $quallevelData,
                        $fullContractor['town'] ?? null,
                        $fullContractor['country'] ?? null,
                        $availabilityData,
                    ]);

                } catch (\Throwable $err) {
                    // Ignore individual contractor error
                }
            }

            return response()->json(['message' => 'All contractors saved successfully.']);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/clientDataget
     */
    public function ClientsetDatabae(Request $request)
    {
        if ($request->isMethod('get')) {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $query = Client::query();
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }
            return response()->json($query->get());
        }

        try {
            $incoming = $request->json()->all();
            if (!is_array($incoming) || empty($incoming)) {
                $incoming = [$request->all()];
            }
            if (isset($incoming['topic']) && !isset($incoming[0])) {
                $incoming = [$incoming];
            }

            if (empty($incoming)) {
                return response()->json(['error' => 'Empty request body.'], 400);
            }

            $branchId = $request->query('branch_id') ?? $request->input('branch_id') ?? ($incoming[0]['branch_id'] ?? '1017');
            $results = [];
            $insertedPkgs = [];

            foreach ($incoming as $appointment) {
                $tcResponse = $this->tutorCruncher->post('/appointments/', $appointment, (string)$branchId, 'Appointment');
                $tcId = $tcResponse['id'] ?? null;

                $dbInsertId = null;
                if (Schema::hasTable('alldatasend')) {
                    $dbInsertId = DB::table('alldatasend')->insertGetId([
                        'client_id' => $appointment['client_id'] ?? null,
                        'branch_id' => $appointment['branch_id'] ?? $branchId,
                        'pkg_id' => $appointment['pkg_id'] ?? null,
                        'appoinmet_id' => $tcId,
                        'start' => $appointment['start'] ?? null,
                        'finish' => $appointment['finish'] ?? null,
                        'topic' => $appointment['topic'] ?? null,
                        'status' => $appointment['status'] ?? null,
                        'service' => is_array($appointment['service'] ?? null) ? json_encode($appointment['service']) : ($appointment['service'] ?? null),
                        'rcras' => json_encode($appointment['rcras'] ?? []),
                        'cjas' => json_encode($appointment['cjas'] ?? []),
                        'pricestatus' => $appointment['pricestatus'] ?? 0,
                        'freeassismentemailsend' => $appointment['freeassismentemailsend'] ?? 0,
                    ]);
                }

                $pkgId = $appointment['pkg_id'] ?? null;
                if ($pkgId && !in_array($pkgId, $insertedPkgs) && Schema::hasTable('package_expire')) {
                    DB::table('package_expire')->insert([
                        'student_id' => $appointment['student_id'] ?? null,
                        'client_id' => $appointment['client_id'] ?? null,
                        'pkg_id' => $pkgId,
                        'start_date' => $appointment['start_date'] ?? null,
                        'expiry_date' => $appointment['expiry_date'] ?? null,
                    ]);
                    $insertedPkgs[] = $pkgId;
                }

                // Send Free Assessment Email if freeassismentemailsend == 1
                if (($appointment['freeassismentemailsend'] ?? 0) == 1 && !empty($appointment['client_id'])) {
                    $alreadySent = Schema::hasTable('alldatasend') && DB::table('alldatasend')
                        ->where('client_id', $appointment['client_id'])
                        ->where('freeassismentemailsend', 1)
                        ->where('id', '!=', $dbInsertId)
                        ->exists();

                    if (!$alreadySent) {
                        $client = Client::where('clientid', $appointment['client_id'])->first();
                        if ($client) {
                            $adminEmail = env('MAIL_ADMIN', 'tarunbirla2018@gmail.com');
                            $subject = "📝 Free Assessment Appointment Created";
                            $html = "
                            <div style='background-color:#f4f4ff;padding:24px;'>
                              <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:8px;padding:32px 24px;font-family:Arial, sans-serif;color:#333;'>
                                <h2>📝 Free Assessment Appointment Created</h2>
                                <p><b>Client Name:</b> {$client->firstname} {$client->lastname}</p>
                                <p><b>Email:</b> {$client->email}</p>
                                <p><b>Number of Students:</b> {$client->numberofstudent}</p>
                                <p><b>Appointment Topic:</b> " . ($appointment['topic'] ?? 'Free Assessment') . "</p>
                                <p><b>Start:</b> " . ($appointment['start'] ?? '') . "</p>
                                <p><b>Finish:</b> " . ($appointment['finish'] ?? '') . "</p>
                              </div>
                            </div>";

                            try {
                                Mail::html($html, function ($msg) use ($adminEmail, $subject) {
                                    $msg->to($adminEmail)->subject($subject);
                                });
                            } catch (\Throwable $mErr) {
                                Log::warning('Free Assessment email warning: ' . $mErr->getMessage());
                            }

                            if ($dbInsertId && Schema::hasTable('alldatasend')) {
                                DB::table('alldatasend')->where('id', $dbInsertId)->update(['freeassismentemailsend' => 1]);
                            }
                        }
                    }
                }

                $results[] = [
                    'inserted_id' => $dbInsertId,
                    'tc_appointment_id' => $tcId,
                    'tutorcruncher' => $tcResponse,
                ];
            }

            return response()->json(['success' => true, 'results' => $results], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/subjects
     */
    public function subjectsAPIGET(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $data = $this->tutorCruncher->get('/subjects/', $request->query(), $branchId, 'Subjects');
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/subjectsalldata
     */
    public function GetAlldatasubject(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $query = DB::table('subjectall');
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }
            return response()->json($query->get());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/location
     */
    public function locationAPIGET(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $data = $this->tutorCruncher->get('/locations/', $request->query(), $branchId, 'Branch');
            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/locationalldata
     */
    public function GetAlldatalocation(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $query = DB::table('location');
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }
            return response()->json($query->get());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/appointments
     */
    public function appointmentsAPIPOST(Request $request)
    {
        try {
            $payload = $request->all();
            $branchId = (string)($payload[0]['branch_id'] ?? ($payload['branch_id'] ?? '1017'));
            $res = $this->tutorCruncher->post('/appointments/', $payload, $branchId, 'Appointment');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/appointments/{id}
     */
    public function appointmentputApi(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->put("/appointments/{$id}/", $request->all(), $branchId, 'Appointment');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/appointments/{id}
     */
    public function appointmentfilterApi(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id') ?? '1017';
            $res = $this->tutorCruncher->get('/appointments/', ['recipient' => $id], $branchId, 'Appointment');
            return response()->json([
                'success' => true,
                'message' => 'Appointments filtered by recipient successfully.',
                'data' => $res,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/appointmentstwo/{id}
     */
    public function appointmentfilterApitwo(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id') ?? '1017';
            if (!$id) {
                return response()->json(['error' => 'Recipient ID is required in the URL.'], 400);
            }

            $ids = array_filter(array_map('trim', explode(',', (string)$id)));
            $mergedAppointmentsRaw = [];
            $rawResponses = [];

            foreach ($ids as $recipientId) {
                $res = $this->tutorCruncher->get('/appointments/', ['recipient' => $recipientId], $branchId, 'Appointment');
                $list = [];
                if (is_array($res)) {
                    $list = $res['results'] ?? (isset($res[0]) ? $res : []);
                }
                $rawResponses[] = [
                    'recipient_id' => $recipientId,
                    'data' => $list,
                ];
                foreach ($list as $appt) {
                    $mergedAppointmentsRaw[] = $appt;
                }
            }

            // Deduplicate by appt id
            $uniqueMap = [];
            foreach ($mergedAppointmentsRaw as $appt) {
                if (is_array($appt) && isset($appt['id'])) {
                    $uniqueMap[$appt['id']] = $appt;
                }
            }
            $mergedAppointments = array_values($uniqueMap);

            // Join each appointment with alldatasend table where appoinmet_id = appt.id and pre-enrich service details
            $allData = [];
            foreach ($mergedAppointments as &$appt) {
                $apptId = $appt['id'] ?? null;
                if (!$apptId) continue;

                $dbRow = null;
                if (Schema::hasTable('alldatasend')) {
                    $dbRow = DB::table('alldatasend')->where('appoinmet_id', $apptId)->first();
                }

                if ($dbRow) {
                    $appt['db'] = (array)$dbRow;
                    $allData[] = $appt;
                }

                // Pre-enrich service details if conjobs or rcrs missing
                $serviceId = null;
                if (isset($appt['service']) && is_numeric($appt['service'])) {
                    $serviceId = $appt['service'];
                } elseif (isset($appt['service']['id'])) {
                    $serviceId = $appt['service']['id'];
                }

                if ($serviceId) {
                    try {
                        $svcData = $this->tutorCruncher->get("/services/{$serviceId}", [], $branchId, 'Service');
                        if (is_array($svcData) && !isset($svcData['error'])) {
                            if (!is_array($appt['service'])) {
                                $appt['service'] = ['id' => $serviceId];
                            }
                            $appt['service']['conjobs'] = $svcData['conjobs'] ?? ($appt['service']['conjobs'] ?? []);
                            $appt['service']['rcrs'] = $svcData['rcrs'] ?? ($appt['service']['rcrs'] ?? []);
                            $appt['service']['name'] = $svcData['name'] ?? ($appt['service']['name'] ?? '');
                        }
                    } catch (\Throwable $e) {
                        // ignore single service fetch warning
                    }
                }
            }
            unset($appt);

            return response()->json([
                'alldata' => $allData,
                'success' => true,
                'message' => 'Appointments fetched for all recipient ID(s)',
                'branch_id' => $branchId,
                'recipient_ids' => array_values($ids),
                'total_appointments' => count($mergedAppointments),
                'data' => $mergedAppointments,
                'raw_responses' => $rawResponses,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/appointmentsthree/{id}
     */
    public function appointmentfilterApithree(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id') ?? '1017';
            if (!$id) {
                return response()->json(['error' => 'Recipient ID is required in the URL.'], 400);
            }

            $ids = array_filter(array_map('trim', explode(',', (string)$id)));
            $mergedAppointments = [];

            foreach ($ids as $recipientId) {
                $res = $this->tutorCruncher->get('/appointments/', ['recipient' => $recipientId], $branchId, 'Appointment');
                $list = is_array($res) ? ($res['results'] ?? (isset($res[0]) ? $res : [])) : [];
                foreach ($list as $appt) {
                    $mergedAppointments[] = $appt;
                }
            }

            $allData = [];
            foreach ($mergedAppointments as $appt) {
                $apptId = $appt['id'] ?? null;
                if (!$apptId) continue;

                $rows = [];
                if (Schema::hasTable('alldatasend')) {
                    $rows = DB::table('alldatasend')->where('appoinmet_id', $apptId)->get()->toArray();
                }

                if (!empty($rows)) {
                    $appt['dbRows'] = array_map(function($r) { return (array)$r; }, $rows);
                    $allData[] = $appt;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Filtered appointments fetched successfully.',
                'data' => $allData,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/appointments
     */
    public function appointmentsAPIGET(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->get('/appointments/', $request->query(), $branchId, 'Appointment');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/services
     */
    public function servicesAPIPOST(Request $request)
    {
        try {
            $branchId = (string)($request->input('branch_id', '1017'));
            $res = $this->tutorCruncher->post('/services/', $request->all(), $branchId, 'Services');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/services/{id}
     */
    public function servicesAPIgetbyid(Request $request, $id)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->get("/services/{$id}/", [], $branchId, 'Services');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/service/{id}
     */
    public function getbyidShowService(Request $request, $id)
    {
        return $this->servicesAPIgetbyid($request, $id);
    }

    /**
     * GET /api/countries
     */
    public function saveAllCountry(Request $request)
    {
        $apiKey = '38a6db3d34b2fc558a0db4ca472bb81b1e0858e6';
        $url = 'https://app.tutorcruncher.com/api/countries/';

        try {
            while ($url) {
                $response = Http::withHeaders([
                    'Authorization' => "Token {$apiKey}",
                    'Content-Type' => 'application/json',
                ])->get($url);

                if (!$response->successful()) {
                    break;
                }

                $data = $response->json();
                $countriesList = $data['results'] ?? [];

                foreach ($countriesList as $country) {
                    usleep(200000); // 200ms delay to prevent 429 rate limit
                    $countryId = $country['id'];
                    $detailRes = Http::withHeaders([
                        'Authorization' => "Token {$apiKey}",
                        'Content-Type' => 'application/json',
                    ])->get("https://app.tutorcruncher.com/api/countries/{$countryId}");

                    if ($detailRes->successful()) {
                        $fullCountry = $detailRes->json();
                        DB::statement("
                            INSERT INTO countries (id, name, abbreviation, three_letter_iso, currency)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                name = VALUES(name),
                                abbreviation = VALUES(abbreviation),
                                three_letter_iso = VALUES(three_letter_iso),
                                currency = VALUES(currency)
                        ", [
                            $fullCountry['id'],
                            $fullCountry['name'] ?? '',
                            $fullCountry['abbreviation'] ?? '',
                            $fullCountry['three_letter_iso'] ?? '',
                            $fullCountry['currency'] ?? '',
                        ]);
                    }
                }

                $url = $data['next'] ?? null;
            }

            return response()->json(['message' => 'Countries synced successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/availability
     */
    public function AllContractorsavailability(Request $request)
    {
        try {
            $branchId = $request->input('branch_id') ?? $request->query('branch_id');
            if (!$branchId) {
                return response()->json(['error' => 'branch_id is required'], 400);
            }

            $apiKeyRow = DB::table('branch_api_key')
                ->where('branch_id', $branchId)
                ->where('action', 'Contractors')
                ->first();

            if (!$apiKeyRow) {
                return response()->json(['error' => 'API key not found for Contractors action'], 404);
            }

            $apiKey = $apiKeyRow->api_key;
            $res = Http::withHeaders([
                'Authorization' => "Token {$apiKey}",
                'Content-Type' => 'application/json',
            ])->get('https://app.tutorcruncher.com/api/contractors/');

            if (!$res->successful()) {
                return response()->json(['error' => 'Failed to fetch contractors from TutorCruncher'], 500);
            }

            $contractorList = $res->json()['results'] ?? [];
            foreach ($contractorList as $contractor) {
                usleep(200000); // 200ms delay to prevent 429 rate limit
                $contractorId = $contractor['id'];
                $availRes = Http::withHeaders([
                    'Authorization' => "Token {$apiKey}",
                    'Content-Type' => 'application/json',
                ])->get("https://app.tutorcruncher.com/api/contractor_availability/{$contractorId}");

                if ($availRes->successful()) {
                    $availabilityList = $availRes->json();
                    if (is_array($availabilityList)) {
                        foreach ($availabilityList as $item) {
                            DB::table('availabilityslot')->insert([
                                'type' => $item['type'] ?? '',
                                'start' => $item['start'] ?? null,
                                'finish' => $item['finish'] ?? null,
                                'apt_id' => $item['apt_id'] ?? null,
                            ]);
                        }
                    }
                }
            }

            return response()->json(['message' => 'All contractors availability saved successfully.']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/countriesAll
     */
    public function GetallCountry()
    {
        $rows = DB::table('countries')->get();
        return response()->json($rows);
    }

    /**
     * POST /api/create-payment-intent (Stripe)
     */
    public function paymentGetwaystripe(Request $request)
    {
        return response()->json(['success' => true, 'clientSecret' => 'mock_stripe_secret']);
    }

    /**
     * POST /api/reviews
     */
    public function ReviewApi(Request $request)
    {
        try {
            $branchId = $request->input('branch_id') ?? $request->query('branch_id');
            $res = $this->tutorCruncher->post('/reviews/', $request->all(), $branchId, 'Services');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/sendemailadmin
     */
    public function Adminemail(Request $request)
    {
        $email = $request->input('email', env('MAIL_ADMIN', 'admin@myfrwrd.com'));

        $emailHTML = "
        <div style='background-color:#f4f4ff;padding:24px;'>
          <div style='max-width:600px;margin:0 auto;background-color:#ffffff;border-radius:8px;padding:32px 24px;font-family:Arial, sans-serif;color:#333;'>
            <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
              <h2 style='color: #4A90E2;'>Tutor Slot Booking Notification</h2>
              <p>Dear Admin,</p>
              <p>A student has booked a slot on <strong>TutorCruncher</strong>.</p>
              <p>Please login to the <a href='https://app.tutorcruncher.com/' target='_blank'>TutorCruncher admin panel</a> and accept the tutor slot to confirm the booking.</p>
              <p>Thank you,<br/>FRWRD Team</p>
            </div>
          </div>
        </div>";

        try {
            Mail::html($emailHTML, function ($msg) use ($email) {
                $msg->to($email)->subject('Tutor Slot Booking Notification');
            });
        } catch (\Throwable $mailErr) {
            Log::warning('Adminemail SMTP warning: ' . $mailErr->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification email sent to admin successfully',
        ]);
    }

    /**
     * POST /api/client-packages
     */
    public function createClientPackageSingleTable(Request $request)
    {
        try {
            $pkg = ClientPackageData::create($request->all());
            return response()->json(['success' => true, 'data' => $pkg], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/client-package/reschedule
     */
    public function updatePackageRescheduleCount(Request $request)
    {
        $clientId = $request->input('clientid');
        $count = $request->input('purches_status');

        ClientPackageData::where('clientid', $clientId)->update(['purches_status' => $count]);
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/client-packages/{clientid}
     */
    public function getClientPackageByClientId(Request $request, $clientid)
    {
        $query = ClientPackageData::where('clientid', $clientid);
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');
        if ($branchId && Schema::hasColumn('client_package_data', 'branch_id')) {
            $query->where('branch_id', (string) $branchId);
        }
        $pkgs = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $pkgs]);
    }

    /**
     * GET /api/client-packagesid/{id}
     */
    public function getClientPackageById($id)
    {
        $pkg = ClientPackageData::find($id);
        return response()->json($pkg);
    }

    /**
     * PUT /api/client-packagesupdate/{id}
     */
    public function updateClientPackageById(Request $request, $id)
    {
        ClientPackageData::where('id', $id)->update($request->all());
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/client/status-check
     */
    public function autoStatusCheck(Request $request)
    {
        $clientid = $request->query('clientid') ?? $request->input('clientid');

        if (!$clientid) {
            return response()->json(['error' => 'clientid is required'], 400);
        }

        try {
            $client = Client::where('clientid', $clientid)->first();
            if (!$client) {
                return response()->json(['error' => 'Client not found in client table'], 404);
            }

            $hasPackage = false;
            if (Schema::hasTable('client_package_data')) {
                $hasPackage = DB::table('client_package_data')->where('clientid', $clientid)->exists();
            }
            if (!$hasPackage && Schema::hasTable('alldatasend')) {
                $hasPackage = DB::table('alldatasend')->where('client_id', $clientid)->orWhere('clientid', $clientid)->exists();
            }

            $newStatus = $hasPackage ? 'existing_user' : 'new_user';

            Client::where('clientid', $clientid)->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => "Client status set to '{$newStatus}'",
                'clientid' => $clientid,
                'status' => $newStatus,
            ], 200);
        } catch (\Throwable $err) {
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * POST / GET /api/subjectFilter
     */
    public function ClientSubjectnameFilterData(Request $request)
    {
        $subjectNames = $request->input('subjectNames');
        $branchId = $request->input('branch_id') ?? $request->query('branch_id');
        $yearLevels = $request->input('yearLevels');

        if (!$subjectNames || (is_array($subjectNames) && count($subjectNames) === 0)) {
            return response()->json(['error' => 'subjectNames is required'], 400);
        }

        if (!$branchId) {
            return response()->json(['error' => 'branch_id is required'], 400);
        }

        try {
            $rows = DB::table('tutors')
                ->where(function($q) use ($branchId) {
                    $q->where('branch_id', (string) $branchId)
                      ->orWhere('branch_id', (int) $branchId);
                })
                ->get();

            $subjectArray = is_array($subjectNames) ? $subjectNames : [$subjectNames];
            $yearArray = $yearLevels
                ? (is_array($yearLevels) ? array_map(fn($y) => trim((string)$y), $yearLevels) : [trim((string)$yearLevels)])
                : [];

            $filteredAvailabilities = [];

            foreach ($rows as $tutor) {
                try {
                    $skills = [];
                    if (!empty($tutor->skills)) {
                        $skills = is_array($tutor->skills) ? $tutor->skills : (json_decode($tutor->skills, true) ?: []);
                    }

                    if (empty($tutor->availability)) continue;

                    $availability = is_array($tutor->availability) ? $tutor->availability : json_decode($tutor->availability, true);
                    if (!is_array($availability)) continue;

                    foreach ($skills as $skill) {
                        $skillSubject = $skill['subject'] ?? '';
                        $subjectMatch = in_array($skillSubject, $subjectArray);

                        $yearMatch = false;
                        if (empty($yearArray)) {
                            $yearMatch = true;
                        } elseif (!empty($skill['quallevel'])) {
                            $qlStr = (string)$skill['quallevel'];
                            if (preg_match('/Year\s*(\d+)/i', $qlStr, $matches)) {
                                $yearMatch = in_array($matches[1], $yearArray);
                            } elseif (preg_match('/(\d+)/', $qlStr, $matches)) {
                                $yearMatch = in_array($matches[1], $yearArray);
                            } else {
                                $yearMatch = in_array(trim($qlStr), $yearArray);
                            }
                        }

                        if ($subjectMatch && $yearMatch) {
                            foreach ($availability as $slot) {
                                if (is_array($slot)) {
                                    $filteredAvailabilities[] = array_merge($slot, [
                                        'tutor_id' => $tutor->id,
                                        'tutor_name' => "{$tutor->first_name} {$tutor->last_name}",
                                        'subject' => $skillSubject,
                                        'year' => $skill['quallevel'] ?? null,
                                    ]);
                                }
                            }
                        }
                    }
                } catch (\Throwable $err) {
                    // skip tutor on parse error
                }
            }

            return response()->json($filteredAvailabilities);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    public function Adminandclientemailsend(Request $request)
    {
        $clientId = $request->input('clientid');
        $clientEmail = $request->input('client_email');
        $reviewNumber = $request->input('review_number');
        $description = $request->input('description');

        if (!$clientId || !$clientEmail || !$reviewNumber || !$description) {
            return response()->json([
                'error' => 'clientid, client_email, review_number, and description are required',
            ], 400);
        }

        $adminEmail = env('MAIL_ADMIN', 'growyourbrnds@gmail.com');

        $adminSubject = "Slot Issue Reported - Review #{$reviewNumber}";
        $adminHTML = "
        <h2>Slot Issue Reported</h2>
        <p><strong>Client ID:</strong> {$clientId}</p>
        <p><strong>Submitted By:</strong> {$clientEmail}</p>
        <p><strong>Review #:</strong> {$reviewNumber}</p>
        <p><strong>Description:</strong></p>
        <p>{$description}</p>";

        $clientSubject = "Feedback Received - Review #{$reviewNumber}";
        $clientHTML = "
        <h2>Thank You for Your Feedback</h2>
        <p>We received your report regarding Review #{$reviewNumber}.</p>
        <p><strong>Your Description:</strong></p>
        <p>{$description}</p>
        <p>Our team is looking into this and will get back to you shortly.</p>";

        try {
            Mail::html($adminHTML, function ($msg) use ($adminEmail, $adminSubject) {
                $msg->to($adminEmail)->subject($adminSubject);
            });
            Mail::html($clientHTML, function ($msg) use ($clientEmail, $clientSubject) {
                $msg->to($clientEmail)->subject($clientSubject);
            });
        } catch (\Throwable $mailErr) {
            Log::warning('Adminandclientemailsend SMTP warning: ' . $mailErr->getMessage());
        }

        return response()->json([
            'message' => 'Review saved and emails sent successfully.',
        ]);
    }

    /**
     * GET /api/clientpricestatus/{clientid}
     */
    public function priceStatuseclientid($clientid)
    {
        try {
            $row = null;
            if (Schema::hasTable('alldatasend')) {
                $row = DB::table('alldatasend')
                    ->where(function($q) use ($clientid) {
                        $q->where('client_id', $clientid)->orWhere('clientid', $clientid);
                    })
                    ->where('pricestatus', 1)
                    ->first();
            }
            if (!$row && Schema::hasTable('client_package_data')) {
                $row = DB::table('client_package_data')
                    ->where('clientid', $clientid)
                    ->where('purches_status', 1)
                    ->first();
            }

            if (!$row) {
                return response()->json([
                    'status' => 404,
                    'message' => "No records found for client_id: {$clientid} with pricestatus = 1",
                    'pricestatus' => 0,
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'message' => "Pricestatus values for client_id: {$clientid}",
                'pricestatus' => $row->pricestatus ?? 1,
            ], 200);
        } catch (\Throwable $error) {
            return response()->json([
                'status' => 404,
                'message' => "No records found for client_id: {$clientid} with pricestatus = 1",
                'pricestatus' => 0,
            ], 404);
        }
    }

    /**
     * POST /api/client-packagesfree
     */
    public function createClientPackageSingleTabletwo(Request $request)
    {
        try {
            $row = ClientPackageDataTwo::create($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Client package saved successfully',
                'insertId' => $row->id ?? null,
                'data' => $row,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error while saving package data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/client-packagesfree/{clientid}
     */
    public function getClientPackageByClientIdtwo(Request $request, $clientid)
    {
        $query = ClientPackageDataTwo::where('clientid', $clientid);
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');
        if ($branchId && Schema::hasColumn('client_package_data_two', 'branch_id')) {
            $query->where('branch_id', (string) $branchId);
        }
        $rows = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * DELETE /api/client-packagesfree/{id}
     */
    public function deleteClientPackageById($id)
    {
        ClientPackageDataTwo::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/client-packagesclientid/{clientid}
     */
    public function deleteClientPackageByClientId($clientid)
    {
        ClientPackageDataTwo::where('clientid', $clientid)->delete();
        return response()->json(['success' => true]);
    }

    /**
     * POST /api/proformainvoice
     */
    public function proformaInvoiceAPIPOST(Request $request)
    {
        try {
            $branchId = (string)($request->input('branch_id', '1017'));
            $res = $this->tutorCruncher->post('/proforma-invoices/', $request->all(), $branchId, 'Performainvoices');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/proformainvoice
     */
    public function proformaInvoiceAPIGet(Request $request)
    {
        try {
            $branchId = (string)($request->query('branch_id', '1017'));
            $res = $this->tutorCruncher->get('/proforma-invoices/', $request->query(), $branchId, 'Performainvoices');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/proformainvoicetakepayment/{id}
     */
    public function proformaInvoicetakepaymentAPIPOST(Request $request, $id)
    {
        try {
            $branchId = (string)($request->input('branch_id', '1017'));
            $res = $this->tutorCruncher->post("/proforma-invoices/{$id}/take-payment/", $request->all(), $branchId, 'Performainvoices');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/freeassismentstatus/{client_id}
     */
    public function FreeAssismentstatuscheck($client_id)
    {
        try {
            if (!$client_id) {
                return response()->json(['success' => false, 'error' => 'client_id is required'], 400);
            }

            $row = null;
            if (Schema::hasTable('alldatasend')) {
                $row = DB::table('alldatasend')
                    ->where(function($q) use ($client_id) {
                        $q->where('client_id', $client_id)->orWhere('clientid', $client_id);
                    })
                    ->orderBy('id', 'desc')
                    ->first();
            }
            if (!$row && Schema::hasTable('client_package_data')) {
                $row = DB::table('client_package_data')
                    ->where('clientid', $client_id)
                    ->orderBy('id', 'desc')
                    ->first();
            }

            if (!$row) {
                return response()->json([
                    'success' => false,
                    'message' => 'No record found for this client_id',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'client_id' => $client_id,
                'freeassismentemailsend' => $row->freeassismentemailsend ?? 0,
            ], 200);
        } catch (\Throwable $error) {
            return response()->json(['success' => false, 'error' => 'Internal server error'], 500);
        }
    }

    /**
     * GET /api/clientbookdata/{id}
     */
    public function getClientBookData($id)
    {
        $rows = ClientPackageData::where('clientid', $id)->orderBy('created_at', 'desc')->get();
        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    /**
     * GET /api/save-all-client-student-appointments
     */
    public function saveAllClientsStudentAppointments(Request $request)
    {
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');
        if (!$branchId) {
            return response()->json(['error' => 'branch_id is required'], 400);
        }

        try {
            $apiKeyRow = DB::table('branch_api_key')
                ->where('branch_id', $branchId)
                ->where('action', 'Appointment')
                ->first();

            if (!$apiKeyRow) {
                return response()->json(['error' => 'API key not found'], 404);
            }

            $apiKey = $apiKeyRow->api_key;
            $clients = DB::table('client')->where('branch_id', $branchId)->get();

            $totalSaved = 0;
            $finalData = [];

            $now = time();
            $startOfWeek = strtotime('last Sunday', $now);
            if (date('w', $now) == 0) {
                $startOfWeek = strtotime('today', $now);
            }
            $endOfWeek = strtotime('+6 days 23:59:59', $startOfWeek);

            foreach ($clients as $client) {
                $clientId = $client->clientid;
                $clientEmail = $client->email;

                $students = is_array($client->studentdetails) ? $client->studentdetails : json_decode($client->studentdetails ?? '[]', true);
                if (empty($students) || !is_array($students)) continue;

                foreach ($students as $st) {
                    usleep(200000); // 200ms delay to prevent 429 rate limit
                    $studentId = $st['id'] ?? null;
                    if (!$studentId) continue;

                    try {
                        $res = Http::withHeaders([
                            'Authorization' => "Token {$apiKey}",
                        ])->get("https://app.tutorcruncher.com/api/appointments/", [
                            'recipient' => $studentId,
                        ]);

                        if (!$res->successful()) continue;

                        $appointments = $res->json()['results'] ?? [];
                        if (empty($appointments)) continue;

                        $currentWeekAppointments = [];
                        foreach ($appointments as $appt) {
                            $apptTime = strtotime($appt['start']);
                            if ($apptTime >= $startOfWeek && $apptTime <= $endOfWeek) {
                                $currentWeekAppointments[] = $appt;
                            }
                        }

                        if (empty($currentWeekAppointments)) continue;

                        usort($currentWeekAppointments, fn($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));
                        $firstClass = $currentWeekAppointments[0];

                        DB::statement("
                            INSERT INTO client_student_appointments 
                            (client_id, client_email, student_id, last_appointment_id, last_appointment_date, branch_id)
                            VALUES (?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                                last_appointment_id = VALUES(last_appointment_id),
                                last_appointment_date = VALUES(last_appointment_date)
                        ", [
                            $clientId,
                            $clientEmail,
                            $studentId,
                            $firstClass['id'],
                            $firstClass['start'],
                            $branchId,
                        ]);

                        $totalSaved++;
                        $finalData[] = [
                            'client_id' => $clientId,
                            'student_id' => $studentId,
                            'last_appointment_id' => $firstClass['id'],
                            'last_appointment_date' => $firstClass['start'],
                        ];
                    } catch (\Throwable $err) {
                        // Ignore individual student error
                    }
                }
            }

            return response()->json([
                'success' => true,
                'branch_id' => $branchId,
                'total_clients' => count($clients),
                'total_saved' => $totalSaved,
                'data' => $finalData,
            ]);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
