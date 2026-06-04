#!/bin/sh
set -eu

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"

log() {
  printf '%s\n' "$*"
}

fix_permissions() {
  log "Ajustando permissões do Moodle..."

  mkdir -p "$MOODLE_DATAROOT" \
           "$MOODLE_DATAROOT/cache" \
           "$MOODLE_DATAROOT/localcache" \
           "$MOODLE_DATAROOT/temp" \
           "$MOODLE_DATAROOT/sessions" \
           "$MOODLE_DATAROOT/muc" \
           "$MOODLE_DATAROOT/filedir"

  chown -R www-data:www-data "$MOODLE_DATAROOT"
  chmod -R ug+rwX,o-rwx "$MOODLE_DATAROOT"

  # O código-fonte já é copiado no build, mas config.php pode ser gerado no runtime.
  chown -R www-data:www-data "$MOODLE_DIR"
}

create_config_if_needed() {
  if [ -f "$MOODLE_DIR/config.php" ]; then
    log "config.php já existe. Mantendo configuração atual."
    return 0
  fi

  log "Criando config.php do Moodle..."

  cat > "$MOODLE_DIR/config.php" <<'EOF'
<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = getenv('MOODLE_DBTYPE') ?: 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost    = getenv('MOODLE_DBHOST') ?: 'postgres';
$CFG->dbname    = getenv('MOODLE_DBNAME') ?: 'moodle';
$CFG->dbuser    = getenv('MOODLE_DBUSER') ?: 'moodleuser';
$CFG->dbpass    = getenv('MOODLE_DBPASS') ?: '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MOODLE_DBPORT') ?: '5432',
  'dbsocket' => '',
);

$CFG->wwwroot   = getenv('MOODLE_URL') ?: 'http://localhost';
$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/var/www/moodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0770;

require_once(__DIR__ . '/lib/setup.php');
EOF

  chown www-data:www-data "$MOODLE_DIR/config.php"
  chmod 640 "$MOODLE_DIR/config.php"
}

database_is_reachable() {
  php <<'PHP'
<?php
try {
    $host = getenv('MOODLE_DBHOST') ?: 'postgres';
    $port = getenv('MOODLE_DBPORT') ?: '5432';
    $db   = getenv('MOODLE_DBNAME') ?: 'moodle';
    $user = getenv('MOODLE_DBUSER') ?: 'moodleuser';
    $pass = getenv('MOODLE_DBPASS') ?: '';
    new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
PHP
}

database_is_installed() {
  php <<'PHP'
<?php
try {
    $host = getenv('MOODLE_DBHOST') ?: 'postgres';
    $port = getenv('MOODLE_DBPORT') ?: '5432';
    $db   = getenv('MOODLE_DBNAME') ?: 'moodle';
    $user = getenv('MOODLE_DBUSER') ?: 'moodleuser';
    $pass = getenv('MOODLE_DBPASS') ?: '';
    $pdo = new PDO("pgsql:host={$host};port={$port};dbname={$db}", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $stmt = $pdo->query("SELECT to_regclass('public.mdl_config')");
    $table = $stmt ? $stmt->fetchColumn() : null;
    exit($table ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
PHP
}

wait_for_database() {
  log "Aguardando PostgreSQL ficar disponível..."
  attempts=0
  until database_is_reachable; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge 60 ]; then
      log "ERRO: PostgreSQL não ficou disponível após 60 tentativas."
      exit 1
    fi
    sleep 5
  done
  log "PostgreSQL disponível."
}

install_database_if_needed() {
  if [ "${MOODLE_AUTO_INSTALL:-true}" != "true" ]; then
    log "Instalação automática do banco desativada para este container."
    return 0
  fi

  wait_for_database

  if database_is_installed; then
    log "Banco do Moodle já possui tabelas. Pulando instalação."
    touch "$MOODLE_DATAROOT/.moodle-installed"
    chown www-data:www-data "$MOODLE_DATAROOT/.moodle-installed"
    return 0
  fi

  : "${MOODLE_ADMIN_PASSWORD:?Defina MOODLE_ADMIN_PASSWORD no Coolify}"
  : "${MOODLE_ADMIN_EMAIL:?Defina MOODLE_ADMIN_EMAIL no Coolify}"

  log "Banco vazio detectado. Instalando Moodle automaticamente..."

  php "$MOODLE_DIR/admin/cli/install_database.php" \
    --agree-license \
    --non-interactive \
    --fullname="${MOODLE_FULLNAME:-Mboepi}" \
    --shortname="${MOODLE_SHORTNAME:-Mboepi}" \
    --adminuser="${MOODLE_ADMIN_USER:-admin}" \
    --adminpass="${MOODLE_ADMIN_PASSWORD}" \
    --adminemail="${MOODLE_ADMIN_EMAIL}"

  log "Instalação do banco concluída."

  touch "$MOODLE_DATAROOT/.moodle-installed"
  chown www-data:www-data "$MOODLE_DATAROOT/.moodle-installed"

  fix_permissions
}

fix_permissions
create_config_if_needed
install_database_if_needed

exec "$@"
