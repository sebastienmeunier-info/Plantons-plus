<?php

declare(strict_types=1);

namespace Plantons\Controllers;

use Plantons\Core\Application;
use Plantons\Core\Request;
use Plantons\Core\Response;
use Plantons\Services\GeoJsonStore;

final class GeoJsonController
{
    public function __construct(private readonly Application $app) {}

    public function territory(Request $request): never { $this->respond('territory'); }
    public function sectors(Request $request): never { $this->respond('sectors'); }
    public function trees(Request $request): never { $this->respondPublicProposals('trees'); }
    public function proposals(Request $request): never { $this->respondPublicProposals('proposals'); }
    public function donations(Request $request): never { $this->respondPublicProposals('donations'); }

    private function featureEnabled(string $feature): bool
    {
        return ($this->app->config('app')['features'][$feature] ?? true) === true;
    }

    private function respond(string $source): never
    {
        $config = (array) ($this->app->config('data_sources')[$source] ?? []);
        $file = trim((string) ($config['file'] ?? ''));
        Response::json($file === '' ? ['type' => 'FeatureCollection', 'features' => []] : (new GeoJsonStore())->read($file));
    }

    /**
     * Never expose contributors' contact details, comments, photo paths or
     * precise positions through the public map endpoints.
     */
    private function respondPublicProposals(string $source): never
    {
        if (!$this->featureEnabled($source)) {
            Response::json(['type' => 'FeatureCollection', 'features' => []]);
        }
        $config = $this->app->config('data_sources')[$source];
        $privacy = $this->app->config('privacy');
        $precision = max(0, min(6, (int) ($privacy['public_coordinate_precision'] ?? 4)));
        $allowedProperties = (array) ($privacy['public_proposal_properties'] ?? []);
        $features = [];

        foreach ((new GeoJsonStore())->read($config['file'])['features'] as $feature) {
            $coordinates = $feature['geometry']['coordinates'] ?? null;
            if (!is_array($coordinates) || count($coordinates) < 2 || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
                continue;
            }
            $properties = [];
            foreach ($allowedProperties as $key) {
                if (array_key_exists($key, $feature['properties'] ?? [])) {
                    $properties[$key] = $feature['properties'][$key];
                }
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $coordinates[0], $precision), round((float) $coordinates[1], $precision)]],
                'properties' => $properties,
            ];
        }

        Response::json(['type' => 'FeatureCollection', 'features' => $features]);
    }
}
