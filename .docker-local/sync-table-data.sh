#!/bin/bash
#
# Sync table data from .sql.gz dumps into MySQL.
# Inserts only missing rows by default (INSERT IGNORE), avoiding FK issues.
#
# Usage:
#   ./sync-table-data.sh <table_name> [database] [--replace] [--all-tenants]
#
# Examples:
#   ./sync-table-data.sh permissions                        # insert missing into tenant_dixies
#   ./sync-table-data.sh permissions tenant_kens             # insert missing into tenant_kens
#   ./sync-table-data.sh permissions --all-tenants           # insert missing into all tenant DBs
#   ./sync-table-data.sh permissions --replace               # replace existing + insert missing
#   ./sync-table-data.sh roles --all-tenants --replace       # replace in all tenant DBs

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="${SCRIPT_DIR}/data-updates"

MYSQL_USER="${MYSQL_USER:-nat_user}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-nat_password}"

MODE="ignore"
ALL_TENANTS=false
TABLE_NAME=""
DATABASE=""

# Parse arguments
for arg in "$@"; do
    case "$arg" in
        --replace)
            MODE="replace"
            ;;
        --all-tenants)
            ALL_TENANTS=true
            ;;
        --help|-h)
            echo "Usage: $0 <table_name> [database] [--replace] [--all-tenants]"
            echo ""
            echo "Looks for <table_name>.sql.gz in ${DATA_DIR}/"
            echo "Extracts INSERT statements and runs them against the target database."
            echo ""
            echo "Options:"
            echo "  --replace       Use REPLACE INTO instead of INSERT IGNORE"
            echo "                  (overwrites existing rows that match by primary/unique key)"
            echo "  --all-tenants   Apply to all tenant databases found in nat_central.tenants"
            echo "  --help          Show this help message"
            echo ""
            echo "Defaults:"
            echo "  database:  tenant_dixies"
            echo "  mode:      INSERT IGNORE (skip existing rows)"
            exit 0
            ;;
        -*)
            echo "Error: Unknown option: ${arg}"
            echo "Run '$0 --help' for usage."
            exit 1
            ;;
        *)
            if [ -z "$TABLE_NAME" ]; then
                TABLE_NAME="$arg"
            elif [ -z "$DATABASE" ]; then
                DATABASE="$arg"
            else
                echo "Error: Unexpected argument: ${arg}"
                exit 1
            fi
            ;;
    esac
done

if [ -z "$TABLE_NAME" ]; then
    echo "Error: table name is required."
    echo "Run '$0 --help' for usage."
    exit 1
fi

SQL_FILE="${DATA_DIR}/${TABLE_NAME}.sql.gz"

if [ ! -f "$SQL_FILE" ]; then
    echo "Error: File not found: ${SQL_FILE}"
    echo ""
    echo "Available data files:"
    ls -1 "${DATA_DIR}"/*.sql.gz 2>/dev/null | sed 's|.*/||' || echo "  (none)"
    exit 1
fi

mysql_cmd() {
    docker exec -i nat-mysql mysql -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" --silent "$@" 2>/dev/null
}

# Build the list of target databases
DATABASES=()
if [ "$ALL_TENANTS" = true ]; then
    while IFS= read -r db; do
        DATABASES+=("$db")
    done < <(mysql_cmd -N -e "SELECT CONCAT('tenant_', id) FROM tenants" nat_central)

    if [ ${#DATABASES[@]} -eq 0 ]; then
        echo "Error: No tenants found in nat_central.tenants"
        exit 1
    fi
    echo "Found ${#DATABASES[@]} tenant database(s): ${DATABASES[*]}"
elif [ -n "$DATABASE" ]; then
    DATABASES+=("$DATABASE")
else
    DATABASES+=("tenant_dixies")
fi

# Determine sed replacement based on mode
if [ "$MODE" = "replace" ]; then
    SED_REPLACE='s/^INSERT INTO/REPLACE INTO/'
    MODE_LABEL="REPLACE INTO (overwrite existing)"
else
    SED_REPLACE='s/^INSERT INTO/INSERT IGNORE INTO/'
    MODE_LABEL="INSERT IGNORE (skip existing)"
fi

# Extract INSERT statements from the dump once and reuse
INSERT_SQL=$(zcat "$SQL_FILE" | sed -n '/^INSERT INTO/,/;$/p' | sed "$SED_REPLACE")

if [ -z "$INSERT_SQL" ]; then
    echo "Error: No INSERT statements found in ${SQL_FILE}"
    exit 1
fi

echo "========================================"
echo "Syncing table: ${TABLE_NAME}"
echo "Source:        ${SQL_FILE}"
echo "Mode:          ${MODE_LABEL}"
echo "========================================"
echo ""

TOTAL_ADDED=0

for DB in "${DATABASES[@]}"; do
    # Verify table exists in the target database
    TABLE_EXISTS=$(mysql_cmd -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB}' AND table_name='${TABLE_NAME}'" 2>/dev/null)
    if [ "$TABLE_EXISTS" != "1" ]; then
        echo "[${DB}] SKIP - table '${TABLE_NAME}' does not exist"
        continue
    fi

    BEFORE=$(mysql_cmd -N -e "SELECT COUNT(*) FROM \`${TABLE_NAME}\`" "$DB")

    echo "$INSERT_SQL" | mysql_cmd "$DB"

    AFTER=$(mysql_cmd -N -e "SELECT COUNT(*) FROM \`${TABLE_NAME}\`" "$DB")
    ADDED=$((AFTER - BEFORE))
    TOTAL_ADDED=$((TOTAL_ADDED + ADDED))

    if [ "$MODE" = "replace" ]; then
        echo "[${DB}] done - rows: ${BEFORE} -> ${AFTER} (${ADDED} net new)"
    else
        echo "[${DB}] done - rows: ${BEFORE} -> ${AFTER} (+${ADDED} added)"
    fi
done

echo ""
echo "========================================"
echo "Complete. Total new rows added: ${TOTAL_ADDED}"
echo "========================================"
