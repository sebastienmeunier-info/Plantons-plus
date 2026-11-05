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

final class MyProposalController
{
    public function __construct(private readonly Application $app) {}

    public function show(Request $request): never
    {
        $user = $this->requireUser();
        $source = $this->source((string) $request->query('source'));
        $feature = $this->findOwnedFeature($source, (string) $request->query('id'), $user);
        Response::html($this->app->view()->render('proposal/show', [
            'application' => $this->app->config('app'),
            'csrfToken' => $this->auth()->csrfToken(),
            'user' => $user,
            'feature' => $feature,
            'source' => $source,
            'planting' => $this->app->config('planting'),
            'editable' => ($feature['properties']['status'] ?? 'a_valider') === 'a_valider',
            'notice' => $request->query('updated') === '1' ? 'Votre proposition a été mise à jour.' : null,
        ]));
    }

    public function update(Request $request): never
    {
        try {
            $auth = $this->auth();
            $user = $this->requireUser();
            if (!$auth->verifyCsrf((string) $request->input('csrf_token'))) { Response::html('Session expirée.', 403); }
            $source = $this->source((string) $request->input('source'));
            $feature = $this->findOwnedFeature($source, (string) $request->input('id'), $user);
            if (($feature['properties']['status'] ?? 'a_valider') !== 'a_valider') { Response::html('Cette proposition ne peut plus être modifiée.', 403); }
            $planting = $this->app->config('planting');
            $species = trim((string) $request->input('species'));
            if (!in_array($species, $planting['allowed_species'], true)) { throw new InvalidArgumentException('Essence invalide.'); }
            $comment = mb_substr(trim((string) $request->input('comment')), 0, 1000);
            $changes = ['species' => $species, 'comment' => $comment, 'updated_at' => date(DATE_ATOM)];
            $previousProperties = (array) ($feature['properties'] ?? []);
            $modifications = [];
            if ($species !== (string) ($previousProperties['species'] ?? '')) { $modifications[] = 'essence changée en « ' . $species . ' »'; }
            if ($source === 'donations') {
                if ($conditioning !== (string) ($previousProperties['conditioning'] ?? '')) { $modifications[] = 'conditionnement changé en « ' . (string) ($planting['tree_conditioning'][$conditioning]['label'] ?? $conditioning) . ' »'; }
                if ($treeSize !== (string) ($previousProperties['tree_size'] ?? '')) { $modifications[] = 'taille changée en « ' . (string) ($planting['tree_sizes'][$treeSize]['label'] ?? $treeSize) . ' »'; }
            } else {
                $objectiveLabel = static fn(string $key): string => (string) ($planting['objectives'][$key]['label'] ?? $key);
                $previousObjectives = array_values((array) ($previousProperties['objectives'] ?? []));
                foreach (array_diff($objectives, $previousObjectives) as $added) { $modifications[] = 'objectif « ' . $objectiveLabel((string) $added) . ' » sélectionné'; }
                foreach (array_diff($previousObjectives, $objectives) as $removed) { $modifications[] = 'objectif « ' . $objectiveLabel((string) $removed) . ' » désélectionné'; }
            }
            if ($modifications !== []) {
                $modifications[0] = mb_strtoupper(mb_substr($modifications[0], 0, 1), 'UTF-8') . mb_substr($modifications[0], 1);
            }
            if ($modifications !== [] || ($comment !== '' && $comment !== (string) ($previousProperties['comment'] ?? ''))) {
                $messages = array_values(array_filter((array) ($previousProperties['messages'] ?? []), 'is_array'));
                $now = date(DATE_ATOM);
                if ($modifications !== []) {
                    $messages[] = ['author' => 'contributeur', 'kind' => 'modification', 'text' => implode(' · ', $modifications), 'at' => $now];
                }
                if ($comment !== '' && $comment !== (string) ($previousProperties['comment'] ?? '')) {
                    $messages[] = ['author' => 'contributeur', 'text' => $comment, 'at' => $now];
                }
                $changes['messages'] = $messages;
            }
            if ($source === 'donations') {
                $conditioning = (string) $request->input('conditioning');
                $treeSize = (string) $request->input('tree_size');
                if (!isset($planting['tree_conditioning'][$conditioning], $planting['tree_sizes'][$treeSize])) { throw new InvalidArgumentException('Caractéristiques de l’arbre invalides.'); }
                $changes += ['conditioning' => $conditioning, 'tree_size' => $treeSize, 'objectives' => []];
            } else {
                $objectives = array_values(array_unique(array_filter((array) $request->input('objectives', []), 'is_string')));
                if ($objectives === [] || array_diff($objectives, array_keys($planting['objectives'])) !== [] || count($objectives) > $planting['max_objectives_per_proposal']) { throw new InvalidArgumentException('Objectifs invalides.'); }
                $changes['objectives'] = $objectives;
            }
            (new GeoJsonStore())->updateFeature($this->app->config('data_sources')[$source]['file'], (string) $feature['properties']['id'], $changes);
            header('Location: ' . $this->app->routeUrl('/ma-proposition?source=' . rawurlencode($source) . '&id=' . rawurlencode((string) $feature['properties']['id']) . '&updated=1'), true, 303);
            exit;
        } catch (InvalidArgumentException $exception) {
            Response::html(htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'), 422);
        }
    }

    private function requireUser(): array
    {
        $user = $this->auth()->currentUser();
        if ($user === null) { header('Location: ' . $this->app->routeUrl('/connexion'), true, 303); exit; }
        return $user;
    }

    private function source(string $source): string
    {
        if (!in_array($source, ['proposals', 'donations'], true)) { Response::html('Source invalide.', 404); }
        return $source;
    }

    private function findOwnedFeature(string $source, string $id, array $user): array
    {
        foreach ((new GeoJsonStore())->read($this->app->config('data_sources')[$source]['file'])['features'] as $feature) {
            if (($feature['properties']['id'] ?? '') === $id && mb_strtolower((string) ($feature['properties']['email'] ?? '')) === mb_strtolower((string) $user['email'])) { return $feature; }
        }
        Response::html('Proposition introuvable ou accès refusé.', 404);
    }

    private function auth(): AuthService
    {
        return new AuthService(new UserRepository($this->app->config('auth')['users_file']), $this->app->config('auth'));
    }
}
