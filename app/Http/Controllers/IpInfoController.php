<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Exception;

class IpInfoController extends Controller
{
    private $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36 Edg/119.0.0.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/120.0'
    ];

    public function getIpInfo(Request $request): JsonResponse
    {
        try {
            $ch = curl_init();
            $clientIp = $this->getClientIp($request); // "3.24.179.243";

            curl_setopt_array($ch, [
                CURLOPT_URL => "https://ipapi.co/{$clientIp}/json/",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP)'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                throw new \Exception("cURL error: " . $error);
            }

            if ($httpCode !== 200) {
                throw new \Exception("HTTP error: " . $httpCode);
            }

            $data = json_decode($response, true);

            if (!$data) {
                throw new \Exception("Invalid JSON response");
            }

//            $client   = $this->createAdvancedClient();
//            $response = $client->get('https://ipapi.co/json/', [
//                'headers' => $this->getRandomHeaders('http://ip-api.com'),
//                'query' => ['fields' => 'status,message,country,regionName,city,lat,lon,timezone,isp,query']
//            ]);
//            if ($response->getStatusCode() === 200) {
//                $body = $response->getBody()->getContents();
//                $data = json_decode($body, true);
//            }

            $formattedData = [
                'success' => true,
                'data'    => $data
            ];

            return response()->json($formattedData, 200);

        } catch (RequestException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch IP information',
                'message' => $e->getMessage()
            ], 500);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    private function createAdvancedClient(): Client
    {
        return new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
            'http_errors' => false,
            'curl' => [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TCP_NODELAY => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => $this->getRandomUserAgent(),
            ]
        ]);
    }

    private function getRandomUserAgent(): string
    {
        return $this->userAgents[array_rand($this->userAgents)];
    }

    private function getRandomHeaders(string $referer = ''): array
    {
        $headers = [
            'User-Agent'                => $this->getRandomUserAgent(),
            'Accept'                    => 'application/json, text/plain, */*',
            'Accept-Language'           => 'en-US,en;q=0.9',
            'Accept-Encoding'           => 'gzip, deflate, br',
            'Cache-Control'             => 'no-cache',
            'Pragma'                    => 'no-cache',
            'DNT'                       => '1',
            'Connection'                => 'keep-alive',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'none',
            'Sec-Fetch-User'            => '?1',
            'sec-ch-ua'                 => '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"Windows"'
        ];

        if (!empty($referer)) {
            $headers['Referer'] = $referer;
            $headers['Origin'] = $referer;
        }

        return $headers;
    }

    private function getClientIp(Request $request): ?string
    {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'HTTP_X_REAL_IP',           // Nginx proxy
            'REMOTE_ADDR'               // Standard
        ];

        foreach ($ipKeys as $key) {
            if ($request->server($key)) {
                $ip = $request->server($key);

                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Return first valid public IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $request->ip();
    }
}
