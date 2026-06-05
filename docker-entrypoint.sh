#!/bin/sh
set -eu

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"

# Arquivos criados por PHP/Moodle ficam acessíveis ao grupo www-data e inacessíveis a outros usuários.
umask 0007

log() {
  printf '%s\n' "$*"
}

required_env() {
  var_name="$1"
  eval "value=\${$var_name:-}"
  if [ -z "$value" ]; then
    echo "ERRO: variável obrigatória não definida: $var_name"
    exit 1
  fi
}

run_as_www_data() {
  gosu www-data "$@"
}

validate_required_envs() {
  required_env MOODLE_URL
  required_env MOODLE_DBPASS

  if [ "${MOODLE_AUTO_INSTALL:-true}" = "true" ]; then
    required_env MOODLE_ADMIN_PASSWORD
    required_env MOODLE_ADMIN_EMAIL
  fi
}

fix_permissions() {
  log "Ajustando permissões do Moodle..."

  mkdir -p "$MOODLE_DATAROOT" \
           "$MOODLE_DATAROOT/cache" \
           "$MOODLE_DATAROOT/localcache" \
           "$MOODLE_DATAROOT/temp" \
           "$MOODLE_DATAROOT/sessions" \
           "$MOODLE_DATAROOT/muc" \
           "$MOODLE_DATAROOT/filedir" \
           "$MOODLE_DATAROOT/lang"

  chown -R www-data:www-data "$MOODLE_DATAROOT"
  find "$MOODLE_DATAROOT" -type d -exec chmod 770 {} \;
  find "$MOODLE_DATAROOT" -type f -exec chmod 660 {} \;

  # Evita que config.php fique inacessível ao PHP-FPM.
  if [ -f "$MOODLE_DIR/config.php" ]; then
    chown www-data:www-data "$MOODLE_DIR/config.php"
    chmod 640 "$MOODLE_DIR/config.php"
  fi
}

create_config_if_needed() {
  if [ -f "$MOODLE_DIR/config.php" ]; then
    log "config.php já existe. Mantendo configuração atual."
    return 0
  fi

  log "Criando config.php do Moodle..."

  cat > "$MOODLE_DIR/config.php" <<'PHP_CONFIG'
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

// Necessário quando o HTTPS termina no Traefik/Coolify e o Nginx interno recebe HTTP.
$CFG->sslproxy = true;

$CFG->directorypermissions = 0770;

require_once(__DIR__ . '/lib/setup.php');
PHP_CONFIG

  chown www-data:www-data "$MOODLE_DIR/config.php"
  chmod 640 "$MOODLE_DIR/config.php"
}

database_is_reachable() {
  run_as_www_data php <<'PHP'
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
  run_as_www_data php <<'PHP'
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
    chmod 660 "$MOODLE_DATAROOT/.moodle-installed"

    fix_permissions
    return 0
  fi

  log "Banco vazio detectado. Instalando Moodle automaticamente como www-data..."

  run_as_www_data php "$MOODLE_DIR/admin/cli/install_database.php" \
    --agree-license \
    --fullname="${MOODLE_FULLNAME:-Mboepi}" \
    --shortname="${MOODLE_SHORTNAME:-Mboepi}" \
    --adminuser="${MOODLE_ADMIN_USER:-admin}" \
    --adminpass="${MOODLE_ADMIN_PASSWORD}" \
    --adminemail="${MOODLE_ADMIN_EMAIL}"

  log "Instalação do banco concluída."

  touch "$MOODLE_DATAROOT/.moodle-installed"
  chown www-data:www-data "$MOODLE_DATAROOT/.moodle-installed"
  chmod 660 "$MOODLE_DATAROOT/.moodle-installed"

  fix_permissions
}

validate_required_envs
fix_permissions
create_config_if_needed
install_database_if_needed

exec "$@"