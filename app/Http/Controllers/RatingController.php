<?php

namespace App\Http\Controllers;

use App\Models\ReviewDoctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RatingController extends Controller
{
    /**
     * POST /api/reviewsdoctor
     */
    public function createReview(Request $request)
    {
        $userId = $request->input('user_id');
        $doctorId = $request->input('doctor_id');
        $rating = $request->input('rating');
        $message = $request->input('message');

        if (!$userId || !$doctorId || !$rating) {
            return response()->json(['error' => 'user_id, doctor_id, and rating are required!'], 400);
        }

        try {
            $id = DB::table('reviewdoctor')->insertGetId([
                'user_id' => $userId,
                'doctor_id' => $doctorId,
                'rating' => $rating,
                'message' => $message,
                'created_at' => now(),
            ]);

            return response()->json([
                'id' => $id,
                'user_id' => $userId,
                'doctor_id' => $doctorId,
                'rating' => $rating,
                'message' => $message,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * GET /api/reviewsdoctor
     */
    public function getAllReviews()
    {
        try {
            $rows = DB::table('reviewdoctor')->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * GET /api/reviewsdoctor/doctor/{doctor_id}
     */
    public function getDoctorReviews($doctor_id)
    {
        try {
            $rows = DB::table('reviewdoctor')->where('doctor_id', $doctor_id)->get();
            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * DELETE /api/reviewsdoctor/{id}
     */
    public function deleteReview($id)
    {
        try {
            DB::table('reviewdoctor')->where('id', $id)->delete();
            return response()->json(['message' => 'Review deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * GET /api/reviewsdoctorcount
     */
    public function getAverageRatings()
    {
        try {
            $rows = DB::table('tutors as t')
                ->leftJoin('reviewdoctor as r', 't.id', '=', 'r.doctor_id')
                ->select('t.id as doctor_id', DB::raw('AVG(r.rating) as average_rating'), DB::raw('COUNT(r.id) as review_count'))
                ->groupBy('t.id')
                ->orderByDesc('average_rating')
                ->get();

            return response()->json($rows);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
