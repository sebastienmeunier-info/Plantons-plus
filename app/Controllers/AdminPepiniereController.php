<?php

declare(strict_types=1);

namespace Plantons\Controllers;

use InvalidArgumentException;
use Plantons\Core\Application;
use Plantons\Core\Request;
use Plantons\Core\Response;
use Plantons\Repositories\UserRepository;
use Plantons\Services\AuthService;
use Plantons\Services\GeoJsonStore;
use RuntimeException;

final class AdminPepiniereController
{
    private const SLOTS = ['P1', 'P2', 'P3', 'P4', 'P5'];

    public function __construct(private readonly Application $app) {}

    public function index(Request $request): never
    {
        $this->requireFeature();
        $auth = $this->auth();
        $this->guard($auth);
        header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=pepinieres'), true, 303);
        exit;
    }

    public function update(Request $request): never
    {
        $this->requireFeature();
        try {
            $auth = $this->auth();
            $administrator = $this->guard($auth);
            if (!$auth->verifyCsrf((string) $request->input('csrf_token'))) {
                Response::html('Session expirée.', 403);
            }
            $ids = (array) $request->input('id', []);
            $longitudes = (array) $request->input('longitude', []);
            $latitudes = (array) $request->input('latitude', []);
            $addresses = (array) $request->input('address', []);
            if (count($ids) !== count($longitudes) || count($ids) !== count($latitudes)) {
                throw new InvalidArgumentException('Données de pépinières invalides.');
            }
            $features = [];
            $seen = [];
            foreach ($ids as $index => $id) {
                $id = (string) $id;
                if (!in_array($id, self::SLOTS, true) || isset($seen[$id])) {
                    throw new InvalidArgumentException('Emplacement de pépinière invalide.');
                }
                $seen[$id] = true;
                $longitude = $longitudes[$index] ?? null;
                $latitude = $latitudes[$index] ?? null;
                if (!is_numeric($longitude) || !is_numeric($latitude)) {
                    throw new InvalidArgumentException('Coordonnées de pépinière invalides.');
                }
                $slot = array_search($id, self::SLOTS, true);
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => ['type' => 'Point', 'coordinates' => [(float) $longitude, (float) $latitude]],
                    'properties' => [
                        'id' => $id,
                        'label' => 'Pépinière ' . ((int) $slot + 1),
                        'address' => mb_substr(trim((string) ($addresses[$index] ?? '')), 0, 255),
                        'updated_at' => date(DATE_ATOM),
                        'updated_by' => $administrator['id'],
                    ],
                ];
            }
            if (count($features) > count(self::SLOTS)) {
                throw new InvalidArgumentException('Cinq pépinières maximum sont autorisées.');
            }
            (new GeoJsonStore())->replaceFeatures($this->app->config('data_sources')['pepinieres']['file'], $features);
            header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=pepinieres&pepinieres=enregistrees'), true, 303);
            exit;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            Response::html(htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'), 422);
        }
    }

    private function requireFeature(): never
    {
        if (($this->app->config('app')['features']['pepinieres'] ?? true) !== true) {
            Response::html('Cette fonctionnalité est désactivée.', 404);
        }
    }

    private function guard(AuthService $auth): array
    {
        $user = $auth->currentUser();
        if (!in_array($user['role'] ?? null, ['administrateur', 'super_administrateur'], true)) {
            Response::html('Accès réservé aux administrateurs.', 403);
        }
        return $user;
    }

    private function auth(): AuthService
    {
        return new AuthService(new UserRepository($this->app->config('auth')['users_file']), $this->app->config('auth'));
    }
}
