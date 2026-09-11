<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TutorCruncherService
{
    protected string $baseUrl = 'https://app.tutorcruncher.com/api';

    /**
     * Resolve API key for a specific branch and action.
     */
    public function getApiKey(?string $branchId = null, string $action = ''): string
    {
        if ($branchId && $action) {
            $record = DB::table('branch_api_key')
                ->where('branch_id', $branchId)
                ->where('action', $action)
                ->value('api_key');

            if ($record) {
                return trim($record);
            }
        }

        // Fallback to default API Key in config/env
        return trim(config('services.tutorcruncher.api_key', env('API_KEY', '')));
    }

    /**
     * Perform GET request to TutorCruncher API with caching & 429 retry backoff.
     */
    public function get(string $endpoint, array $query = [], ?string $branchId = null, string $action = ''): array
    {
        $cacheKey = 'tc_get_' . md5($endpoint . '_' . json_encode($query) . '_' . ($branchId ?? '') . '_' . $action);

        // Cache single item lookups like /services/{id} or /contractors/{id} for 60 seconds
        if (str_contains($endpoint, '/services/') || str_contains($endpoint, '/contractors/')) {
            return Cache::remember($cacheKey, 60, function () use ($endpoint, $query, $branchId, $action) {
                return $this->executeGetRequest($endpoint, $query, $branchId, $action);
            });
        }

        return $this->executeGetRequest($endpoint, $query, $branchId, $action);
    }

    protected function executeGetRequest(string $endpoint, array $query = [], ?string $branchId = null, string $action = ''): array
    {
        $apiKey = $this->getApiKey($branchId, $action);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $response = Http::retry(3, 2000, function (\Exception $exception) {
            return $exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 429;
        })->withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Accept' => 'application/json',
        ])->get($url, $query);

        if ($response->failed()) {
            Log::error("TutorCruncher GET failed [{$url}]: " . $response->body());
            return ['error' => $response->body(), 'status' => $response->status()];
        }

        return $response->json() ?? [];
    }

    /**
     * Perform POST request to TutorCruncher API with 429 retry backoff.
     */
    public function post(string $endpoint, array $data = [], ?string $branchId = null, string $action = ''): array
    {
        $apiKey = $this->getApiKey($branchId, $action);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $response = Http::retry(3, 2000, function (\Exception $exception) {
            return $exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 429;
        })->withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, $data);

        if ($response->failed()) {
            Log::error("TutorCruncher POST failed [{$url}]: " . $response->body());
            return ['error' => $response->body(), 'status' => $response->status()];
        }

        return $response->json() ?? [];
    }

    /**
     * Perform PUT/PATCH request to TutorCruncher API with 429 retry backoff.
     */
    public function put(string $endpoint, array $data = [], ?string $branchId = null, string $action = ''): array
    {
        $apiKey = $this->getApiKey($branchId, $action);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');

        $response = Http::retry(3, 2000, function (\Exception $exception) {
            return $exception instanceof \Illuminate\Http\Client\RequestException && $exception->response->status() === 429;
        })->withHeaders([
            'Authorization' => "Token {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->put($url, $data);

        if ($response->failed()) {
            Log::error("TutorCruncher PUT failed [{$url}]: " . $response->body());
            return ['error' => $response->body(), 'status' => $response->status()];
        }

        return $response->json() ?? [];
    }
}
