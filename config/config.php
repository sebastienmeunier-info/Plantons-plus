<?php

declare(strict_types=1);

use Plantons\Core\Logger;

/*
 * ============================================================================
 *  CONFIGURATION DE L'APPLICATION PLANTONS +
 * ============================================================================
 *
 *  Ce fichier centralise TOUS les réglages. Aucune autre modification de
 *  code n'est nécessaire pour déployer l'application sur un autre territoire.
 *
 *  Comment lire ce fichier :
 *  - Les sections sont classées de la plus souvent modifiée (territoire,
 *    carte, listes métier) à la plus rarement touchée (sécurité, journaux).
 *  - Chaque valeur peut être surchargée par une variable d'environnement
 *    (préfixe PLANTONS_), utile en production pour les réglages sensibles
 *    (mot de passe SMTP notamment) sans modifier le dépôt.
 *  - Les commentaires « [Territoire] » signalent les réglages à adapter
 *    pour chaque collectivité ; les autres peuvent rester inchangés.
 *
 *  Sommaire :
 *    1. Application        — identité, environnement, routage
 *    2. Territoire         — [Territoire] nom, centre de carte, fuseau
 *    3. Carte              — zooms par défaut
 *    4. Fichiers de données — GeoJSON (propositions, dons, arbres, etc.)
 *    5. Géocodage           — service d'adresses (IGN/BAN par défaut)
 *    6. Listes métier       — essences, objectifs, conditionnements, statuts
 *    7. Comptes et rôles    — fichiers utilisateurs, rôles, inscriptions
 *    8. E-mails             — SMTP et modèles de messages
 *    9. Vie privée (RGPD)   — précision publique, mentions, durées
 *   10. Sécurité            — CSRF, téléversements
 *   11. Journaux            — emplacement et niveau de log
 * ============================================================================
 */

/* ---------------------------------------------------------------------------
 * Variables préparatoires (ne pas modifier en temps normal).
 * ------------------------------------------------------------------------- */

$root = dirname(__DIR__);

$environment = getenv('PLANTONS_ENV') ?: 'development';
$projectName = getenv('PLANTONS_PROJECT_NAME') ?: 'Plantons +';
$territoryName = getenv('PLANTONS_TERRITORY_NAME') ?: 'Baugé-en-Anjou';
$sectorType = getenv('PLANTONS_TYPE_SECTEUR') ?: 'commune déléguée';

// [Territoire] Centre de la carte : [latitude, longitude].
$territoryCenter = [47.5310, -0.1020];

// Sous-dossier d'installation, détecté automatiquement (ex. /PlanArbreBEA).
// Surcharger avec PLANTONS_BASE_PATH seulement si l'hébergement utilise
// une règle particulère.
$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$detectedBasePath = str_ends_with($scriptName, '.php') ? str_replace('\\', '/', dirname($scriptName)) : '';
$basePath = str_replace('\\', '/', rtrim((string) (getenv('PLANTONS_BASE_PATH') ?: $detectedBasePath), '/.'));
$basePath = $basePath === '/' ? '' : $basePath;

