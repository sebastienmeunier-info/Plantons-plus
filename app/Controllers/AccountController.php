<?php

declare(strict_types=1);

namespace Plantons\Controllers;

use Plantons\Core\Application;
use Plantons\Core\Request;
use Plantons\Core\Response;
use Plantons\Repositories\UserRepository;
use Plantons\Services\AuthService;
use Plantons\Services\GeoJsonStore;
use Plantons\Services\StatisticsService;

final class AccountController
{
    public function __construct(private readonly Application $app) {}

    public function index(Request $request): never
    {
        $auth = $this->auth();
        $user = $auth->currentUser();
        if (!$user) { header('Location: ' . $this->app->routeUrl('/connexion'), true, 303); exit; }

        $isAdministrator = in_array($user['role'], ['administrateur', 'super_administrateur'], true);
        $tab = (string) $request->query('onglet', 'profil');
        $allowedTabs = $isAdministrator ? ['profil', 'plantations', 'dons', 'recensements', 'pepinieres', 'utilisateurs', 'exports', 'statistiques'] : ['profil', 'plantations', 'statistiques'];
        if (!in_array($tab, $allowedTabs, true)) { $tab = 'profil'; }

        $store = new GeoJsonStore();
        $userRepository = new UserRepository($this->app->config('auth')['users_file']);
        $sources = $this->app->config('data_sources');
        $isDonationTab = $tab === 'dons';
        $isCensusTab = $tab === 'recensements';
        $allFeatures = $store->read($sources[$isDonationTab ? 'donations' : ($isCensusTab ? 'trees' : 'proposals')]['file'])['features'];
        $features = array_values(array_filter($allFeatures, static fn(array $feature): bool => ($feature['properties']['email'] ?? '') === $user['email']));
        $displayFeatures = $isAdministrator ? $allFeatures : $features;
        if ($isAdministrator) {
            $usersByEmail = [];
            foreach ($userRepository->all() as $contributor) {
                $usersByEmail[mb_strtolower((string) $contributor['email'])] = $contributor;
            }
            foreach ($displayFeatures as &$feature) {
                $properties = $feature['properties'] ?? [];
                $contributor = $usersByEmail[mb_strtolower((string) ($properties['email'] ?? ''))] ?? [];
                $feature['properties']['contributor'] = [
                    'name' => trim((string) ($contributor['first_name'] ?? '') . ' ' . (string) ($contributor['last_name'] ?? '')) ?: (string) ($properties['author'] ?? ''),
                    'email' => (string) ($contributor['email'] ?? $properties['email'] ?? ''),
                    'address' => (string) ($contributor['address'] ?? ''),
                    'phone' => (string) ($contributor['phone'] ?? ''),
                ];
            }
            unset($feature);
        }
        $counts = ['proposed' => 0, 'validated' => 0, 'rejected' => 0, 'planted' => 0, 'remarkable' => 0];
        foreach ($displayFeatures as $feature) {
            $status = $feature['properties']['status'] ?? 'a_valider';
            if ($status === 'validee') { $counts['validated']++; }
            elseif ($status === 'remarquable') { $counts['remarkable']++; }
            elseif (in_array($status, ['arbre_plante', 'realisee'], true)) { $counts['planted']++; }
            elseif (in_array($status, ['refusee', 'rejetee'], true)) { $counts['rejected']++; }
            else { $counts['proposed']++; }
        }
        $allUsers = $userRepository->all();
        $managedUsers = $isAdministrator ? $allUsers : [];
        $registrationStatuses = [];
        foreach ($managedUsers as $managedUser) {
            $registrationStatuses[(string) $managedUser['id']] = (string) ($managedUser['registration_status'] ?? 'approved');
        }
        $statistics = (new StatisticsService())->summarize(
            array_merge(
                $store->read($sources['proposals']['file'])['features'],
                $store->read($sources['donations']['file'])['features'],
            ),
            $allUsers,
            $this->app->config('proposals')['status_labels'],
        );
        $pepiniereFeatures = $isAdministrator ? $store->read($sources['pepinieres']['file'])['features'] : [];

        Response::html($this->app->view()->render('account/index', [
            'application' => $this->app->config('app'), 'csrfToken' => $auth->csrfToken(), 'user' => $user,
            'tab' => $tab, 'isAdministrator' => $isAdministrator, 'counts' => $counts, 'features' => $features,
            'displayFeatures' => $displayFeatures, 'statuses' => $isCensusTab ? $this->app->config('census')['status_labels'] : $this->app->config('proposals')['status_labels'],
            'planting' => $this->app->config('planting'), 'isDonationTab' => $isDonationTab, 'isCensusTab' => $isCensusTab, 'geocoding' => $this->app->config('geocoding'),
            'notice' => $request->query('pepinieres') === 'enregistrees'
                ? 'Les positions des pépinières ont été enregistrées.'
                : ($request->query('import') === 'done'
                ? sprintf('Import terminé : %d utilisateur(s), %d plantation(s), %d don(s), %d photo(s) ajoutés et %d e-mail(s) envoyé(s).', (int) $request->query('users'), (int) $request->query('proposals'), (int) $request->query('donations'), (int) $request->query('photos'), (int) $request->query('emails'))
                : match ($request->query('approval')) {
                'sent' => 'L’inscription a été approuvée et le courriel de confirmation a été envoyé.',
                'failed' => 'L’inscription est approuvée, mais le courriel de confirmation n’a pas pu être envoyé.',
                default => match ($request->query('invite')) {
                    'sent' => 'Le compte a été créé et l’invitation a été envoyée.',
                    'failed' => 'Le compte est créé, mais l’invitation n’a pas pu être envoyée. Vérifiez la configuration SMTP puis renvoyez l’invitation.',
                    default => null,
                },
            }),
            'users' => $managedUsers,
            'pepiniereFeatures' => $pepiniereFeatures,
            'registrationStatuses' => $registrationStatuses,
            'statisticsData' => $statistics,
            'primarySuperAdministratorId' => (string) ($userRepository->primarySuperAdministrator()['id'] ?? ''),
        ]));
    }

