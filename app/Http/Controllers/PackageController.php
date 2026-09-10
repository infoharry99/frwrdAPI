<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Holiday;
use App\Models\ClientTermsAcceptance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    /**
     * POST /api/packega
     */
    public function PackegaCreate(Request $request)
    {
        $packegas = $request->all();

        if (!is_array($packegas) || empty($packegas)) {
            return response()->json(['success' => false, 'message' => 'Invalid data format'], 400);
        }

        $insertedCount = 0;
        foreach ($packegas as $pkg) {
            Package::create([
                'name' => $pkg['name'] ?? null,
                'numberofclass' => $pkg['numberofclass'] ?? null,
                'description' => $pkg['description'] ?? null,
                'duration_days' => $pkg['duration_days'] ?? null,
                'price' => $pkg['price'] ?? null,
            ]);
            $insertedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$insertedCount} packegas created successfully",
            'insertedCount' => $insertedCount,
        ], 201);
    }

    /**
     * GET /api/allpackega
     */
    public function PackegaAllshow()
    {
        try {
            $rows = DB::table('packega')->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error fetching all packega'], 500);
        }
    }

    /**
     * GET /api/packega/{id}
     */
    public function PackegaById($id)
    {
        try {
            $pkg = DB::table('packega')->where('id', $id)->first();
            if (!$pkg) {
                return response()->json(['message' => 'Not found'], 404);
            }
            return response()->json($pkg);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Error fetching packega'], 500);
        }
    }

    /**
     * GET /api/allpackegastudent
     */
    public function PackegaAllshowstudent(Request $request)
    {
        $student = (int) $request->query('student');
        $level = (int) $request->query('level');
        $studyType = $request->query('studyType');

        if (
            !in_array($student, [1, 2]) ||
            !in_array($level, range(1, 13)) ||
            !in_array($studyType, ['group', 'individual'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing parameters. Use ?student=1|2&level=1..13&studyType=group|individual',
            ], 400);
        }

        try {
            $rows = DB::table('packega')->get();

            $updatedPackages = $rows->map(function ($pkg) use ($level, $student, $studyType) {
                if ($level <= 9) {
                    $totalField = $student === 1 ? 'total1' : ($studyType === 'group' ? 'total2' : 'total7');
                } elseif ($level <= 11) {
                    $totalField = $student === 1 ? 'total3' : ($studyType === 'group' ? 'total4' : 'total8');
                } else {
                    $totalField = $student === 1 ? 'total5' : ($studyType === 'group' ? 'total6' : 'total9');
                }

                $selectedTotal = trim($pkg->$totalField ?? '');

                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'numberofclass' => $pkg->numberofclass,
                    'note' => $pkg->note ?? null,
                    'description' => $pkg->description,
                    'offers' => $pkg->offers ?? null,
                    'types' => $pkg->types ?? null,
                    'duration_days' => $pkg->duration_days,
                    'created_at' => $pkg->created_at,
                    'total' => $selectedTotal,
                ];
            });

            return response()->json([
                'success' => true,
                'student' => $student,
                'level' => $level,
                'data' => $updatedPackages,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching packega data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/terms/accept
     */
    public function AcceptTerms(Request $request)
    {
        $clientId = $request->input('client_id');
        $isAccepted = $request->input('is_accepted');

        if (!$clientId || !is_bool($isAccepted)) {
            return response()->json([
                'success' => false,
                'message' => 'client_id and is_accepted (true/false) required',
            ], 400);
        }

        try {
            $acceptedAt = $isAccepted ? now() : null;

            $id = DB::table('client_terms_acceptance')->insertGetId([
                'client_id' => $clientId,
                'is_accepted' => $isAccepted,
                'accepted_at' => $acceptedAt,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Terms status saved',
                'data' => [
                    'id' => $id,
                    'client_id' => $clientId,
                    'is_accepted' => $isAccepted,
                    'accepted_at' => $acceptedAt,
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Server error'], 500);
        }
    }

    /**
     * GET /api/holidays
     */
    public function HolidayAllShow()
    {
        try {
            $rows = Holiday::orderBy('holiday_date', 'asc')->get();
            return response()->json([
                'success' => true,
                'count' => $rows->count(),
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching holidays'], 500);
        }
    }

    /**
     * GET /api/holidays/year/{year}
     */
    public function HolidayByYear($year)
    {
        try {
            $rows = Holiday::where('year', $year)->orderBy('holiday_date', 'asc')->get();
            return response()->json([
                'success' => true,
                'year' => (string)$year,
                'count' => $rows->count(),
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching holidays by year'], 500);
        }
    }

    /**
     * GET /api/holidays/upcoming
     */
    public function UpcomingHolidays()
    {
        try {
            $rows = Holiday::where('holiday_date', '>=', now()->toDateString())
                ->orderBy('holiday_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'count' => $rows->count(),
                'data' => $rows,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Error fetching upcoming holidays'], 500);
        }
    }
}