return [

    /* ========================================================================
     * 1. APPLICATION
     * Identité du projet, environnement technique et mode de routage.
     * ======================================================================== */

    'app' => [
        'name' => $projectName,
        // Laisser vide pour utiliser l'accroche visuelle par défaut de l'en-tête.
        'header_text' => getenv('PLANTONS_HEADER_TEXT') ?: '',
        // Laisser vide pour utiliser le logo libre fourni dans le BrandKit.
        'logo_url' => getenv('PLANTONS_PROJECT_LOGO_URL') ?: $basePath . '/docs/brankit/02_Logos/SVG/Plantons.svg',
        // 'development' ou 'production' (via PLANTONS_ENV).
        'environment' => $environment,
        // En production, passer PLANTONS_DEBUG=0 pour masquer les détails d'erreur.
        'debug' => filter_var(getenv('PLANTONS_DEBUG') ?: $environment !== 'production', FILTER_VALIDATE_BOOL),
        // URL publique complète, si le serveur la connaît (facultatif).
        'base_url' => rtrim((string) (getenv('PLANTONS_BASE_URL') ?: ''), '/'),
        // Sous-dossier d'installation (détection automatique, cf. plus haut).
        'base_path' => $basePath,
        // 'query' : compatible hébergements mutualisés sans mod_rewrite.
        // 'path'   : URLs propres, nécessite la réécriture d'URL (.htaccess).
        'routing_mode' => getenv('PLANTONS_ROUTING_MODE') ?: 'query',
        'timezone' => getenv('PLANTONS_TIMEZONE') ?: 'Europe/Paris',
        'version' => trim((string) file_get_contents($root . '/VERSION')),
    ],

    /* ========================================================================
     * 1 bis. PAGE D'ACCUEL
     * Description affichée sur la page d'accueil, à droite de la carte.
     * HTML autorisé (paragraphes, listes, liens) — la carte montre les
     * propositions, dons et recensements VALIDÉS par la collectivité.
     * ======================================================================== */

    'home' => [
        'description_html' => getenv('PLANTONS_HOME_DESCRIPTION') ?: '<p><strong>Plantons +</strong> est la plateforme participative du territoire pour planter, donner et recenser des arbres.</p><p>Chaque habitant peut proposer une plantation, offrir un arbre à la collectivité ou recenser un arbre remarquable existant. Les contributions sont examinées par la collectivité, puis les contenus validés apparaissent sur cette carte.</p><ul><li><a href="' . $basePath . '/?route=%2Fenvie-d-un-arbre">Envie d’un arbre</a> — proposez un emplacement de plantation</li><li><a href="' . $basePath . '/?route=%2Fproposer-un-arbre">Donner un arbre</a> — offrez un arbre en pot ou en pleine terre</li><li><a href="' . $basePath . '/?route=%2Frecenser-un-arbre">Recenser un arbre</a> — faites connaître le patrimoine arboré existant</li></ul><p>Ensemble, plantons plus d’arbres sur notre territoire.</p>',
    ],

    /* ========================================================================
     * 2. TERRITOIRE  [Territoire]
     * Adapter uniquement cette section pour déployer sur une autre collectivité.
     * Les géométries (limites, secteurs) restent des fichiers de données
     * séparés (cf. section 4) afin de pouvoir les mettre à jour sans code.
     * ======================================================================== */

    'territory' => [
        // Nom affiché dans l'interface (en-tête, e-mails, mentions RGPD).
        'name' => $territoryName,
        // Libellé des secteurs : « commune déléguée », « quartier », etc.
        'type_secteur' => $sectorType,
        // Centre de la carte publique [latitude, longitude].
        'center' => $territoryCenter,
        'timezone' => getenv('PLANTONS_TIMEZONE') ?: 'Europe/Paris',
    ],

    /* ========================================================================
     * 3. CARTE
     * Réglages d'affichage de la carte Leaflet.
     * ======================================================================== */

    'map' => [
        'center' => $territoryCenter,
        // Niveau de zoom initial (12 ≈ vue communale).
        'default_zoom' => 12,
        // Bornes de zoom autorisées.
        'min_zoom' => 10,
        'max_zoom' => 20,
        // Coefficient multiplicateur appliqué à la largeur et à la hauteur des
        // icônes de repères sur les cartes (1 = taille de référence, 0,5 = moitié).
        'marker_width_coefficient' => (float) (getenv('PLANTONS_MARKER_WIDTH_COEFFICIENT') ?: 0.5),
        'marker_height_coefficient' => (float) (getenv('PLANTONS_MARKER_HEIGHT_COEFFICIENT') ?: 0.5),
    ],

    /* ========================================================================
     * 4. FICHIERS DE DONNÉES
     * Chaque source associe un fichier GeoJSON (stockage) et une URL (API).
     * Les fichiers se créent automatiquement au premier envoi ; seul le
     * territoire et les secteurs sont fournis par défaut.
     * ======================================================================== */

    'data_sources' => [
        // Arbres existants recensés par les habitants (page « Recenser un arbre »).
        'trees' => [
            'file' => $root . '/data/arbres.geojson',
            'url' => getenv('PLANTONS_TREES_GEOJSON_URL') ?: '/api/data/arbres',
        ],
        // Propositions de plantation (page « Envie d'un arbre »).
        'proposals' => [
            'file' => $root . '/data/propositions.geojson',
            'url' => getenv('PLANTONS_PROPOSALS_GEOJSON_URL') ?: '/api/data/propositions',
        ],
        // Dons d'arbres (page « Donner un arbre »).
        'donations' => [
            'file' => $root . '/data/dons.geojson',
            'url' => getenv('PLANTONS_DONATIONS_GEOJSON_URL') ?: '/api/data/dons',
        ],
        // [Territoire] Limite du territoire : hors de cette zone, les envois
        // sont refusés. Supprimer l'entrée (ou `file` vide) pour n'avoir
        // aucune limite territoriale.
        'territory' => [
            'file' => $root . '/data/territoire.geojson',
            'url' => getenv('PLANTONS_TERRITORY_GEOJSON_URL') ?: '/api/data/territoire',
        ],
        // [Territoire] Secteurs (communes déléguées, quartiers…).
        // Supprimer l'entrée (ou `file` vide) si aucun secteur ne s'applique.
        'sectors' => [
            'file' => getenv('PLANTONS_SECTORS_GEOJSON_FILE') ?: $root . '/data/communes-deleguees.geojson',
            'url' => getenv('PLANTONS_SECTORS_GEOJSON_URL') ?: (getenv('PLANTONS_MUNICIPALITIES_GEOJSON_URL') ?: '/api/data/secteurs'),
        ],
        // Pépinières (lieux de mise en nourrice, P1 à P5, onglet admin).
        'pepinieres' => [
            'file' => getenv('PLANTONS_PEPINIERES_GEOJSON_FILE') ?: $root . '/data/pepinieres.geojson',
            'url' => getenv('PLANTONS_PEPINIERES_GEOJSON_URL') ?: '/api/data/pepinieres',
        ],
    ],

    /* ========================================================================
     * 5. GÉOCODAGE
     * Service de recherche d'adresses. Par défaut le service public IGN/BAN ;
     * le remplacer par une infrastructure locale si l'hébergement l'exige.
     * ======================================================================== */

    'geocoding' => [
        // Recherche d'adresse à partir d'un texte.
        'search_url' => getenv('PLANTONS_GEOCODING_SEARCH_URL') ?: 'https://data.geopf.fr/geocodage/search',
        // Adresse à partir de coordonnées (inverse).
        'reverse_url' => getenv('PLANTONS_GEOCODING_REVERSE_URL') ?: 'https://data.geopf.fr/geocodage/reverse',
    ],

    /* ========================================================================
     * 6. LISTES MÉTIER
     * Essences proposées, objectifs de plantation, conditionnements des dons
     * et libellés de statuts. Ces listes alimentent directement les
     * formulaires publics et l'espace d'administration.
     * ======================================================================== */

    'planting' => [
        // Objectif de plantation affiché sur la page d'accueil.
        'target_count' => 1000,
        // Nombre maximum d'objectifs cochables par proposition.
        'max_objectives_per_proposal' => 3,
        // Essences proposées dans les formulaires.
        'allowed_species' => [
            'Chêne', 'Érable', 'Tilleul', 'Charme', 'Frêne', 'Merisier',
            'Arbre fruitier', 'Autre',
        ],
        // Objectifs de plantation (propositions).
        'objectives' => [
            'ombre' => ['label' => 'Ombre et fraîcheur', 'icon' => '🌳'],
            'biodiversite' => ['label' => 'Biodiversité', 'icon' => '🐝'],
            'paysage' => ['label' => 'Paysage', 'icon' => '🏞️'],
            'eaux_pluviales' => ['label' => 'Gestion des eaux pluviales', 'icon' => '💧'],
            'arbre_fruitier' => ['label' => 'Arbre fruitier', 'icon' => '🍎'],
            'confort_pieton' => ['label' => 'Confort piéton', 'icon' => '🚶'],
            'autre' => ['label' => 'Autre objectif', 'icon' => '✦'],
        ],
        // Conditionnement d'un arbre donné (page « Donner un arbre »).
        'tree_conditioning' => [
            'pot' => ['label' => 'En pot', 'icon' => '🪴'],
            'pleine_terre' => ['label' => 'Pleine terre', 'icon' => '🌱'],
        ],
        // Taille d'un arbre donné.
        'tree_sizes' => [
            'moins_50cm' => ['label' => 'Moins de 50 cm', 'icon' => '🌱'],
            '50cm_a_1m' => ['label' => 'De 50 cm à 1 m', 'icon' => '🌿'],
            '1_a_2m' => ['label' => 'De 1 à 2 m', 'icon' => '🌳'],
            'plus_2m' => ['label' => 'Plus de 2 m', 'icon' => '🌲'],
        ],
    ],

    // Statuts d'une proposition de plantation ou d'un don.
    'proposals' => [
        'status_labels' => [
            'a_valider' => 'Proposée',
            'refusee' => 'Refusée',
            'rejetee' => 'Refusée',
            'validee' => 'Validée',
            'arbre_plante' => 'Arbre planté',
            'realisee' => 'Arbre planté',
        ],
    ],

    // Statuts d'un arbre recensé (page « Recenser un arbre »).
    // La suppression d'un recensement reste réservée au super-admin.
    'census' => [
        'status_labels' => [
            'a_valider' => 'Proposé',
            'refusee' => 'Refusé',
            'validee' => 'Validé',
            'remarquable' => 'Remarquable',
        ],
    ],

    /* ========================================================================
     * 7. COMPTES ET RÔLES
     * Fichiers de données des utilisateurs et règles d'inscription.
     * ======================================================================== */

    'auth' => [
        'users_file' => $root . '/data/utilisateurs.json',
        'password_resets_file' => $root . '/data/reinitialisations.json',
        // Répertoire de stockage des sessions PHP. Sur un hébergement mutualisé
        // multi-serveurs (OVH, etc.), le répertoire par défaut n'est PAS partagé
        // entre les serveurs : les sessions étaient perdues aléatoirement
        // (« Session expirée »). Un répertoire local règle le problème.
        'sessions_path' => getenv('PLANTONS_SESSIONS_PATH') ?: $root . '/data/sessions',
        // Nom de la variable de session conservant l'utilisateur connecté.
        'session_key' => 'plantons_user_id',
        // E-mails promus administrateur / super-admin au premier lancement.
        'bootstrap_admin_email' => strtolower((string) (getenv('PLANTONS_BOOTSTRAP_ADMIN_EMAIL') ?: '')),
        'administrator_emails' => ['contact@sebastienmeunier.info'],
        'bootstrap_super_admin_email' => strtolower((string) (getenv('PLANTONS_BOOTSTRAP_SUPER_ADMIN_EMAIL') ?: '')),
        // true : toute nouvelle inscription doit être approuvée par un admin.
        'validation_inscription_obligatoire' => filter_var(getenv('PLANTONS_VALIDATION_INSCRIPTION_OBLIGATOIRE') ?: false, FILTER_VALIDATE_BOOL),
        // true : seuls les comptes connectés peuvent envoyer une proposition.
        'inscription_obligatoire' => filter_var(getenv('PLANTONS_INSCRIPTION_OBLIGATOIRE') ?: false, FILTER_VALIDATE_BOOL),
        // Rôles disponibles, du plus simple au plus élevé.
        'roles' => ['contributeur', 'administrateur', 'super_administrateur'],
    ],

    /* ========================================================================
     * 8. E-MAILS
     * Serveur SMTP et modèles des messages transactionnels.
     * ======================================================================== */

    'smtp' => [
        // Identifiants sensibles : définir PLANTONS_SMTP_PASSWORD dans
        // l'hébergement, jamais dans le dépôt Git.
        'host' => getenv('PLANTONS_SMTP_HOST') ?: 'ssl0.ovh.net',
        'port' => (int) (getenv('PLANTONS_SMTP_PORT') ?: 465),
        'encryption' => getenv('PLANTONS_SMTP_ENCRYPTION') ?: 'ssl',
        'username' => getenv('PLANTONS_SMTP_USERNAME') ?: 'plantons@sebastienmeunier.info',
        // PLANARBRE_SMTP_PASSWORD est accepté pour les installations déjà configurées.
        'password' => getenv('PLANTONS_SMTP_PASSWORD') ?: (getenv('PLANARBRE_SMTP_PASSWORD') ?: ''),
        'from_email' => getenv('PLANTONS_SMTP_FROM_EMAIL') ?: 'plantons@sebastienmeunier.info',
        'from_name' => getenv('PLANTONS_SMTP_FROM_NAME') ?: $projectName,
    ],

    'notifications' => [
        // Activer/désactiver l'envoi des e-mails de suivi.
        'status_changes' => true,
        // Copie cachée facultative de tous les e-mails transactionnels.
        'notification_cci' => getenv('PLANTONS_NOTIFICATION_CCI') ?: 'plantons@sebastienmeunier.info',
        // Statuts déclenchant un e-mail au contributeur.
        'events' => ['validee', 'rejetee', 'arbre_plante', 'localisation_deplacee'],
        /*
         * Modèles des messages. Variables disponibles :
         * {{project_name}}, {{first_name}}, {{last_name}}, {{proposal_id}},
         * {{species}}, {{location}}, {{comment}}, {{administrator_name}},
         * {{administrator_email}}, {{instance_url}}, {{url}}.
         */
        'messages' => [
            'account_invitation' => [
                'subject' => 'Activation de votre compte · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre compte a été créé. Définissez votre mot de passe en suivant ce lien, valable 7 jours :\n{{url}}\n\nÀ bientôt,\n{{project_name}}",
            ],
            'password_reset' => [
                'subject' => 'Réinitialisation de votre mot de passe · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nUtilisez ce lien valable une heure pour choisir un nouveau mot de passe :\n{{url}}\n\n{{project_name}}",
            ],
            'account_imported' => [
                'subject' => 'Votre compte a été importé · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre compte a été importé sur cette instance :\n{{instance_url}}\n\nPour choisir votre nouveau mot de passe, utilisez ce lien valable 7 jours :\n{{url}}\n\n{{project_name}}",
            ],
            'registration_approved' => [
                'subject' => 'Votre inscription est validée · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre inscription a été validée. Vous pouvez maintenant vous connecter à l’application.\n\nValidation réalisée par : {{administrator_name}} ({{administrator_email}})\n\n{{project_name}}",
            ],
            'proposal_administrative_update' => [
                'subject' => 'Votre proposition a été mise à jour · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre proposition {{proposal_id}} a été modifiée par l’administration.\n\nStatut : {{status}}\nEssence : {{species}}\nObjectifs / caractéristiques : {{objectives}}\nAdresse : {{address}}\nCoordonnées GPS : {{latitude}}, {{longitude}}\nSecteur : {{sector}}\n\nVotre commentaire :\n{{comment}}\n\nCommentaire de l’administration :\n{{admin_comment}}\n\nModification réalisée par : {{administrator_name}} ({{administrator_email}})\n\nConsulter votre proposition :\n{{proposal_url}}\n\n{{project_name}}",
            ],
            'proposal_deleted' => [
                'subject' => 'Votre proposition a été supprimée · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre proposition {{proposal_id}} a été supprimée par l’administration.\n\nStatut avant suppression : {{status}}\nEssence : {{species}}\nObjectifs / caractéristiques : {{objectives}}\nAdresse : {{address}}\nCoordonnées GPS : {{latitude}}, {{longitude}}\nSecteur : {{sector}}\n\nVotre commentaire :\n{{comment}}\n\nCommentaire de l’administration :\n{{admin_comment}}\n\nSuppression réalisée par : {{administrator_name}} ({{administrator_email}})\n\n{{project_name}}",
            ],
            'proposal_validated' => [
                'subject' => 'Votre proposition a été validée · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre proposition {{proposal_id}} pour {{species}} a été validée.\n\nCommentaire de l’administration :\n{{comment}}\n\nModification réalisée par : {{administrator_name}} ({{administrator_email}})\n\n{{project_name}}",
            ],
            'proposal_rejected' => [
                'subject' => 'Décision concernant votre proposition · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nVotre proposition {{proposal_id}} pour {{species}} n’a pas été retenue.\n\nCommentaire de l’administration :\n{{comment}}\n\nModification réalisée par : {{administrator_name}} ({{administrator_email}})\n\n{{project_name}}",
            ],
            'tree_planted' => [
                'subject' => 'Plantation réalisée · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nL’arbre proposé dans le cadre de la proposition {{proposal_id}} a été planté.\n\nCommentaire de l’administration :\n{{comment}}\n\nModification réalisée par : {{administrator_name}} ({{administrator_email}})\n\nMerci pour votre contribution,\n{{project_name}}",
            ],
            'location_moved' => [
                'subject' => 'Localisation mise à jour · {{project_name}}',
                'body' => "Bonjour {{first_name}},\n\nLa localisation de votre proposition {{proposal_id}} a été mise à jour : {{location}}.\n\nCommentaire de l’administration :\n{{comment}}\n\nModification réalisée par : {{administrator_name}} ({{administrator_email}})\n\n{{project_name}}",
            ],
        ],
    ],

    /* ========================================================================
     * 9. VIE PRIVÉE (RGPD)
     * Réglages de publication des données et mentions légales.
     * ======================================================================== */

    'privacy' => [
        /*
         * Données affichées sur les cartes publiques. 4 décimales représentent
         * environ 11 m : suffisant pour visualiser une proposition sans
         * publier son emplacement exact.
         */
        'public_coordinate_precision' => 4,
        // Propriétés visibles publiquement (jamais l'e-mail ni les photos).
        'public_proposal_properties' => ['status', 'species', 'objectives', 'conditioning', 'tree_size'],
        // [Territoire] Mentions à adapter par chaque collectivité avant
        // la mise en production.
        'controller_name' => getenv('PLANTONS_PRIVACY_CONTROLLER') ?: 'Sébastien MEUNIER, conseiller municipal (minorité) - Commune de ' . $territoryName,
        'contact_email' => getenv('PLANTONS_PRIVACY_CONTACT_EMAIL') ?: 'plantons@sebastienmeunier.info',
        'dpo_email' => getenv('PLANTONS_DPO_EMAIL') ?: '',
        'legal_basis' => getenv('PLANTONS_PRIVACY_LEGAL_BASIS') ?: 'mission d’intérêt public exercée par la collectivité',
        'account_retention' => getenv('PLANTONS_ACCOUNT_RETENTION') ?: 'pendant la durée d’utilisation du compte, puis 3 ans après sa dernière activité',
        'proposal_retention' => getenv('PLANTONS_PROPOSAL_RETENTION') ?: '5 ans après la clôture de la proposition',
    ],

    /* ========================================================================
     * 10. SÉCURITÉ
     * Protection CSRF et limites de téléversement.
     * ======================================================================== */

    'security' => [
        // Nom du champ de formulaire portant le jeton CSRF.
        'csrf_token_name' => 'csrf_token',
        // Taille maximum d'une photo (octets) : 512 000 = 500 Ko.
        'max_upload_size' => 512_000,
        // Nombre maximum de photos par envoi.
        'max_photos_per_proposal' => 3,
        // Taille maximum d'une archive d'import/export complet (octets).
        'max_transfer_size' => 104857600,
        // Longueur maximum acceptée pour un e-mail.
        'max_email_length' => 254,
        // Formats de photos acceptés.
        'allowed_photo_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    /* ========================================================================
     * 11. JOURNAUX
     * Fichier de log applicatif et niveau de détail.
     * ======================================================================== */

    'logging' => [
        'file' => $root . '/logs/application.log',
        // En production : avertissements et plus ; ailleurs : tout (debug).
        'minimum_level' => $environment === 'production' ? Logger::WARNING : Logger::DEBUG,
    ],

    /* ========================================================================
     * CHEMINS INTERNES
     * Emplacements des répertoires techniques — rarement modifiés.
     * ======================================================================== */

    'paths' => [
        'root' => $root,
        'data' => $root . '/data',
        'logs' => $root . '/logs',
        'uploads' => $root . '/uploads',
        'views' => $root . '/resources/templates',
        'public' => $root . '/public',
    ],
];
