#!/bin/sh
set -e

MOODLE_DIR=${MOODLE_DIR:-/var/www/moodle}
MOODLE_DATAROOT=${MOODLE_DATAROOT:-/var/www/moodledata}

mkdir -p "$MOODLE_DATAROOT"

chown -R www-data:www-data "$MOODLE_DATAROOT"
chown -R www-data:www-data "$MOODLE_DIR"

if [ ! -f "$MOODLE_DIR/config.php" ]; then
cat > "$MOODLE_DIR/config.php" <<EOF
<?php
unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = getenv('MOODLE_DBTYPE') ?: 'pgsql';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = getenv('MOODLE_DBHOST') ?: 'postgres';
\$CFG->dbname    = getenv('MOODLE_DBNAME') ?: 'moodle';
\$CFG->dbuser    = getenv('MOODLE_DBUSER') ?: 'moodleuser';
\$CFG->dbpass    = getenv('MOODLE_DBPASS') ?: '';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => getenv('MOODLE_DBPORT') ?: '5432',
  'dbsocket' => '',
);

\$CFG->wwwroot   = getenv('MOODLE_URL') ?: 'http://localhost';
\$CFG->dataroot  = getenv('MOODLE_DATAROOT') ?: '/var/www/moodledata';
\$CFG->admin     = 'admin';

\$CFG->directorypermissions = 0770;

require_once(__DIR__ . '/lib/setup.php');
EOF

chown www-data:www-data "$MOODLE_DIR/config.php"
fi

exec "$@"