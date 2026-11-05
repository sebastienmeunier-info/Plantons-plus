<?php

declare(strict_types=1);

namespace Plantons\Controllers;

use Plantons\Core\Application;
use Plantons\Core\Request;
use Plantons\Core\Response;
use Plantons\Services\GeoJsonStore;
use Plantons\Services\AuthService;
use Plantons\Repositories\UserRepository;

final class HomeController
{
    public function __construct(private readonly Application $app) {}

    public function index(Request $request): never
    {
        $this->renderProposalPage('Envie d\'un arbre', false, 'proposals', $this->app->routeUrl('/api/propositions'));
    }

    public function treeProposal(Request $request): never
    {
        $this->renderProposalPage('Donner un arbre', true, 'donations', $this->app->routeUrl('/api/dons'));
    }

    public function treeCensus(Request $request): never
    {
        $this->renderProposalPage('Recenser un arbre', false, 'trees', $this->app->routeUrl('/api/arbres'), true);
    }

    private function renderProposalPage(string $pageTitle, bool $treeProposal, string $source, string $submissionUrl, bool $isCensus = false): never
    {
        $auth = new AuthService(new UserRepository($this->app->config('auth')['users_file']), $this->app->config('auth'));
        $user = $auth->currentUser();
        if ((bool) ($this->app->config('auth')['inscription_obligatoire'] ?? false) && $user === null) {
            header('Location: ' . $this->app->routeUrl('/connexion'), true, 303);
            exit;
        }
        $planting = $this->app->config('planting');
        $dataSources = $this->app->config('data_sources');
        $proposalFeatures = (new GeoJsonStore())->read($dataSources[$source]['file'])['features'];
        $statistics = ['proposed' => 0, 'validated' => 0, 'planted' => 0];
        foreach ($proposalFeatures as $proposal) {
            $status = $proposal['properties']['status'] ?? 'a_valider';
            if ($status === 'validee') { $statistics['validated']++; }
            elseif (in_array($status, ['arbre_plante', 'realisee'], true)) { $statistics['planted']++; }
            else { $statistics['proposed']++; }
        }

        Response::html($this->app->view()->render('home', [
            'application' => $this->app->config('app'),
            'territory' => $this->app->config('territory'),
            'planting' => $planting,
            'statistics' => $statistics,
            'security' => $this->app->config('security'),
            'privacy' => $this->app->config('privacy'),
            'map' => $this->app->config('map'),
            'proposals' => $this->app->config('proposals'),
            'dataSources' => $dataSources,
            'geocoding' => $this->app->config('geocoding'),
            'activeDataSource' => $dataSources[$source],
            'submissionUrl' => $submissionUrl,
            'csrfToken' => $auth->csrfToken(),
            'user' => $user,
            'pageTitle' => $pageTitle,
            'treeProposal' => $treeProposal,
            'isCensus' => $isCensus,
        ]));
    }

    public function health(Request $request): never
    {
        Response::json(['status' => 'ok', 'version' => $this->app->config('app')['version']]);
    }
}
