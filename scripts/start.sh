#!/bin/sh
set -eu

PORT_VALUE="${PORT:-10000}"
DB_TYPE_VALUE="${DB_TYPE:-sqlite}"
DB_FILE_VALUE="${DB_FILE:-database/clinic.db}"
AUTO_SETUP_DB_VALUE="${AUTO_SETUP_DB:-1}"

mkdir -p uploads uploads/photos uploads/home_uploads

if [ "$DB_TYPE_VALUE" = "sqlite" ]; then
    mkdir -p "$(dirname "$DB_FILE_VALUE")"
    if [ ! -f "$DB_FILE_VALUE" ]; then
        echo "Initializing SQLite database at $DB_FILE_VALUE"
        php setup_sqlite.php
    else
        # Self-heal partially initialized SQLite databases by checking core tables.
        if ! php -r "require 'config/config.php'; \$pdo = new PDO('sqlite:' . DB_FILE); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); \$required = ['users', 'doctors', 'reports', 'audit_logs']; foreach (\$required as \$table) { \$stmt = \$pdo->prepare(\"SELECT name FROM sqlite_master WHERE type='table' AND name=?\"); \$stmt->execute([\$table]); if (!\$stmt->fetchColumn()) { exit(1); } }"; then
            echo "Detected missing SQLite tables. Re-running setup_sqlite.php"
            php setup_sqlite.php
        fi
    fi
    php database/apply_performance_indexes.php || true
elif [ "$DB_TYPE_VALUE" = "mysql" ]; then
    if [ "$AUTO_SETUP_DB_VALUE" = "1" ]; then
        echo "Initializing MySQL database schema"
        php setup_database.php || true
    fi
fi

exec php -S 0.0.0.0:"$PORT_VALUE" -t .
