<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function applyCoupon(Request $request)
    {
        $code = $request->input('code');
        $userId = $request->input('userId');

        if (!$code || !$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon code and userId required',
            ], 400);
        }

        $coupon = Coupon::where('code', $code)->first();

        if (!$coupon) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coupon',
            ], 404);
        }

        if ($coupon->expiry_date && $coupon->expiry_date->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Coupon expired',
            ], 400);
        }

        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json([
                'success' => false,
                'message' => 'Usage limit reached',
            ], 400);
        }

        $coupon->increment('used_count');

        return response()->json([
            'success' => true,
            'discount_type' => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'message' => 'Coupon applied successfully',
        ]);
    }
}
