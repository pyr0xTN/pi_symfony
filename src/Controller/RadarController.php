<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class RadarController extends AbstractController
{
    #[Route('/api/radar/flights', name: 'api_radar_flights', methods: ['GET'])]
    public function flights(Request $request)
    {
        // Proxy to flight data provider. Prefer AirLabs if API key is provided.
        $bbox = $request->query->get('bbox'); // expected: minLat,maxLat,minLon,maxLon
        $count = (int) $request->query->get('count', 500);
        $count = max(1, min(500, $count));

        $apiKey = $_ENV['AIRLABS_API_KEY'] ?? $_SERVER['AIRLABS_API_KEY'] ?? null;
        $client = HttpClient::create();

        try {
            $flights = [];

            if ($apiKey) {
                // Query AirLabs flights endpoint using documented lat/lng fields.
                $url = 'https://airlabs.co/api/v9/flights';
                $query = [
                    'api_key' => $apiKey,
                    '_fields' => 'hex,flight_icao,flight_iata,lat,lng,dir,alt,speed,updated,flag,airline_iata,airline_icao',
                ];

                if ($bbox && is_string($bbox)) {
                    $parts = array_map('floatval', explode(',', $bbox));
                    if (count($parts) === 4) {
                        [$minLat, $maxLat, $minLon, $maxLon] = $parts;
                        $query['bbox'] = $minLat . ',' . $minLon . ',' . $maxLat . ',' . $maxLon;
                    }
                }

                $res = $client->request('GET', $url, [
                    'timeout' => 10,
                    'query' => $query,
                    'headers' => ['User-Agent' => 'RehletnaRadar/1.0']
                ]);

                if ($res->getStatusCode() === 200) {
                    $content = $res->getContent(false);
                    $data = json_decode($content, true);

                    // Try multiple likely keys for flights list
                    if (isset($data['response']) && is_array($data['response'])) {
                        $list = $data['response'];
                    } elseif (isset($data['flights']) && is_array($data['flights'])) {
                        $list = $data['flights'];
                    } elseif (isset($data['data']) && is_array($data['data'])) {
                        $list = $data['data'];
                    } elseif (is_array($data)) {
                        $list = $data;
                    } else {
                        $list = [];
                    }

                    foreach ($list as $s) {
                        if (!is_array($s)) {
                            continue;
                        }

                        // Prefer object keys from AirLabs' default response; fall back to array positions when needed.
                        $lat = $s['lat'] ?? $s['latitude'] ?? $s[3] ?? null;
                        $lon = $s['lng'] ?? $s['lon'] ?? $s['longitude'] ?? $s[4] ?? null;
                        $callsign = $s['flight_icao'] ?? $s['callsign'] ?? $s['call_sign'] ?? $s[1] ?? null;
                        $icao24 = $s['hex'] ?? $s['icao24'] ?? $s[0] ?? null;
                        $velocity = $s['speed'] ?? $s['velocity'] ?? $s[7] ?? null;
                        $track = $s['dir'] ?? $s['track'] ?? $s['true_track'] ?? $s[5] ?? null;
                        $alt = $s['alt'] ?? $s['altitude'] ?? $s['baro_altitude'] ?? $s[6] ?? null;

                        if ($lat === null || $lon === null) continue;

                        $fl = [
                            'icao24' => $icao24,
                            'callsign' => is_string($callsign) ? trim($callsign) : $callsign,
                            'origin_country' => $s['origin_country'] ?? null,
                            'time_position' => $s['updated'] ?? null,
                            'last_contact' => $s['updated'] ?? null,
                            'longitude' => $this->normalizeFloat($lon),
                            'latitude' => $this->normalizeFloat($lat),
                            'baro_altitude' => $this->normalizeFloat($alt),
                            'on_ground' => $s['on_ground'] ?? null,
                            'velocity' => $this->normalizeFloat($velocity),
                            'true_track' => $this->normalizeFloat($track),
                            'vertical_rate' => $this->normalizeFloat($s['vert_rate'] ?? null),
                        ];

                        // bbox filter
                        if ($bbox && is_string($bbox)) {
                            $parts = array_map('floatval', explode(',', $bbox));
                            if (count($parts) === 4) {
                                [$minLat, $maxLat, $minLon, $maxLon] = $parts;
                                $latv = $fl['latitude'];
                                $lonv = $fl['longitude'];
                                if ($latv < $minLat || $latv > $maxLat || $lonv < $minLon || $lonv > $maxLon) {
                                    continue;
                                }
                            }
                        }

                        $flights[] = $fl;
                        if (count($flights) >= $count) break;
                    }
                }
            }

            // Fallback to OpenSky if no API key or no data
            if (empty($flights)) {
                $url = 'https://opensky-network.org/api/states/all';
                $response = $client->request('GET', $url, [
                    'timeout' => 8,
                    'headers' => [ 'User-Agent' => 'RehletnaRadar/1.0' ],
                ]);

                if (200 === $response->getStatusCode()) {
                    $content = $response->getContent(false);
                    $data = json_decode($content, true);
                    $states = isset($data['states']) && is_array($data['states']) ? $data['states'] : [];
                    foreach ($states as $s) {
                        $fl = [
                            'icao24' => $s[0] ?? null,
                            'callsign' => isset($s[1]) ? trim($s[1]) : null,
                            'origin_country' => $s[2] ?? null,
                            'time_position' => $s[3] ?? null,
                            'last_contact' => $s[4] ?? null,
                            'longitude' => $this->normalizeFloat($s[5] ?? null),
                            'latitude' => $this->normalizeFloat($s[6] ?? null),
                            'baro_altitude' => $this->normalizeFloat($s[7] ?? null),
                            'on_ground' => $s[8] ?? null,
                            'velocity' => $this->normalizeFloat($s[9] ?? null),
                            'true_track' => $this->normalizeFloat($s[10] ?? null),
                            'vertical_rate' => $this->normalizeFloat($s[11] ?? null),
                        ];

                        if ($bbox && is_string($bbox)) {
                            $parts = array_map('floatval', explode(',', $bbox));
                            if (count($parts) === 4) {
                                [$minLat, $maxLat, $minLon, $maxLon] = $parts;
                                $lat = $fl['latitude'];
                                $lon = $fl['longitude'];
                                if ($lat === null || $lon === null) {
                                    continue;
                                }
                                if ($lat < $minLat || $lat > $maxLat || $lon < $minLon || $lon > $maxLon) {
                                    continue;
                                }
                            }
                        }

                        $flights[] = $fl;
                        if (count($flights) >= $count) {
                            break;
                        }
                    }
                }
            }

            // Ensure stable count for frontend: top up with demo flights when provider has fewer rows.
            if (count($flights) < $count) {
                $missing = $count - count($flights);
                $demoFlights = $this->getDemoFlights($bbox, $missing);
                foreach ($demoFlights as $demo) {
                    $flights[] = $demo;
                    if (count($flights) >= $count) {
                        break;
                    }
                }
            }

            // Trim to requested size when provider returned more than requested.
            if (count($flights) > $count) {
                $flights = array_slice($flights, 0, $count);
            }

            return new JsonResponse(['flights' => $flights]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'error' => 'Radar proxy error',
                'message' => $e->getMessage(),
                'type' => get_class($e),
            ], 500);
        }
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $number = (float) $value;
            return is_finite($number) ? $number : null;
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDemoFlights(?string $bbox = null, int $count = 500): array
    {
        $examples = [
            ['icao24' => 'demoA', 'callsign' => 'REHLTNA'],
            ['icao24' => 'demoB', 'callsign' => 'REHLTNB'],
            ['icao24' => 'demoC', 'callsign' => 'REHLTNC'],
            ['icao24' => 'demoD', 'callsign' => 'REHLTND'],
            ['icao24' => 'demoE', 'callsign' => 'REHLTNE'],
            ['icao24' => 'demoF', 'callsign' => 'REHLTNF'],
            ['icao24' => 'demoG', 'callsign' => 'REHLTNG'],
            ['icao24' => 'demoH', 'callsign' => 'REHLTNH'],
            ['icao24' => 'demoI', 'callsign' => 'REHLTNI'],
            ['icao24' => 'demoJ', 'callsign' => 'REHLTNJ'],
        ];

        $count = max(1, min(500, $count));
        $results = [];
        $now = microtime(true);

        $hasBBox = false;
        if ($bbox && is_string($bbox)) {
            $parts = array_map('floatval', explode(',', $bbox));
            if (count($parts) === 4) {
                [$minLat, $maxLat, $minLon, $maxLon] = $parts;
                $hasBBox = true;
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $ex = $examples[$i % count($examples)];

            // Smooth deterministic movement based on time and index (no random jumping).
            $phase = $i * 0.27;
            $speed = 0.18 + (($i % 11) * 0.008);
            $angle = $now * $speed + $phase;

            if ($hasBBox) {
                $centerLat = ($minLat + $maxLat) / 2.0;
                $centerLon = ($minLon + $maxLon) / 2.0;
                $spanLat = max(0.0001, ($maxLat - $minLat));
                $spanLon = max(0.0001, ($maxLon - $minLon));
                $ampLat = $spanLat * 0.42;
                $ampLon = $spanLon * 0.42;

                $lat = $centerLat + sin($angle) * $ampLat;
                $lon = $centerLon + cos($angle * 1.13) * $ampLon;

                $lat = max($minLat, min($maxLat, $lat));
                $lon = max($minLon, min($maxLon, $lon));
            } else {
                $lat = sin($angle) * 70.0;
                $lon = cos($angle * 1.07) * 150.0;
            }

            $heading = fmod(rad2deg($angle) + 360.0, 360.0);
            $velocity = 210 + (($i * 17) % 360);
            $altitude = 3000 + (($i * 137) % 34000);
            $lastContact = time();

            $results[] = [
                'icao24' => $ex['icao24'] . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'callsign' => $ex['callsign'] . $i,
                'longitude' => $lon,
                'latitude' => $lat,
                'true_track' => (int) round($heading),
                'velocity' => $velocity,
                'baro_altitude' => $altitude,
                'last_contact' => $lastContact,
                'time_position' => $lastContact,
            ];
        }

        return $results;
    }
}
