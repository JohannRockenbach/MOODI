#!/usr/bin/env bash

set -euo pipefail

# Idempotent PostgreSQL test DB bootstrap for Laravel/Pest.
# Creates (or reuses) role and DB, and resets public schema privileges.

if [[ -f ".env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
fi

if [[ -f ".env.testing" ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env.testing
  set +a
fi

TEST_DB="${DB_TEST_DATABASE:-moodi_test}"
TEST_USER="${DB_TEST_USERNAME:-${DB_USERNAME:-postgres}}"
TEST_PASS="${DB_TEST_PASSWORD:-${DB_PASSWORD:-}}"
TEST_HOST="${DB_TEST_HOST:-${DB_HOST:-127.0.0.1}}"
TEST_PORT="${DB_TEST_PORT:-${DB_PORT:-5432}}"

ADMIN_USER="${DB_ADMIN_USERNAME:-${DB_TEST_USERNAME:-${DB_USERNAME:-postgres}}}"
ADMIN_PASS="${DB_ADMIN_PASSWORD:-${DB_TEST_PASSWORD:-${DB_PASSWORD:-}}}"
ADMIN_DB="${DB_ADMIN_DATABASE:-postgres}"

for value in "$TEST_DB" "$TEST_USER" "$ADMIN_USER"; do
  if [[ ! "$value" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "❌ Valor inválido para identificador SQL: $value" >&2
    echo "   Solo se permiten letras, números y guión bajo." >&2
    exit 1
  fi
done

escaped_test_pass="${TEST_PASS//\'/\'\'}"

export PGPASSWORD="$ADMIN_PASS"

if [[ -z "$ADMIN_PASS" ]]; then
  echo "⚠️  DB_ADMIN_PASSWORD/DB_TEST_PASSWORD/DB_PASSWORD vacío." >&2
  echo "   Si tu Postgres exige contraseña, exportala antes de ejecutar este script." >&2
fi

psql_admin=(psql -v ON_ERROR_STOP=1 -h "$TEST_HOST" -p "$TEST_PORT" -U "$ADMIN_USER" -d "$ADMIN_DB")
psql_testdb=(psql -v ON_ERROR_STOP=1 -h "$TEST_HOST" -p "$TEST_PORT" -U "$ADMIN_USER" -d "$TEST_DB")

echo "🔎 Verificando rol de test: $TEST_USER"
role_exists="$(${psql_admin[@]} -tAc "SELECT 1 FROM pg_roles WHERE rolname='${TEST_USER}'")"

if [[ -z "$role_exists" ]]; then
  can_create_role="$(${psql_admin[@]} -tAc "SELECT CASE WHEN rolsuper OR rolcreaterole THEN 1 ELSE 0 END FROM pg_roles WHERE rolname=current_user")"

  if [[ "$can_create_role" == "1" ]]; then
    echo "➕ Creando rol de test: $TEST_USER"
    ${psql_admin[@]} -c "CREATE ROLE \"${TEST_USER}\" LOGIN PASSWORD '${escaped_test_pass}';"
  else
    echo "❌ El rol ${TEST_USER} no existe y ${ADMIN_USER} no tiene CREATEROLE/superuser." >&2
    echo "   Solución: crear el rol manualmente con un usuario administrador." >&2
    exit 1
  fi
else
  echo "✅ Rol ${TEST_USER} ya existe"
fi

echo "🔎 Verificando base de test: $TEST_DB"
db_exists="$(${psql_admin[@]} -tAc "SELECT 1 FROM pg_database WHERE datname='${TEST_DB}'")"

if [[ -z "$db_exists" ]]; then
  echo "➕ Creando base de test: $TEST_DB"
  ${psql_admin[@]} -c "CREATE DATABASE \"${TEST_DB}\" OWNER \"${TEST_USER}\" TEMPLATE template0 ENCODING 'UTF8';"
else
  echo "✅ Base ${TEST_DB} ya existe"
fi

echo "🔐 Ajustando permisos mínimos sobre la base"
${psql_admin[@]} -c "REVOKE ALL ON DATABASE \"${TEST_DB}\" FROM PUBLIC;"
${psql_admin[@]} -c "GRANT CONNECT, TEMP ON DATABASE \"${TEST_DB}\" TO \"${TEST_USER}\";"

echo "🧹 Reinicializando schema public de forma idempotente"
${psql_testdb[@]} -c "DROP SCHEMA IF EXISTS public CASCADE;"
${psql_testdb[@]} -c "CREATE SCHEMA public AUTHORIZATION \"${TEST_USER}\";"
${psql_testdb[@]} -c "GRANT USAGE, CREATE ON SCHEMA public TO \"${TEST_USER}\";"
${psql_testdb[@]} -c "ALTER DEFAULT PRIVILEGES FOR ROLE \"${TEST_USER}\" IN SCHEMA public GRANT ALL ON TABLES TO \"${TEST_USER}\";"
${psql_testdb[@]} -c "ALTER DEFAULT PRIVILEGES FOR ROLE \"${TEST_USER}\" IN SCHEMA public GRANT ALL ON SEQUENCES TO \"${TEST_USER}\";"

echo "✅ Bootstrap de PostgreSQL para tests listo"
echo "   DB: ${TEST_DB} | User: ${TEST_USER} | Host: ${TEST_HOST}:${TEST_PORT}"
