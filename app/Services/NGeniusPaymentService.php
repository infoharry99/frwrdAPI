<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NGeniusPaymentService
{
    protected string $baseUrl;
    protected string $outletId;
    protected string $authBasic;

    public function __construct()
    {
        $this->baseUrl = env('NGENIUS_BASE_URL', 'https://api-gateway.ngenius-payments.com');
        $this->outletId = env('OUTLET_ID', '381f8601-22f3-48dd-b883-af55c5e48ff0');
        $this->authBasic = env('API_KEY', 'OTY1NjRmYzctNjU5Ni00MDYzLWI5YjItZWYxMzAxZjI3ZDQyOjMwZTUzNTgwLWU3NWItNGE5My05NWQzLWZjZWY0Nzc2ZjY2Zg==');
    }

    /**
     * Get OAuth Access Token from N-Genius.
     */
    public function getAccessToken(): string
    {
        $url = rtrim($this->baseUrl, '/') . '/identity/auth/access-token';

        $response = Http::withHeaders([
            'accept' => 'application/vnd.ni-identity.v1+json',
            'authorization' => "Basic {$this->authBasic}",
            'content-type' => 'application/vnd.ni-identity.v1+json',
        ])->post($url);

        if ($response->failed()) {
            Log::error('N-Genius Token Request Failed: ' . $response->body());
            throw new \Exception('Failed to obtain N-Genius access token: ' . $response->body());
        }

        $data = $response->json();
        return $data['access_token'] ?? '';
    }

    /**
     * Create Order on N-Genius Gateway and record in payments table.
     */
    public function createOrder(
        float $amountAed,
        string $email,
        array $studentIds = [],
        ?int $clientId = null,
        array $appointIds = [],
        array $packageIds = [],
        ?string $branchId = null,
        $bookingPayload = null
    ): array {
        $token = $this->getAccessToken();
        $amountInFils = (int) round($amountAed * 100);

        $orderBody = [
            'action' => 'SALE',
            'amount' => [
                'currencyCode' => 'AED',
                'value' => $amountInFils > 0 ? $amountInFils : 100,
            ],
            'language' => 'en',
            'merchantOrderReference' => 'MOR-' . (int)(microtime(true) * 1000),
            'emailAddress' => $email ?: 'buyer@example.com',
            'merchantAttributes' => [
                'redirectUrl' => env('NGENIUS_REDIRECT_URL', 'https://api.frwrdtutors.com/api/success'),
                'cancelUrl' => env('NGENIUS_CANCEL_URL', 'https://api.frwrdtutors.com/api/cancel'),
                'failedUrl' => env('NGENIUS_FAILED_URL', 'https://api.frwrdtutors.com/api/failed'),
                'skipConfirmationPage' => true,
            ],
        ];

        $url = rtrim($this->baseUrl, '/') . "/transactions/outlets/{$this->outletId}/orders";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Content-Type' => 'application/vnd.ni-payment.v2+json',
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ])->post($url, $orderBody);

        if ($response->failed()) {
            Log::error('N-Genius Order Creation Failed: ' . $response->body());
            throw new \Exception('Create order failed: ' . $response->body());
        }

        $data = $response->json();
        $orderReference = $data['reference'] ?? null;
        $paymentHref = $data['_links']['payment']['href'] ?? null;
        $code = null;

        if ($paymentHref) {
            $parsedUrl = parse_url($paymentHref);
            if (isset($parsedUrl['query'])) {
                parse_str($parsedUrl['query'], $queryParams);
                $code = $queryParams['code'] ?? null;
            }
        }

        // Save into payments table
        Payment::create([
            'ref_no' => $orderReference,
            'student_ids' => $studentIds,
            'client_id' => $clientId,
            'client_email' => $email,
            'appoint_ids' => $appointIds,
            'package_ids' => $packageIds,
            'amount' => $amountAed,
            'status' => 'PENDING',
            'raw_response' => $data,
            'branch_id' => $branchId,
            'booking_payload' => $bookingPayload,
        ]);

        return [
            'orderReference' => $orderReference,
            'paymentCode' => $code,
            'paymentLink' => $paymentHref,
            'token' => $token,
        ];
    }

    /**
     * Check Order Status from N-Genius Gateway.
     */
    public function checkOrderStatus(string $ref): array
    {
        $token = $this->getAccessToken();
        $url = rtrim($this->baseUrl, '/') . "/transactions/outlets/{$this->outletId}/orders/{$ref}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/vnd.ni-payment.v2+json',
        ])->get($url);

        $data = $response->json() ?? [];
        $paymentState = $data['_embedded']['payment'][0]['state']
            ?? $data['payment']['state']
            ?? null;

        return [
            'status' => $paymentState,
            'data' => $data,
            'raw' => $response->body(),
        ];
    }
}
