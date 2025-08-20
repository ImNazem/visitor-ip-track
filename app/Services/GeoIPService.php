<?php

declare(strict_types=1);

namespace App\Services;

use GeoIp2\Database\Reader;

use Exception;

class GeoIPService
{
    private $cityDbReader;
//    private $countryReader;
    private bool $isInitialized = false;

    public function __construct()
    {
       // $this->initializeReaders();
    }

    /*private function initializeReaders()
    {
        try {
            $geoipPath = base_path(env('GEOIP_DATABASE_PATH', 'database/geoip'));
            $cityDbPath = $geoipPath . '/' . env('GEOIP_CITY_DB', 'GeoLite2-City.mmdb');
            $countryDbPath = $geoipPath . '/' . env('GEOIP_COUNTRY_DB', 'GeoLite2-Country.mmdb');

            if (file_exists($cityDbPath)) {
                $this->cityReader = new Reader($cityDbPath);
                $this->isInitialized = true;
            } elseif (file_exists($countryDbPath)) {
                $this->countryReader = new Reader($countryDbPath);
                $this->isInitialized = true;
            }
        } catch (Exception $e) {
            error_log("GeoIP initialization failed: " . $e->getMessage());
        }
    }

    public function getDatabaseInfo()
    {
        $info = [
            'city_db_available' => $this->cityReader !== null,
            'country_db_available' => $this->countryReader !== null,
            'initialized' => $this->isInitialized
        ];

        if ($this->cityReader) {
            $metadata = $this->cityReader->metadata();
            $info['city_db'] = [
                'build_date' => $metadata->buildEpoch,
                'description' => $metadata->description,
                'database_type' => $metadata->databaseType
            ];
        }

        if ($this->countryReader) {
            $metadata = $this->countryReader->metadata();
            $info['country_db'] = [
                'build_date' => $metadata->buildEpoch,
                'description' => $metadata->description,
                'database_type' => $metadata->databaseType
            ];
        }

        return $info;
    }

    public function __destruct()
    {
        if ($this->cityReader) {
            $this->cityReader->close();
        }
        if ($this->countryReader) {
            $this->countryReader->close();
        }
    }
    */

    public function getLocationData(string $ip)
    {
        // Validate IP address
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $this->getDefaultLocation($ip);
        }

        if (!$this->isInitialized) {
            return $this->getDefaultLocation($ip, 'GeoIP database not available');
        }

        try {
            // Try city database first (more detailed)
            if ($this->cityReader) {
                return $this->getCityLocation($ip);
            }

            // Fallback to country database
            if ($this->countryReader) {
                return $this->getCountryLocation($ip);
            }

        } catch (Exception $e) {
            error_log("GeoIP lookup failed for IP {$ip}: " . $e->getMessage());
        }

        return $this->getDefaultLocation($ip, 'Lookup failed');
    }


    private function getCityLocation($ip)
    {
        $record = $this->cityReader->city($ip);

        return [
            'ip' => $ip,
            'country' => $record->country->name ?? 'Unknown',
            'country_code' => $record->country->isoCode ?? '',
            'region' => $record->mostSpecificSubdivision->name ?? 'Unknown',
            'region_code' => $record->mostSpecificSubdivision->isoCode ?? '',
            'city' => $record->city->name ?? 'Unknown',
            'postal_code' => $record->postal->code ?? '',
            'latitude' => $record->location->latitude ?? '',
            'longitude' => $record->location->longitude ?? '',
            'timezone' => $record->location->timeZone ?? 'Unknown',
            'accuracy_radius' => $record->location->accuracyRadius ?? '',
            'metro_code' => $record->location->metroCode ?? '',
            'continent' => $record->continent->name ?? 'Unknown',
            'continent_code' => $record->continent->code ?? '',
            'is_eu' => $record->country->isInEuropeanUnion ?? false,
            'source' => 'MaxMind GeoLite2 City',
            'database_type' => 'city'
        ];
    }

    private function getCountryLocation($ip)
    {
        $record = $this->countryReader->country($ip);

        return [
            'ip' => $ip,
            'country' => $record->country->name ?? 'Unknown',
            'country_code' => $record->country->isoCode ?? '',
            'region' => 'Unknown',
            'region_code' => '',
            'city' => 'Unknown',
            'postal_code' => '',
            'latitude' => '',
            'longitude' => '',
            'timezone' => 'Unknown',
            'accuracy_radius' => '',
            'metro_code' => '',
            'continent' => $record->continent->name ?? 'Unknown',
            'continent_code' => $record->continent->code ?? '',
            'is_eu' => $record->country->isInEuropeanUnion ?? false,
            'source' => 'MaxMind GeoLite2 Country',
            'database_type' => 'country'
        ];
    }

    private function getDefaultLocation($ip, $reason = 'Private/Local IP')
    {
        // For local/private IPs, try to determine basic info
        $defaultData = [
            'ip'              => $ip,
            'country'         => 'Unknown',
            'country_code'    => '',
            'region'          => 'Unknown',
            'region_code'     => '',
            'city'            => 'Unknown',
            'postal_code'     => '',
            'latitude'        => '',
            'longitude'       => '',
            'timezone'        => date_default_timezone_get(),
            'accuracy_radius' => '',
            'metro_code'      => '',
            'continent'       => 'Unknown',
            'continent_code'  => '',
            'is_eu'           => false,
            'source'          => 'Default (' . $reason . ')',
            'database_type'   => 'none'
        ];


        if ($this->isLocalIP($ip)) {
            $defaultData['country'] = 'Local Network';
            $defaultData['city']    = 'Local Environment';
            $defaultData['source']  = 'Local IP Detection';
        }

        return $defaultData;
    }

    private function isLocalIP($ip)
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
