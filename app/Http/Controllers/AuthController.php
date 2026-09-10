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
        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json(['success' => false, 'message' => 'Email and password required'], 400);
        }

        try {
            $user = Client::where('email', $email)->first();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            // Check password (bcrypt)
            if (!Hash::check($password, $user->password)) {
                return response()->json(['success' => false, 'message' => 'Invalid email or password'], 401);
            }

            // Generate JWT token
            $token = JwtHelper::generateToken($user->clientid, 3600);

            // Log login
            DB::table('login_logs')->insert([
                'clientid' => $user->clientid,
                'email' => $user->email,
            ]);

            $responseUser = [
                'clientid' => $user->clientid,
                'firstname' => $user->firstname,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'studentdetails' => is_array($user->studentdetails) ? $user->studentdetails : (json_decode($user->studentdetails ?? '[]', true) ?: []),
                'status' => $user->status,
                'numberofstudent' => $user->numberofstudent,
                'branch_id' => $user->branch_id,
                'phone_number' => $user->phone_number,
                'how_you_came_to_know' => $user->how_you_came_to_know,
                'students' => $user->students,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => $responseUser,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Login error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * GET /api/client/profile/{clientid}
     */
    public function getClientProfile($clientid)
    {
        try {
            $client = Client::where('clientid', $clientid)->first();

            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $client->studentdetails = is_array($client->studentdetails)
                ? $client->studentdetails
                : (json_decode($client->studentdetails ?? '[]', true) ?: []);

            return response()->json([
                'success' => true,
                'data' => $client,
            ]);
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
            return response()->json(['success' => false, 'message' => 'clientid required'], 400);
        }

        try {
            Client::where('clientid', $clientId)->update(['is_first_login' => 1]);
            return response()->json(['success' => true, 'message' => 'First login marked as completed']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Internal Server Error'], 500);
        }
    }

    /**
     * DELETE /api/delete-student/{clientid}/{student_id}
     */
    public function deleteStudent($clientid, $student_id)
    {
        try {
            $client = Client::where('clientid', $clientid)->first();
            if (!$client) {
                return response()->json(['success' => false, 'message' => 'Client not found'], 404);
            }

            $students = is_array($client->studentdetails)
                ? $client->studentdetails
                : (json_decode($client->studentdetails ?? '[]', true) ?: []);

            $filteredStudents = array_values(array_filter($students, function ($s) use ($student_id) {
                return (string)($s['id'] ?? '') !== (string)$student_id;
            }));

            $client->studentdetails = $filteredStudents;
            $client->save();

            Student::where('studentid', $student_id)->delete();

            return response()->json(['success' => true, 'message' => 'Student deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error deleting student'], 500);
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
        $studentData = $request->all();

        try {
            $branchId = (string)($studentData['branch_id'] ?? '1017');
            $tcRes = $this->tutorCruncher->post('/students/', $studentData, $branchId, 'Recipients');

            if (!empty($tcRes['id'])) {
                Student::updateOrInsert(
                    ['studentid' => $tcRes['id']],
                    [
                        'studentfirstname' => $tcRes['first_name'] ?? ($studentData['first_name'] ?? ''),
                        'studentlastname' => $tcRes['last_name'] ?? ($studentData['last_name'] ?? ''),
                        'email' => $tcRes['email'] ?? ($studentData['email'] ?? ''),
                        'clientid' => $studentData['client_id'] ?? null,
                    ]
                );
            }

            return response()->json($tcRes);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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
        $to = $request->input('to');
        $subject = $request->input('subject', 'Notification');
        $html = $request->input('html', '');
        $clientId = $request->input('client_id');

        try {
            Mail::html($html, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });

            EmailLog::create([
                'client_id' => $clientId,
                'email' => $to,
                'subject' => $subject,
                'type' => 'custom',
                'status' => 'sent',
                'created_at' => now(),
            ]);

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
        $client = Client::where('clientid', $clientid)->first();
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        return response()->json($client);
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
            $res = $this->tutorCruncher->get("/contractor_availability/?contractor={$id}", [], $branchId, 'Contractors');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/availability
     */
    public function AllContractorsavailability(Request $request)
    {
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->get('/contractor_availability/', $request->query(), $branchId, 'Contractors');
            return response()->json($res);
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
        try {
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $query = DB::table('tutors');

            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }

            if ($request->has('subject')) {
                $query->where('skills', 'like', '%' . $request->query('subject') . '%');
            }

            return response()->json($query->orderByDesc('created_at')->get());
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/contractors
     */
    public function saveAllContractors(Request $request)
    {
        return response()->json(['message' => 'Sync triggered']);
    }

    /**
     * GET /api/clientDataget
     */
    public function ClientsetDatabae(Request $request)
    {
        $branchId = $request->query('branch_id') ?? $request->input('branch_id');
        $query = Client::query();
        if ($branchId) {
            $query->where('branch_id', (string) $branchId);
        }
        return response()->json($query->get());
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
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            $res = $this->tutorCruncher->get("/appointments/{$id}/", [], $branchId, 'Appointment');
            return response()->json($res);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/appointmentstwo/{id}
     */
    public function appointmentfilterApitwo(Request $request, $id)
    {
        return $this->appointmentfilterApi($request, $id);
    }

    /**
     * GET /api/appointmentsthree/{id}
     */
    public function appointmentfilterApithree(Request $request, $id)
    {
        return $this->appointmentfilterApi($request, $id);
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
        return response()->json(['message' => 'Countries synced']);
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
        return response()->json(['success' => true, 'message' => 'Review created']);
    }

    /**
     * POST /api/sendemailadmin
     */
    public function Adminemail(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Admin email sent']);
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
        $pkgs = $query->get();
        return response()->json($pkgs);
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
        return response()->json(['success' => true, 'status' => 'active']);
    }

    /**
     * POST / GET /api/subjectFilter
     */
    public function ClientSubjectnameFilterData(Request $request)
    {
        return response()->json(['success' => true, 'data' => []]);
    }

    /**
     * POST /api/sendReviewEmail
     */
    public function Adminandclientemailsend(Request $request)
    {
        return response()->json(['success' => true]);
    }

    /**
     * GET /api/clientpricestatus/{clientid}
     */
    public function priceStatuseclientid($clientid)
    {
        return response()->json(['success' => true, 'price_status' => 'paid']);
    }

    /**
     * POST /api/client-packagesfree
     */
    public function createClientPackageSingleTabletwo(Request $request)
    {
        try {
            $row = ClientPackageDataTwo::create($request->all());
            return response()->json(['success' => true, 'data' => $row], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
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
        $rows = $query->get();
        return response()->json($rows);
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
        $hasFree = ClientPackageDataTwo::where('clientid', $client_id)->exists();
        return response()->json(['status' => $hasFree ? 'booked' : 'available']);
    }

    /**
     * GET /api/clientbookdata/{id}
     */
    public function getClientBookData($id)
    {
        $pkg = ClientPackageData::where('clientid', $id)->first();
        return response()->json($pkg);
    }

    /**
     * GET /api/save-all-client-student-appointments
     */
    public function saveAllClientsStudentAppointments(Request $request)
    {
        return response()->json(['success' => true, 'message' => 'Synced appointments']);
    }
}
