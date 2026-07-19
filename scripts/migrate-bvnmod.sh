#!/usr/bin/env bash
#
# Copy the BvnModification table from nimcweb (source) into this app (target).
# See NIMCWEB-MIGRATION.md section 8 for the findings behind this.
#
# Usage, on the VPS, from /var/www/verification_site:
#
#   export SRC_PW='<password from nimcweb .env DATABASE_URL>'
#   bash scripts/migrate-bvnmod.sh check
#   bash scripts/migrate-bvnmod.sh dump      # ~800MB, minutes; run under screen
#   bash scripts/migrate-bvnmod.sh load
#   bash scripts/migrate-bvnmod.sh verify
#   bash scripts/migrate-bvnmod.sh cleanup
#
# Safe to re-run: the insert is "on conflict (id) do nothing", so a second
# pass after cutover imports only the delta.
#
set -euo pipefail

TABLE='"BvnModification"'
STAGE='bvnmod_stage'
DUMP="$HOME/bvnmod.csv"     # psql \copy does NOT expand ~, so use an absolute path
EXPECT=1577                 # source count measured 2026-07-19

# --- connection details -----------------------------------------------------
# .env values can carry inline comments and quotes; strip them.
strip() {
  grep -E "^$1=" .env | head -1 | cut -d= -f2- \
    | sed -e 's/[[:space:]]*#.*$//' \
          -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
          -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

if [ ! -f .env ]; then
  echo "error: no .env here. Run from /var/www/verification_site." >&2
  exit 1
fi

DBN=$(strip DB_DATABASE)
DBU=$(strip DB_USERNAME)
DBP=$(strip DB_PASSWORD)
DBH=$(strip DB_HOST); DBH=${DBH:-127.0.0.1}

if [ -z "${SRC_PW:-}" ]; then
  echo "error: SRC_PW is not set (source password from nimcweb .env)." >&2
  exit 1
fi
SRC="postgresql://abcuser:${SRC_PW}@72.62.22.206:5432/abcweb"

# -w everywhere: fail loudly instead of prompting for a password.
tgt() { PGPASSWORD="$DBP" psql -h "$DBH" -U "$DBU" -d "$DBN" -w "$@"; }

# The source is live production. Server-enforced read-only, so even a typo
# in this script cannot write to it.
src() { PGOPTIONS='-c default_transaction_read_only=on' psql "$SRC" -w "$@"; }

# --- column list ------------------------------------------------------------
# Listed explicitly and in the same order on both sides, so neither table's
# physical column order matters.
COLS='id,"createdAt","updatedAt","oldBal","newBal","amountCharged"'
COLS="$COLS,bvn,nin,\"ninSlipUrl\",\"ninSlipImage\",\"serviceType\""
COLS="$COLS,\"oldFirstName\",\"oldMiddleName\",\"oldLastName\",\"oldDob\",\"oldPhoneNumber\""
COLS="$COLS,\"newFirstName\",\"newMiddleName\",\"newLastName\",\"newDob\",\"newPhoneNumber\""
COLS="$COLS,comment,status,\"userId\""

cmd_check() {
  echo "== connectivity"
  echo "source rows : $(src -Atc "select count(*) from $TABLE")"
  echo "target rows : $(tgt -Atc "select count(*) from $TABLE")"
  echo "target users: $(tgt -Atc 'select count(*) from users')"

  echo
  echo "== disk (need ~1GB free for the dump)"
  df -h "$HOME" | tail -1

  echo
  echo "== FK: source rows whose userId is missing from TARGET users"
  echo "   (must be 0, or those rows will be rejected)"
  src -Atc "select string_agg(distinct \"userId\", ',') from $TABLE" \
    | tr ',' '\n' | sort -u > /tmp/bvnmod-users.txt
  tgt -v ON_ERROR_STOP=1 -q \
    -c "drop table if exists bvnmod_userprobe" \
    -c "create unlogged table bvnmod_userprobe (id text)" \
    -c "\copy bvnmod_userprobe from '/tmp/bvnmod-users.txt'"
  echo "missing: $(tgt -Atc 'select count(*) from bvnmod_userprobe p
                             where not exists (select 1 from users u where u.id = p.id)')"
  tgt -q -c "drop table if exists bvnmod_userprobe"
  rm -f /tmp/bvnmod-users.txt
}

cmd_dump() {
  echo "dumping $TABLE to $DUMP ..."
  echo "(399MB table, ~800MB as CSV because bytea hex-encodes at 2x. Be patient.)"
  src -v ON_ERROR_STOP=1 \
      -c "\copy (select $COLS from $TABLE) to '$DUMP' with csv"
  ls -lh "$DUMP"
}

cmd_load() {
  [ -f "$DUMP" ] || { echo "error: $DUMP not found. Run 'dump' first." >&2; exit 1; }

  # Back up only if the target table already holds rows; on a virgin table
  # the rollback is just a delete.
  local have
  have=$(tgt -Atc "select count(*) from $TABLE")
  if [ "$have" != "0" ]; then
    local bk="$HOME/bvnmod-backup-$(date +%F-%H%M).dump"
    echo "target already has $have rows; backing up to $bk"
    PGPASSWORD="$DBP" pg_dump -h "$DBH" -U "$DBU" -d "$DBN" \
      -t "$TABLE" -Fc -f "$bk"
  fi

  tgt -v ON_ERROR_STOP=1 -q \
    -c "drop table if exists $STAGE" \
    -c "create unlogged table $STAGE (like $TABLE)"
  tgt -v ON_ERROR_STOP=1 -c "\copy $STAGE($COLS) from '$DUMP' with csv"

  echo "staged: $(tgt -Atc "select count(*) from $STAGE") (expect $EXPECT)"

  local orphans
  orphans=$(tgt -Atc "select count(*) from $STAGE s
                      where not exists (select 1 from users u where u.id = s.\"userId\")")
  if [ "$orphans" != "0" ]; then
    echo "ABORT: $orphans staged rows reference a userId not in target users." >&2
    echo "Import users first; stage table left in place for inspection." >&2
    exit 1
  fi

  tgt -v ON_ERROR_STOP=1 --single-transaction \
    -c "insert into $TABLE ($COLS) select $COLS from $STAGE on conflict (id) do nothing"
  echo "inserted."
}

cmd_verify() {
  echo "target rows: $(tgt -Atc "select count(*) from $TABLE") (expect $EXPECT)"
  echo "source rows: $(src -Atc "select count(*) from $TABLE")"
  echo
  echo "== status mix (target)"
  tgt -c "select status, count(*) from $TABLE group by 1 order by 2 desc"
  echo "== rows present on source but not on target (should be empty)"
  tgt -c "select count(*) from $STAGE s
          where not exists (select 1 from $TABLE b where b.id = s.id)" 2>/dev/null \
    || echo "(stage already dropped)"
  echo "== image payloads landed intact?"
  tgt -c "select count(*) as rows,
                 pg_size_pretty(sum(octet_length(\"ninSlipImage\"))::bigint) as image_bytes,
                 count(*) filter (where octet_length(\"ninSlipImage\") = 0) as empty
          from $TABLE"
  echo "source was: 383 MB across 1577 rows, 1 empty"
}

cmd_cleanup() {
  tgt -q -c "drop table if exists $STAGE"
  rm -f "$DUMP"
  echo "stage table dropped, $DUMP removed."
}

case "${1:-}" in
  check)   cmd_check   ;;
  dump)    cmd_dump    ;;
  load)    cmd_load    ;;
  verify)  cmd_verify  ;;
  cleanup) cmd_cleanup ;;
  *) sed -n '3,20p' "$0"; exit 1 ;;
esac
