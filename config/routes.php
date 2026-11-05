<?php

declare(strict_types=1);

use Plantons\Controllers\HomeController;
use Plantons\Controllers\GeoJsonController;
use Plantons\Controllers\ProposalController;
use Plantons\Controllers\AuthController;
use Plantons\Controllers\AdminUserController;
use Plantons\Controllers\AccountController;
use Plantons\Controllers\AdminProposalController;
use Plantons\Controllers\AdminPepiniereController;
use Plantons\Controllers\ExportController;
use Plantons\Controllers\PrivacyController;
use Plantons\Controllers\AboutController;
use Plantons\Controllers\MyProposalController;

return [
    ['GET', '/', [HomeController::class, 'index']],
    ['GET', '/proposer-un-arbre', [HomeController::class, 'treeProposal']],
    ['GET', '/recenser-un-arbre', [HomeController::class, 'treeCensus']],
    ['GET', '/sante', [HomeController::class, 'health']],
    ['GET', '/donnees-personnelles', [PrivacyController::class, 'index']],
    ['GET', '/a-propos', [AboutController::class, 'index']],
    ['GET', '/api/data/territoire', [GeoJsonController::class, 'territory']],
    ['GET', '/api/data/secteurs', [GeoJsonController::class, 'sectors']],
    ['GET', '/api/data/arbres', [GeoJsonController::class, 'trees']],
    ['GET', '/api/data/propositions', [GeoJsonController::class, 'proposals']],
    ['GET', '/api/data/dons', [GeoJsonController::class, 'donations']],
    ['POST', '/api/propositions', [ProposalController::class, 'create']],
    ['POST', '/api/dons', [ProposalController::class, 'createDonation']],
    ['POST', '/api/arbres', [ProposalController::class, 'createCensus']],
    ['GET', '/inscription', [AuthController::class, 'registerForm']],
    ['POST', '/inscription', [AuthController::class, 'register']],
    ['GET', '/connexion', [AuthController::class, 'loginForm']],
    ['POST', '/connexion', [AuthController::class, 'login']],
    ['POST', '/deconnexion', [AuthController::class, 'logout']],
    ['GET', '/mot-de-passe-oublie', [AuthController::class, 'forgotForm']],
    ['POST', '/mot-de-passe-oublie', [AuthController::class, 'forgot']],
    ['GET', '/reinitialiser-mot-de-passe', [AuthController::class, 'resetForm']],
    ['POST', '/reinitialiser-mot-de-passe', [AuthController::class, 'reset']],
    ['GET', '/admin/utilisateurs', [AdminUserController::class, 'index']],
    ['POST', '/admin/utilisateurs/role', [AdminUserController::class, 'changeRole']],
    ['POST', '/admin/utilisateurs/creer', [AdminUserController::class, 'create']],
    ['POST', '/admin/utilisateurs/modifier', [AdminUserController::class, 'updateProfile']],
    ['POST', '/admin/utilisateurs/approuver', [AdminUserController::class, 'approveRegistration']],
    ['POST', '/admin/utilisateurs/supprimer', [AdminUserController::class, 'delete']],
    ['GET', '/admin/propositions', [AdminProposalController::class, 'index']],
    ['POST', '/admin/propositions/modifier', [AdminProposalController::class, 'update']],
    ['POST', '/admin/dons/modifier', [AdminProposalController::class, 'updateDonation']],
    ['GET', '/admin/pepinieres', [AdminPepiniereController::class, 'index']],
    ['POST', '/admin/pepinieres/modifier', [AdminPepiniereController::class, 'update']],
    ['GET', '/ma-proposition', [MyProposalController::class, 'show']],
    ['POST', '/ma-proposition/modifier', [MyProposalController::class, 'update']],
    ['GET', '/admin/exports', [ExportController::class, 'download']],
    ['POST', '/admin/imports', [ExportController::class, 'import']],
    ['GET', '/mon-compte', [AccountController::class, 'index']],
    ['POST', '/mon-compte', [AccountController::class, 'update']],
    ['GET', '/mon-compte/donnees', [AccountController::class, 'downloadPersonalData']],
];
