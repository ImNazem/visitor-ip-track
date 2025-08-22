<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SupabaseService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Exception\RequestException;

use Exception;
use Illuminate\Support\Facades\Log;

class IpInfoController extends Controller
{
    private string $table = 'portfolio_visitor_ip_tracker';

    public function __construct(protected SupabaseService $supabaseService)
    {}

    public function getIpInfo(Request $request): JsonResponse
    {
//        $testData = [
//                "ip" => "103.121.216.120",
//                "network" => "103.121.216.0/23",
//                "version" => "IPv4",
//                "city" => "Dhaka",
//                "region" => "Dhaka Division",
//                "region_code" => "C",
//                "country" => "BD",
//                "country_name" => "Bangladesh",
                //                                        "country_code" => "BD",
                //                                        "country_code_iso3" => "BGD",
                //                                        "country_capital" => "Dhaka",
                //                                        "country_tld" => ".bd",
                //                                        "continent_code" => "AS",
                //                                        "in_eu" => false,
//                "postal" => "1204",
                //                                        "latitude" => 23.7004,
                //                                        "longitude" => 90.4287,
//                "timezone" => "Asia/Dhaka",
                //                                        "utc_offset" => "+0600",
                //                                        "country_calling_code" => "+880",
                //                                        "currency" => "BDT",
                //                                        "currency_name" => "Taka",
                //                                        "languages" => "bn-BD,en",
                //                                        "country_area" => 144000,
                //                                        "country_population" => 161356039,
//                "asn" => "AS134732",
//                "org" => "Dot Internet",
//        ];
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
                throw new Exception("cURL error: " . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception("HTTP error: " . $httpCode);
            }

            $data = json_decode($response, true);

            if (!$data) {
                throw new Exception("Invalid JSON response");
            }

            $formattedData = [
                'success' => true,
                'data'    => $data
            ];

            $insertData = [
                "ip"           => $data['ip'] ?? $clientIp,
                "network"      =>  $data['network'] ?? '',
                "version"      =>  $data['version'] ?? '',
                "city"         => $data['version'] ?? '',
                "region"       => $data['region'] ?? '',
                "region_code"  => $data['region_code'] ?? '',
                "country"      => $data['country'] ?? '',
                "country_name" => $data['country_name'] ?? '',
                "postal"       => $data['postal'] ?? '',
                "timezone"     => $data['timezone'] ?? '',
                "asn"          => $data['asn'] ?? '',
                "org"          => $data['org'] ?? '',
            ];

            $res = $this->supabaseService->insert('table', $insertData);
            $formattedData['supa'] = $res;
            return response()->json($formattedData);

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
