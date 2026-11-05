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
use Plantons\Services\NotificationService;
use Plantons\Services\TerritoryService;
use RuntimeException;

final class AdminProposalController
{
    public function __construct(private readonly Application $app) {}

    public function index(Request $request): never
    {
        $auth = $this->auth();
        $this->guard($auth);
        header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=plantations'), true, 303);
        exit;
    }

    public function update(Request $request): never
    {
        $this->updateForSource($request, 'proposals', 'plantations');
    }

    public function updateDonation(Request $request): never
    {
        $this->updateForSource($request, 'donations', 'dons');
    }

    private function updateForSource(Request $request, string $source, string $tab): never
    {
        try {
            $auth = $this->auth();
            $current = $this->guard($auth);
            if (!$auth->verifyCsrf((string) $request->input('csrf_token'))) {
                Response::html('Session expirée.', 403);
            }
            $status = (string) $request->input('status');
            $statuses = $this->app->config('proposals')['status_labels'];
            if ($status === 'supprimer') {
                if ($current['role'] !== 'super_administrateur') {
                    Response::html('Suppression réservée au super-admin.', 403);
                }
                $store = new GeoJsonStore();
                $sourceFile = $this->app->config('data_sources')[$source]['file'];
                $deleted = $store->deleteFeature($sourceFile, (string) $request->input('id'));
                $this->notifyAdministrativeAction($deleted, $source, 'proposal_deleted', $current, mb_substr(trim((string) $request->input('admin_comment')), 0, 2000));
                $this->deletePhotos((array) ($deleted['properties']['photos'] ?? []));
                header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=' . $tab), true, 303);
                exit;
            }
            if (!array_key_exists($status, $statuses)) {
                throw new InvalidArgumentException('Statut invalide.');
            }
            $longitude = $this->coordinate($request->input('longitude'));
            $latitude = $this->coordinate($request->input('latitude'));
            $planting = $this->app->config('planting');
            $species = trim((string) $request->input('species'));
            $objectives = array_values(array_unique(array_filter((array) $request->input('objectives', []), 'is_string')));
            if (!in_array($species, $planting['allowed_species'], true)) { throw new InvalidArgumentException('Essence invalide.'); }
            $adminComment = mb_substr(trim((string) $request->input('admin_comment')), 0, 2000);
            $changes = ['status' => $status, 'species' => $species, 'admin_comment' => $adminComment];
            if ($source === 'donations') {
                $conditioning = (string) $request->input('conditioning');
                $treeSize = (string) $request->input('tree_size');
                if (!array_key_exists($conditioning, $planting['tree_conditioning'])) { throw new InvalidArgumentException('Conditionnement invalide.'); }
                if (!array_key_exists($treeSize, $planting['tree_sizes'])) { throw new InvalidArgumentException('Taille invalide.'); }
                $changes += ['conditioning' => $conditioning, 'tree_size' => $treeSize, 'objectives' => []];
            } else {
                if ($objectives === [] || array_diff($objectives, array_keys($planting['objectives'])) !== []) { throw new InvalidArgumentException('Objectifs invalides.'); }
                if (count($objectives) > $planting['max_objectives_per_proposal']) { throw new InvalidArgumentException('Trois objectifs maximum sont autorisés.'); }
                $changes['objectives'] = $objectives;
            }
            $store = new GeoJsonStore();
            $sources = $this->app->config('data_sources');
            $proposalId = (string) $request->input('id');
            $previous = $this->featureById($store->read($sources[$source]['file']), $proposalId);
            if ($previous === null) {
                throw new RuntimeException('Proposition introuvable.');
            }
            $messages = array_values(array_filter((array) ($previous['properties']['messages'] ?? []), 'is_array'));
            if ($adminComment !== '') {
                $messages[] = [
                    'author' => 'admin',
                    'text' => $adminComment,
                    'at' => date(DATE_ATOM),
                    'by' => $current['id'],
                    'by_name' => trim((string) ($current['first_name'] ?? '') . ' ' . (string) ($current['last_name'] ?? '')) ?: 'Administration',
                ];
            }
            $changes['messages'] = $messages;
            $territoryService = new TerritoryService();
            $territoryFile = trim((string) ($sources['territory']['file'] ?? ''));
            $territory = $territoryFile === '' ? null : $store->read($territoryFile);
            if ($territory !== null && ($territory['features'] ?? []) !== [] && !$territoryService->contains($territory, $longitude, $latitude)) {
                throw new InvalidArgumentException('La localisation doit rester dans le territoire autorisé.');
            }
            $sectorsFile = trim((string) ($sources['sectors']['file'] ?? ''));
            $sectors = $sectorsFile === '' ? null : $store->read($sectorsFile);
            $updateProperties = $changes + [
                'sector' => $sectors === null ? '' : $territoryService->municipality($sectors, $longitude, $latitude),
                'updated_at' => date(DATE_ATOM),
                'updated_by' => $current['id'],
            ];
            $store->updateFeature($sources[$source]['file'], $proposalId, $updateProperties, [$longitude, $latitude]);
            $updated = $previous;
            $updated['properties'] = array_replace((array) ($previous['properties'] ?? []), $updateProperties);
            $updated['geometry'] = ['type' => 'Point', 'coordinates' => [$longitude, $latitude]];
            $this->notifyAdministrativeAction($updated, $source, 'proposal_administrative_update', $current, $adminComment);
            header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=' . $tab), true, 303);
            exit;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            Response::html(htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'), 422);
        }
    }

    private function coordinate(mixed $value): float
    {
        if (!is_numeric($value)) { throw new InvalidArgumentException('Coordonnées invalides.'); }
        return (float) $value;
    }

    private function featureById(array $collection, string $id): ?array
    {
        foreach ($collection['features'] ?? [] as $feature) {
            if (($feature['properties']['id'] ?? null) === $id) {
                return $feature;
            }
        }
        return null;
    }

    private function notifyAdministrativeAction(array $proposal, string $source, string $event, array $administrator, string $adminComment): void
    {
        $notifications = $this->app->config('notifications');
        $properties = (array) ($proposal['properties'] ?? []);
        $recipient = (string) ($properties['email'] ?? '');
        if (($notifications['status_changes'] ?? false) !== true || empty($properties['notification_consent_at']) || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }
        $user = $this->repository()->findByEmail($recipient);
        $coordinates = (array) ($proposal['geometry']['coordinates'] ?? [null, null]);
        $planting = $this->app->config('planting');
        $objectives = $source === 'donations'
            ? implode(' · ', array_filter([
                $planting['tree_conditioning'][$properties['conditioning'] ?? '']['label'] ?? '',
                $planting['tree_sizes'][$properties['tree_size'] ?? '']['label'] ?? '',
            ]))
            : implode(', ', array_map(static fn(string $objective): string => (string) ($planting['objectives'][$objective]['label'] ?? $objective), (array) ($properties['objectives'] ?? [])));
        $status = (string) ($this->app->config('proposals')['status_labels'][$properties['status'] ?? ''] ?? ($properties['status'] ?? 'Proposée'));
        $proposalUrl = $this->app->absoluteRouteUrl('/ma-proposition?source=' . rawurlencode($source) . '&id=' . rawurlencode((string) ($properties['id'] ?? '')));
        $variables = [
            'project_name' => (string) $this->app->config('app')['name'],
            'first_name' => (string) ($user['first_name'] ?? $properties['author'] ?? ''),
            'last_name' => (string) ($user['last_name'] ?? ''),
            'proposal_id' => (string) ($properties['id'] ?? ''),
            'status' => $status,
            'species' => (string) ($properties['species'] ?? ''),
            'objectives' => $objectives !== '' ? $objectives : 'Non renseigné',
            'address' => (string) ($properties['address'] ?? 'Non renseignée'),
            'latitude' => isset($coordinates[1]) ? number_format((float) $coordinates[1], 6, '.', '') : 'Non renseignée',
            'longitude' => isset($coordinates[0]) ? number_format((float) $coordinates[0], 6, '.', '') : 'Non renseignée',
            'sector' => (string) ($properties['sector'] ?? $properties['delegated_municipality'] ?? 'Non renseigné'),
            'comment' => (string) ($properties['comment'] ?? 'Aucun commentaire.'),
            'admin_comment' => $adminComment !== '' ? $adminComment : 'Aucun commentaire.',
            'administrator_name' => trim((string) ($administrator['first_name'] ?? '') . ' ' . (string) ($administrator['last_name'] ?? '')) ?: 'Administration',
            'administrator_email' => (string) ($administrator['email'] ?? ''),
            'proposal_url' => $proposalUrl,
        ];
        $notifier = new NotificationService($this->app->config('smtp'), $notifications, $this->app->logger());
        $notifier->send($event, $recipient, $variables);
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

    private function repository(): UserRepository
    {
        return new UserRepository($this->app->config('auth')['users_file']);
    }

    /** @param array<int,mixed> $photos */
    private function deletePhotos(array $photos): void
    {
        $uploads = realpath($this->app->config('paths')['uploads']);
        if ($uploads === false) {
            return;
        }
        foreach ($photos as $path) {
            if (!is_string($path) || preg_match('#^uploads/photos/\\d{4}/[a-f0-9]{32}\\.webp$#', $path) !== 1) {
                continue;
            }
            $file = $this->app->config('paths')['root'] . '/' . $path;
            $resolved = realpath($file);
            if ($resolved !== false && str_starts_with($resolved, $uploads . DIRECTORY_SEPARATOR)) {
                unlink($resolved);
            }
        }
    }
}
