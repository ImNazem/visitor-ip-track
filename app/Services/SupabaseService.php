<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class SupabaseService
{
    protected string $url;
    protected string $key;
    protected Client $http;

    public function __construct()
    {
        $this->url  = rtrim(env('SUPABASE_URL'), '/');
        $this->key  = env('SUPABASE_SERVICE_KEY');

        $this->http = new Client([
            'base_uri'    => $this->url,
            'http_errors' => false, // don't throw on 4xx/5xx
            'timeout'     => 1000,
        ]);
    }

    protected function headers(array $extra = []): array
    {
        return array_merge([
                               'apikey'        => $this->key,
                               'Authorization' => 'Bearer ' . $this->key,
                               'Content-Type'  => 'application/json',
                           ], $extra);
    }

    /** INSERT (or UPSERT): POST /rest/v1/{table} */
    public function insert(string $table, array $rows, array $options = [])
    {
        try {
            // For upsert: add ['Prefer' => 'return=representation,resolution=merge-duplicates'] and query ['on_conflict' => 'unique_col']
            $headers = $this->headers(['Prefer' => $options['prefer'] ?? 'return=representation']);
            $query   = $options['query'] ?? [];

            $response = $this->http->post("/rest/v1/{$table}", [
                'headers' => $headers,
                'query'   => $query,
                'body'    => json_encode($rows),
//                'json'    => $rows,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if ($response->getStatusCode() != 201) {
                Log::error("Error code: " . $response->getStatusCode(),  $data);
            }

            return [
                'success' => true,
                'data' => json_decode($response->getBody()->getContents(), true),
                'status_code' => $response->getStatusCode()
            ];
        } catch (RequestException $e) {
            Log::error('Supabase insert error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => $e->getResponse() ? $e->getResponse()->getStatusCode() : 500
            ];
        }
    }

}