    public function update(Request $request): never
    {
        $auth = $this->auth(); $user = $auth->currentUser();
        if (!$user || !$auth->verifyCsrf((string) $request->input('csrf_token'))) { Response::html('Accès refusé.', 403); }
        $first = trim((string) $request->input('first_name')); $last = trim((string) $request->input('last_name'));
        $email = mb_strtolower(trim((string) $request->input('email')));
        $address = mb_substr(trim((string) $request->input('address')), 0, 255);
        $phone = $this->phone($request->input('phone'));
        $password = (string) $request->input('password');
        if ($first === '' || $last === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { Response::html('Informations personnelles invalides.', 422); }
        if ($password !== '' && strlen($password) < 12) { Response::html('Le mot de passe doit contenir au moins 12 caractères.', 422); }
        $repository = new UserRepository($this->app->config('auth')['users_file']); $existing = $repository->findByEmail($email);
        if ($existing !== null && $existing['id'] !== $user['id']) { Response::html('Cette adresse e-mail est déjà utilisée.', 422); }
        $changes = ['first_name' => $first, 'last_name' => $last, 'email' => $email, 'address' => $address, 'phone' => $phone, 'notification_consent_at' => (string) $request->input('notification_consent') === '1' ? ($user['notification_consent_at'] ?? date(DATE_ATOM)) : null];
        if ($password !== '') { $changes['password_hash'] = password_hash($password, PASSWORD_DEFAULT); }
        $repository->update($user['id'], $changes);
        header('Location: ' . $this->app->routeUrl('/mon-compte?onglet=profil'), true, 303); exit;
    }

    public function downloadPersonalData(Request $request): never
    {
        $user = $this->auth()->currentUser();
        if (!$user) {
            Response::html('Accès refusé.', 403);
        }
        unset($user['password_hash']);
        $sources = $this->app->config('data_sources');
        $store = new GeoJsonStore();
        $data = ['exported_at' => date(DATE_ATOM), 'profile' => $user, 'proposals' => [], 'donations' => []];
        foreach (['proposals', 'donations'] as $source) {
            $data[$source] = array_values(array_filter(
                $store->read($sources[$source]['file'])['features'],
                static fn(array $feature): bool => mb_strtolower((string) ($feature['properties']['email'] ?? '')) === mb_strtolower((string) $user['email']),
            ));
        }
        Response::download(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", 'mes-donnees-plantons.json', 'application/json; charset=UTF-8');
    }

    private function auth(): AuthService
    {
        return new AuthService(new UserRepository($this->app->config('auth')['users_file']), $this->app->config('auth'));
    }
    private function phone(mixed $value): string
    {
        $phone = mb_substr(trim((string) $value), 0, 30);
        if ($phone !== '' && preg_match('/^[0-9+().\-\s]+$/', $phone) !== 1) { Response::html('Numéro de téléphone invalide.', 422); }
        return $phone;
    }
}
