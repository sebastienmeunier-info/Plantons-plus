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
use Plantons\Services\PhotoService;
use Plantons\Services\TerritoryService;
use RuntimeException;

final class ProposalController
{
    public function __construct(private readonly Application $app) {}

    public function create(Request $request): never
    {
        $this->createForSource($request, 'proposals');
    }

    public function createDonation(Request $request): never
    {
        $this->createForSource($request, 'donations');
    }

    private function createForSource(Request $request, string $source): never
    {
        try {
            if ((bool) ($this->app->config('auth')['inscription_obligatoire'] ?? false) && $this->auth()->currentUser() === null) {
                Response::json(['message' => 'Vous devez vous connecter avant d’envoyer une proposition.'], 403);
            }
            $this->verifyCsrf((string) $request->input('csrf_token'));
            $planting = $this->app->config('planting');
            $longitude = $this->number($request->input('longitude'), 'Longitude invalide.');
            $latitude = $this->number($request->input('latitude'), 'Latitude invalide.');
            $species = trim((string) $request->input('species'));
            $objectives = array_values(array_filter((array) $request->input('objectives', []), 'is_string'));
            $conditioning = trim((string) $request->input('conditioning'));
            $treeSize = trim((string) $request->input('tree_size'));
            $author = mb_substr(trim((string) $request->input('author')), 0, 80);
            $email = mb_substr(trim((string) $request->input('email')), 0, $this->app->config('security')['max_email_length']);
            $comment = mb_substr(trim((string) $request->input('comment')), 0, 1000);

            if (!in_array($species, $planting['allowed_species'], true)) {
                throw new InvalidArgumentException('Veuillez sélectionner une essence autorisée.');
            }
            if ($source === 'donations') {
                if (!array_key_exists($conditioning, $planting['tree_conditioning'])) {
                    throw new InvalidArgumentException('Veuillez sélectionner le conditionnement de l’arbre.');
                }
                if (!array_key_exists($treeSize, $planting['tree_sizes'])) {
                    throw new InvalidArgumentException('Veuillez sélectionner la taille de l’arbre.');
                }
                $objectives = [];
            } else {
                if ($objectives === [] || array_diff($objectives, array_keys($planting['objectives'])) !== []) {
                    throw new InvalidArgumentException('Veuillez sélectionner au moins un objectif valide.');
                }
                if (count(array_unique($objectives)) > $planting['max_objectives_per_proposal']) {
                    throw new InvalidArgumentException('Vous pouvez sélectionner au maximum trois objectifs.');
                }
            }
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Veuillez renseigner une adresse e-mail valide.');
            }
            $store = new GeoJsonStore();
            $sources = $this->app->config('data_sources');
            $territoryService = new TerritoryService();
            $territoryFile = trim((string) ($sources['territory']['file'] ?? ''));
            $territory = $territoryFile === '' ? null : $store->read($territoryFile);
            if ($territory !== null && ($territory['features'] ?? []) !== [] && !$territoryService->contains($territory, $longitude, $latitude)) {
                throw new InvalidArgumentException('Le point sélectionné est situé hors du territoire autorisé.');
            }

            $sectorsFile = trim((string) ($sources['sectors']['file'] ?? ''));
            $sectors = $sectorsFile === '' ? null : $store->read($sectorsFile);
            $photos = (new PhotoService())->store($request->files('photos'), $this->app->config('security'), $this->app->config('paths')['uploads']);
            $createdAt = date(DATE_ATOM);
            $feature = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$longitude, $latitude]],
                'properties' => [
                    'id' => 'PA-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)),
                    'status' => 'a_valider',
                    'created_at' => $createdAt,
                    'author' => $author,
                    'email' => $email === '' ? null : $email,
                    'notification_consent_at' => $email !== '' && (string) $request->input('notification_consent') === '1' ? date(DATE_ATOM) : null,
                    'species' => $species,
                    'objectives' => $objectives,
                    'conditioning' => $source === 'donations' ? $conditioning : null,
                    'tree_size' => $source === 'donations' ? $treeSize : null,
                    'comment' => $comment,
                    'messages' => $comment !== '' ? [['author' => 'contributeur', 'text' => $comment, 'at' => $createdAt]] : [],
                    'address' => mb_substr(trim((string) $request->input('address')), 0, 255),
                    'sector' => $sectors === null ? '' : $territoryService->municipality($sectors, $longitude, $latitude),
                    'photos' => $photos,
                ],
            ];
            $store->appendFeature($sources[$source]['file'], $feature);
            Response::json(['message' => 'Votre proposition a été enregistrée et sera examinée par la collectivité.', 'feature' => $feature], 201);
        } catch (InvalidArgumentException $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        } catch (RuntimeException $exception) {
            $this->app->logger()->error($exception->getMessage());
            Response::json(['message' => $exception->getMessage()], 500);
        }
    }

    private function verifyCsrf(string $token): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
        if ($token === '' || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
            throw new InvalidArgumentException('Votre session a expiré. Veuillez recharger la page.');
        }
    }

    private function auth(): AuthService
    {
        return new AuthService(new UserRepository($this->app->config('auth')['users_file']), $this->app->config('auth'));
    }

    private function number(mixed $value, string $message): float
    {
        if (!is_numeric($value)) { throw new InvalidArgumentException($message); }
        return (float) $value;
    }
}
