<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\NGeniusPaymentService;
use App\Services\PaymentFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected NGeniusPaymentService $paymentService;
    protected PaymentFulfillmentService $fulfillmentService;

    public function __construct(
        NGeniusPaymentService $paymentService,
        PaymentFulfillmentService $fulfillmentService
    ) {
        $this->paymentService = $paymentService;
        $this->fulfillmentService = $fulfillmentService;
    }

    /**
     * POST /api/create-order
     */
    public function createOrder(Request $request)
    {
        try {
            $rawAmount = $request->input('amount', '0');
            $cleanAmount = (float) str_replace(',', '', (string) $rawAmount);

            $email = $request->input('email', 'buyer@example.com');
            $studentIds = $request->input('student_ids', []);
            $clientId = $request->input('client_id');
            $appointIds = $request->input('appoint_ids', []);
            $packageIds = $request->input('package_ids', []);
            $branchId = $request->input('branch_id');
            $bookingPayload = $request->input('booking_payload');

            $res = $this->paymentService->createOrder(
                $cleanAmount,
                $email,
                $studentIds,
                $clientId ? (int) $clientId : null,
                $appointIds,
                $packageIds,
                $branchId,
                $bookingPayload
            );

            return response()->json($res);
        } catch (\Throwable $e) {
            Log::error('Create order error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/success
     */
    public function success(Request $request)
    {
        $ref = $request->query('ref');
        $frontendSuccess = env('FRONTEND_SUCCESS_URL', 'https://app.frwrdtutors.com/success');
        $frontendFailed = env('FRONTEND_FAILED_URL', 'https://app.frwrdtutors.com/failed');

        if (!$ref) {
            return redirect("{$frontendFailed}?error=order_not_found");
        }

        try {
            $statusRes = $this->paymentService->checkOrderStatus($ref);
            $paymentState = $statusRes['status'];

            if (in_array($paymentState, ['CAPTURED', 'SETTLED', 'PAID'])) {
                // Process fulfillment server-side
                try {
                    $this->fulfillmentService->processPaymentSuccess($ref, $statusRes['data']);
                } catch (\Throwable $procErr) {
                    Log::error("Error in server-side payment processing: {$procErr->getMessage()}");
                }

                return redirect("{$frontendSuccess}?ref={$ref}");
            } else {
                return redirect("{$frontendFailed}?status=" . ($paymentState ?: 'UNKNOWN'));
            }
        } catch (\Throwable $e) {
            Log::error("Error in /success callback: {$e->getMessage()}");
            return redirect("https://imanglobal.net/cancel");
        }
    }

    /**
     * GET /api/failed
     */
    public function failed(Request $request)
    {
        return response('❌ Payment failed.');
    }

    /**
     * GET /api/cancel
     */
    public function cancel(Request $request)
    {
        return response('❌ Payment cancelled.');
    }

    /**
     * POST /api/order_status
     */
    public function orderStatus(Request $request)
    {
        $ref = $request->input('ref');
        $status = strtolower($request->input('status', ''));

        if (!$ref) {
            return response()->json(['message' => 'ref is required'], 400);
        }

        try {
            $updateStatus = match ($status) {
                'success' => 'SUCCESS',
                'failed' => 'FAILED',
                default => 'CANCELLED',
            };

            Payment::where('ref_no', $ref)->update([
                'status' => $updateStatus,
                'raw_response' => $request->all(),
            ]);

            if ($updateStatus === 'SUCCESS') {
                try {
                    $this->fulfillmentService->processPaymentSuccess($ref, $request->all());
                } catch (\Throwable $e) {
                    Log::error("Error fulfilling order from webhook: {$e->getMessage()}");
                }
            }

            return response()->json([
                'message' => $status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : 'cancel'),
                'ref' => $ref,
                'updatedStatus' => $updateStatus,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error in /order_status webhook: {$e->getMessage()}");
            return response()->json(['message' => 'Internal Server Error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/payment-status/{ref}
     */
    public function paymentStatus(string $ref)
    {
        if (!$ref) {
            return response()->json(['error' => 'ref is required'], 400);
        }

        $payment = Payment::where('ref_no', $ref)->first();

        if (!$payment) {
            return response()->json(['error' => 'Payment record not found'], 404);
        }

        if ($payment->status === 'SUCCESS') {
            return response()->json(['status' => 'SUCCESS', 'payment' => $payment]);
        }

        // Check N-Genius directly
        try {
            $statusRes = $this->paymentService->checkOrderStatus($ref);
            $paymentState = $statusRes['status'];

            if (in_array($paymentState, ['CAPTURED', 'SETTLED', 'PAID'])) {
                $this->fulfillmentService->processPaymentSuccess($ref, $statusRes['data']);
                $payment->refresh();
                return response()->json(['status' => 'SUCCESS', 'payment' => $payment]);
            }

            return response()->json(['status' => $paymentState ?: $payment->status, 'payment' => $payment]);
        } catch (\Throwable $e) {
            return response()->json(['status' => $payment->status, 'payment' => $payment]);
        }
    }

    /**
     * GET /api/payments/by-client/{clientId}
     */
    public function paymentsByClient(Request $request, int $clientId)
    {
        try {
            $query = Payment::where('client_id', $clientId);
            $branchId = $request->query('branch_id') ?? $request->input('branch_id');
            if ($branchId) {
                $query->where('branch_id', (string) $branchId);
            }

            $payments = $query->orderByDesc('id')->get();

            return response()->json([
                'success' => true,
                'total' => $payments->count(),
                'data' => $payments,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
