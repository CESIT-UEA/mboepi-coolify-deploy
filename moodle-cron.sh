#!/bin/sh
set -eu

MOODLE_DIR="${MOODLE_DIR:-/var/www/moodle}"
MOODLE_DATAROOT="${MOODLE_DATAROOT:-/var/www/moodledata}"
CRON_INTERVAL="${MOODLE_CRON_INTERVAL:-300}"

log() {
  printf '%s\n' "$*"
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
    exit(1);
}
PHP
}

log "Aguardando instalação do banco do Moodle..."

attempts=0
until database_is_installed; do
  attempts=$((attempts + 1))
  if [ "$attempts" -ge 120 ]; then
    log "ERRO: Banco do Moodle ainda não possui tabelas após 120 tentativas."
    exit 1
  fi
  sleep 10
done

log "Banco do Moodle instalado. Iniciando cron."

while true; do
  if [ -f "$MOODLE_DIR/admin/cli/cron.php" ]; then
    php "$MOODLE_DIR/admin/cli/cron.php" -f || true
  else
    log "ERRO: cron.php não encontrado em $MOODLE_DIR/admin/cli/cron.php"
    find "$MOODLE_DIR" -path "*/admin/cli/cron.php" || true
  fi
  sleep "$CRON_INTERVAL"
done
