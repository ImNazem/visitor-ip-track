<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GeoIPService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    public function __construct(protected GeoIPService $geoIPService)
    {}

    public function getIpInfo(Request $request): JsonResponse
    {
        $ip = $this->getClientIp($request);
        $locationData = $this->geoIPService->getLocationData($ip);

        return response()->json([
            'success'   => true,
            'data'      => $locationData,
            'timestamp' => now()->toISOString(),
            'message'   => 'IP information retrieved successfully using offline GeoIP database'
        ]);
    }

    private function getClientIp(Request $request): string
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
