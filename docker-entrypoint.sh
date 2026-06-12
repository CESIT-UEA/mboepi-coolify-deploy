#!/bin/sh
set -eu

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"

# Files created by PHP/Moodle should be writable by www-data and not world-readable.
umask 0007

log() {
  printf '%s\n' "$*"
}

required_env() {
  var_name="$1"
  eval "value=\${$var_name:-}"
  if [ -z "$value" ]; then
    echo "ERROR: required variable is not set: $var_name"
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

validate_moodle_code_exists() {
  if [ ! -f "$MOODLE_DIR/admin/cli/install_database.php" ]; then
    log "ERRO: codigo do Moodle nao encontrado em $MOODLE_DIR."
    log "Verifique se /var/www/moodle nao foi sobrescrito por volume vazio."
    exit 1
  fi

  if [ ! -f "$MOODLE_DIR/public/login/index.php" ]; then
    log "ERRO: /public/login/index.php nao encontrado."
    log "A configuracao do Nginx provavelmente usa /var/www/moodle/public como root."
    exit 1
  fi
}

fix_permissions() {
  log "Adjusting Moodle permissions..."

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

  # Keep config.php readable by PHP-FPM.
  if [ -f "$MOODLE_DIR/config.php" ]; then
    chown www-data:www-data "$MOODLE_DIR/config.php"
    chmod 640 "$MOODLE_DIR/config.php"
  fi
}

create_config_if_needed() {
  if [ -f "$MOODLE_DIR/config.php" ]; then
    log "config.php already exists. Keeping current configuration."
    return 0
  fi

  log "Creating Moodle config.php..."

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
$CFG->dbpass    = getenv('MOODLE_DBPASS') ?: getenv('POSTGRES_PASSWORD') ?: '';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MOODLE_DBPORT') ?: '5432',
  'dbsocket' => '',
);

$CFG->wwwroot   = getenv('MOODLE_URL') ?: 'http://localhost';
$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/var/www/moodledata';
$CFG->admin     = 'admin';

// Required when HTTPS terminates at Traefik/Coolify and internal Nginx sees HTTP.
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
    $pass = getenv('MOODLE_DBPASS') ?: getenv('POSTGRES_PASSWORD') ?: '';

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
    $pass = getenv('MOODLE_DBPASS') ?: getenv('POSTGRES_PASSWORD') ?: '';

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
  log "Waiting for PostgreSQL to become available..."
  attempts=0

  until database_is_reachable; do
    attempts=$((attempts + 1))

    if [ "$attempts" -ge 60 ]; then
      log "ERROR: PostgreSQL did not become available after 60 attempts."
      exit 1
    fi

    sleep 5
  done

  log "PostgreSQL is available."
}

upgrade_moodle_if_needed() {
  log "Running Moodle upgrade to install plugins and apply pending updates..."
  run_as_www_data php "$MOODLE_DIR/admin/cli/upgrade.php" --non-interactive
}

install_database_if_needed() {
  if [ "${MOODLE_AUTO_INSTALL:-true}" != "true" ]; then
    log "Automatic database install disabled for this container."
    return 0
  fi

  wait_for_database

  if database_is_installed; then
    log "Moodle database already exists. Skipping fresh install."

    touch "$MOODLE_DATAROOT/.moodle-installed"
    chown www-data:www-data "$MOODLE_DATAROOT/.moodle-installed"
    chmod 660 "$MOODLE_DATAROOT/.moodle-installed"

    fix_permissions
    upgrade_moodle_if_needed
    return 0
  fi

  log "Empty database detected. Installing Moodle automatically as www-data..."

  run_as_www_data php "$MOODLE_DIR/admin/cli/install_database.php" \
    --agree-license \
    --fullname="${MOODLE_FULLNAME:-Mboepi}" \
    --shortname="${MOODLE_SHORTNAME:-Mboepi}" \
    --adminuser="${MOODLE_ADMIN_USER:-admin}" \
    --adminpass="${MOODLE_ADMIN_PASSWORD}" \
    --adminemail="${MOODLE_ADMIN_EMAIL}"

  log "Database installation completed."

  touch "$MOODLE_DATAROOT/.moodle-installed"
  chown www-data:www-data "$MOODLE_DATAROOT/.moodle-installed"
  chmod 660 "$MOODLE_DATAROOT/.moodle-installed"

  fix_permissions
  upgrade_moodle_if_needed
}

validate_required_envs
validate_moodle_code_exists
fix_permissions
create_config_if_needed
install_database_if_needed

exec "$@"
