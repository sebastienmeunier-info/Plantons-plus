#!/usr/bin/env bash
#
# Mise à jour de Plantons + sur un serveur YunoHost (My Webapp).
#
# Le script se connecte en SSH au serveur, sauvegarde les données et la
# configuration, aligne le code sur la branche main du dépôt GitHub, puis
# restaure les données. Les propositions, dons, recensements, pépinières
# et comptes utilisateurs ne sont jamais modifiés.
#
# Prérequis sur la machine qui lance le script :
#   - sshpass (connexion SSH par mot de passe) : sudo apt install sshpass
#
# Usage :
#   1. Renseigner les paramètres ci-dessous.
#   2. chmod +x scripts/mise-a-jour.sh
#   3. ./scripts/mise-a-jour.sh
#
# Sécurité : ne versionnez jamais ce fichier une fois vos identifiants
# renseignés (il est ignoré par git si vous respectez la consigne ci-dessus :
# copiez-le hors du dépôt, par exemple ~/bin/mise-a-jour.sh).

set -euo pipefail

# ============================================================================
#  PARAMÈTRES DE CONNEXION AU SERVEUR — à adapter
# ============================================================================

SERVER="apps.sebastienmeunier.info"   # URL du serveur YunoHost
SSH_PORT="22"                         # Port SSH (22 par défaut)
SSH_USER="admin"                      # Utilisateur SSH (admin YunoHost)
SSH_PASS="mot-de-passe-a-remplacer"   # Mot de passe SSH

# Chemin de l'application sur le serveur (dossier installé par My Webapp)
APP_DIR="/var/www/my_webapp"

# Dépôt Git du projet
REPO_URL="https://github.com/sebastienmeunier-info/Plantons-plus.git"
BRANCH="main"

# ============================================================================
#  FIN DES PARAMÈTRES — rien à modifier ci-dessous
# ============================================================================

STAMP="$(date +%F-%H%M)"
REMOTE_BACKUP_DIR="/root/plantons-sauvegardes/${STAMP}"

echo "==> Connexion à ${SSH_USER}@${SERVER}:${SSH_PORT}"

run_remote() {
    sshpass -p "${SSH_PASS}" ssh -p "${SSH_PORT}" \
        -o StrictHostKeyChecking=accept-new \
        "${SSH_USER}@${SERVER}" "$@"
}

# 1. Vérifications préalables sur le serveur
echo "==> Vérifications sur le serveur"
run_remote "test -d ${APP_DIR} || { echo 'Dossier ${APP_DIR} introuvable — ajustez APP_DIR.'; exit 1; }"
run_remote "command -v git >/dev/null || { echo 'git manquant sur le serveur : apt install git'; exit 1; }"

# 2. Sauvegarde des données et de la configuration
echo "==> Sauvegarde de data/ et config/config.php vers ${REMOTE_BACKUP_DIR}"
run_remote "mkdir -p '${REMOTE_BACKUP_DIR}' \
    && tar czf '${REMOTE_BACKUP_DIR}/data.tar.gz' -C '${APP_DIR}' data \
    && cp '${APP_DIR}/config/config.php' '${REMOTE_BACKUP_DIR}/config.php'"

# 3. Initialisation git (une seule fois) puis mise à jour du code
echo "==> Mise à jour du code depuis ${REPO_URL} (${BRANCH})"
run_remote "cd '${APP_DIR}' \
    && { test -d .git || { git init && git remote add origin '${REPO_URL}'; }; } \
    && git fetch origin '${BRANCH}' \
    && git reset --hard "origin/${BRANCH}" \
    && git clean -fd -- app config public resources index.php 2>/dev/null || true"

# 4. Restauration des données (jamais touchées par la mise à jour)
echo "==> Restauration des données"
run_remote "tar xzf '${REMOTE_BACKUP_DIR}/data.tar.gz' -C '${APP_DIR}'"

# 5. Configuration : conserver la version sauvegardée si elle a été personnalisée
echo "==> Restauration de config/config.php (votre configuration d'origine)"
run_remote "cp '${REMOTE_BACKUP_DIR}/config.php' '${APP_DIR}/config/config.php'"
echo "    /!\ Si de nouveaux réglages sont apparus dans config.php du dépôt,"
echo "    comparez : diff ${APP_DIR}/config/config.php <(git -C ${APP_DIR} show origin/${BRANCH}:config/config.php)"

# 6. Permissions pour My Webapp
echo "==> Permissions"
run_remote "chown -R my_webapp: '${APP_DIR}' 2>/dev/null || chown -R www-data: '${APP_DIR}'"

# 7. Vérification rapide
echo "==> Vérification"
run_remote "test -f '${APP_DIR}/index.php' && test -d '${APP_DIR}/data' && echo 'Mise à jour terminée.'"

echo ""
echo "Mise à jour réussie ✅"
echo "  Sauvegardes sur le serveur : ${REMOTE_BACKUP_DIR}"
echo "  Pensez à :"
echo "   - adapter config/config.php si de nouveaux réglages sont apparus"
echo "   - recharger la page avec Ctrl+F5 (cache CSS/JS du navigateur)"
echo "   - les utilisateurs devront se reconnecter une fois (nouveau répertoire de sessions)"
