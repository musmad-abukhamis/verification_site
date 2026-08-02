#!/usr/bin/env bash
#
# Generic table copy: nimcweb (source) -> this app (target).
# See NIMCWEB-MIGRATION.md. Companion to migrate-bvnmod.sh, which stays
# separate because its 400MB of bytea needs the spool-to-disk treatment.
#
# Usage, on the VPS, run from the TARGET app's root (the dir holding its .env --
# target credentials are always read from ./.env at runtime, never hardcoded):
#
#   export SRC_PW='<password from nimcweb .env DATABASE_URL>'   # nimcweb -> abcweb
#   bash scripts/migrate-table.sh bvnRetrieval check
#   bash scripts/migrate-table.sh bvnRetrieval copy
#   bash scripts/migrate-table.sh bvnRetrieval verify
#
# For any other source database, give the whole connection string instead:
#
#   export SRC_URI='postgresql://vtuuser:PASS@72.62.22.206:5432/vtuportal'
#   bash scripts/migrate-table.sh users check
#
# Table names are case-sensitive and passed unquoted: bvnRetrieval, NINDetails,
# Transactions, wallethistory, Pin.
#
# The column list is computed as the INTERSECTION of source and target columns,
# in source order, so target-only columns (remember_token) and any source-only
# column are skipped rather than breaking the copy. Anything skipped is printed
# by 'check' -- read that output, do not assume it is harmless.
#
# Two optional env vars, for tables whose primary key is a SERIAL rather than a
# cuid (Record). Carrying integer ids across would collide with ids the target's
# own sequence already issued, and 'on conflict do nothing' would silently drop
# those rows:
#
#   EXCLUDE_COLS='id'          drop these columns; the target sequence assigns
#   CONFLICT='(ticket_id)'     conflict target -- the real natural key
#
#   EXCLUDE_COLS='id' CONFLICT='(ticket_id)' bash scripts/migrate-table.sh Record copy
#
# Only safe when nothing references the discarded ids. Verify with:
#   select count(*) from information_schema.constraint_column_usage cu
#     join information_schema.table_constraints tc using (constraint_name)
#    where cu.table_name='<T>' and tc.constraint_type='FOREIGN KEY';
#
# Safe to re-run: insert is "on conflict do nothing", so a second pass after
# cutover imports only the delta.
#
set -euo pipefail

TABLE="${1:-}"
ACTION="${2:-}"
if [ -z "$TABLE" ] || [ -z "$ACTION" ]; then
  sed -n '3,22p' "$0"; exit 1
fi

QT="\"$TABLE\""
STAGE="stage_$(echo "$TABLE" | tr -cd '[:alnum:]_' | tr '[:upper:]' '[:lower:]')"

[ -f .env ] || { echo "error: no .env in $(pwd). Run from the target app's root." >&2; exit 1; }
if [ -z "${SRC_URI:-}" ] && [ -z "${SRC_PW:-}" ]; then
  echo "error: set SRC_URI (full source connection string) or SRC_PW." >&2; exit 1
fi

strip() {
  grep -E "^$1=" .env | head -1 | cut -d= -f2- \
    | sed -e 's/[[:space:]]*#.*$//' \
          -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
          -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}
DBN=$(strip DB_DATABASE); DBU=$(strip DB_USERNAME); DBP=$(strip DB_PASSWORD)
DBH=$(strip DB_HOST); DBH=${DBH:-127.0.0.1}
# SRC_URI wins when set, so the script serves more than one source database
# (nimcweb/abcweb originally; vtuportal for the softel.ng -> myvtu copy).
# SRC_PW alone keeps the original nimcweb behaviour working unchanged.
SRC="${SRC_URI:-postgresql://abcuser:${SRC_PW}@72.62.22.206:5432/abcweb}"

tgt() { PGPASSWORD="$DBP" psql -h "$DBH" -U "$DBU" -d "$DBN" -w "$@"; }
# Source is live production: server-enforced read-only.
src() { PGOPTIONS='-c default_transaction_read_only=on' psql "$SRC" -w "$@"; }

cols_of() {   # $1 = src|tgt -- bare column names, source ordinal order
  local q="select column_name from information_schema.columns
           where table_schema='public' and table_name='$TABLE'
           order by ordinal_position"
  if [ "$1" = src ]; then src -Atc "$q"; else tgt -Atc "$q"; fi
}

build_cols() {
  local s t
  s=$(cols_of src); t=$(cols_of tgt)
  [ -n "$s" ] || { echo "error: table $TABLE not found on SOURCE." >&2; exit 1; }
  [ -n "$t" ] || { echo "error: table $TABLE not found on TARGET." >&2; exit 1; }
  ONLY_SRC=$(comm -23 <(echo "$s" | sort) <(echo "$t" | sort) | paste -sd, -)
  ONLY_TGT=$(comm -13 <(echo "$s" | sort) <(echo "$t" | sort) | paste -sd, -)
  # keep source order, quote every identifier for camelCase
  local keep
  keep=$(echo "$s" | grep -Fxf <(echo "$t"))
  if [ -n "${EXCLUDE_COLS:-}" ]; then
    # comma-separated -> one per line, then drop them
    keep=$(echo "$keep" | grep -Fxv -f <(echo "$EXCLUDE_COLS" | tr ',' '\n'))
    EXCLUDED="$EXCLUDE_COLS"
  fi
  COLS=$(echo "$keep" | sed 's/.*/"&"/' | paste -sd, -)
  NCOLS=$(echo "$keep" | grep -c .)
}

case "$ACTION" in

check)
  build_cols
  echo "== $TABLE"
  echo "source rows : $(src -Atc "select count(*) from $QT")"
  echo "target rows : $(tgt -Atc "select count(*) from $QT")"
  echo "size on src : $(src -Atc "select pg_size_pretty(pg_total_relation_size('$QT'))")"
  echo "columns     : $NCOLS copied"
  [ -n "$ONLY_SRC" ] && echo "  !! source-only, WILL BE DROPPED: $ONLY_SRC"
  [ -n "$ONLY_TGT" ] && echo "  -- target-only, left at default: $ONLY_TGT"
  [ -n "${EXCLUDED:-}" ] && echo "  -- excluded by request: $EXCLUDED (target assigns)"
  echo "conflict    : ${CONFLICT:-<any unique constraint>} do nothing"

  if [ "$(tgt -Atc "select to_regclass('public.mt_user_map') is not null")" = "t" ]; then
    echo "identity map: LOADED ($(tgt -Atc 'select count(*) from mt_user_map') entries,"
    echo "              $(tgt -Atc 'select count(*) from mt_user_map where tgt_id is null') marked skip)"
    if [ "$TABLE" = users ]; then
      echo "  users affected: $(src -Atc "select count(*) from $QT" ) source rows, of which"
      src -Atc "select id from $QT" > /tmp/mt-ids.txt
      tgt -q -c "drop table if exists mt_idprobe" \
             -c "create unlogged table mt_idprobe (id text)" \
             -c "\copy mt_idprobe from '/tmp/mt-ids.txt'"
      echo "  $(tgt -Atc 'select count(*) from mt_idprobe p join mt_user_map m on m.src_id=p.id') will be removed from the stage"
      tgt -q -c "drop table if exists mt_idprobe"; rm -f /tmp/mt-ids.txt
    elif echo "$COLS" | grep -q '"userId"'; then
      # Count on the source, using the map's ids passed in as a simple IN list.
      skip=$(tgt -Atc "select string_agg(quote_literal(src_id), ',') from mt_user_map where tgt_id is null")
      move=$(tgt -Atc "select string_agg(quote_literal(src_id), ',') from mt_user_map where tgt_id is not null")
      [ -n "$skip" ] && echo "  rows to DROP  : $(src -Atc "select count(*) from $QT where \"userId\" in ($skip)")"
      [ -n "$move" ] && echo "  rows to REMAP : $(src -Atc "select count(*) from $QT where \"userId\" in ($move)")"
    fi
  else
    echo "identity map: none loaded -- plain copy"
  fi

  # FK precheck against TARGET users, if this table has a userId
  if echo "$COLS" | grep -q '"userId"'; then
    echo
    echo "== FK: userIds missing from TARGET users (must be 0)"
    src -Atc "select distinct \"userId\" from $QT" > /tmp/mt-users.txt
    tgt -q -c "drop table if exists mt_userprobe" \
           -c "create unlogged table mt_userprobe (id text)" \
           -c "\copy mt_userprobe from '/tmp/mt-users.txt'"
    echo "missing: $(tgt -Atc 'select count(*) from mt_userprobe p
                               where not exists (select 1 from users u where u.id = p.id)')"
    tgt -q -c "drop table if exists mt_userprobe"
    rm -f /tmp/mt-users.txt
  fi
  ;;

copy)
  build_cols
  have=$(tgt -Atc "select count(*) from $QT")
  if [ "$have" != "0" ]; then
    bk="$HOME/${STAGE}-backup-$(date +%F-%H%M).dump"
    echo "target has $have rows; backing up to $bk"
    PGPASSWORD="$DBP" pg_dump -h "$DBH" -U "$DBU" -d "$DBN" -t "$QT" -Fc -f "$bk"
  fi

  # "like" carries NOT NULL but not defaults, so a stage table built that way
  # has an id column that is NOT NULL with no sequence -- unusable when we are
  # deliberately not copying id. Build from the column list instead.
  if [ -n "${EXCLUDE_COLS:-}" ]; then
    tgt -v ON_ERROR_STOP=1 -q \
      -c "drop table if exists $STAGE" \
      -c "create unlogged table $STAGE as select $COLS from $QT with no data"
  else
    tgt -v ON_ERROR_STOP=1 -q \
      -c "drop table if exists $STAGE" \
      -c "create unlogged table $STAGE (like $QT)"
  fi

  src -v ON_ERROR_STOP=1 -c "\copy (select $COLS from $QT) to stdout with csv" \
    | tgt -v ON_ERROR_STOP=1 -c "\copy $STAGE($COLS) from stdin with csv"

  staged=$(tgt -Atc "select count(*) from $STAGE")
  echo "staged: $staged"

  # Identity map, if one has been loaded on the target (see
  # scripts/vtuportal-user-map.sql). Applied to the STAGE table only, so the
  # source is never touched and a bad map is undone by re-staging.
  if [ "$(tgt -Atc "select to_regclass('public.mt_user_map') is not null")" = "t" ]; then
    if [ "$TABLE" = users ]; then
      # Mapped source accounts must not be inserted: the merged ones already
      # exist on the target, the skipped ones are deliberately left behind.
      gone=$(tgt -Atc "with d as (delete from $STAGE s using mt_user_map m
                                   where m.src_id = s.id returning 1)
                       select count(*) from d")
      echo "identity map: removed $gone source user(s) from the stage"
    elif echo "$COLS" | grep -q '"userId"'; then
      gone=$(tgt -Atc "with d as (delete from $STAGE s using mt_user_map m
                                   where m.src_id = s.\"userId\" and m.tgt_id is null returning 1)
                       select count(*) from d")
      moved=$(tgt -Atc "with u as (update $STAGE s set \"userId\" = m.tgt_id from mt_user_map m
                                    where m.src_id = s.\"userId\" and m.tgt_id is not null returning 1)
                        select count(*) from u")
      echo "identity map: dropped $gone row(s), remapped $moved row(s) onto merged accounts"
    fi
    staged=$(tgt -Atc "select count(*) from $STAGE")
    echo "staged after map: $staged"
  fi

  if echo "$COLS" | grep -q '"userId"'; then
    orphans=$(tgt -Atc "select count(*) from $STAGE s
                        where not exists (select 1 from users u where u.id = s.\"userId\")")
    if [ "$orphans" != "0" ]; then
      echo "ABORT: $orphans staged rows reference a userId absent from target users." >&2
      echo "Stage table $STAGE left in place for inspection." >&2
      exit 1
    fi
  fi

  # Measure the insert rather than trusting it. "on conflict do nothing" is
  # silent by design, and a silent skip on a parent table strands every child
  # row that references it -- the single most expensive failure mode here.
  before=$(tgt -Atc "select count(*) from $QT")
  tgt -v ON_ERROR_STOP=1 --single-transaction \
    -c "insert into $QT ($COLS) select $COLS from $STAGE
        on conflict ${CONFLICT:-} do nothing"
  after=$(tgt -Atc "select count(*) from $QT")
  ins=$((after - before))

  echo "rows: before=$before after=$after inserted=$ins (staged $staged)"
  if [ "$ins" != "$staged" ]; then
    echo
    echo "WARNING: $((staged - ins)) staged row(s) did NOT land -- skipped by the"
    echo "conflict clause. Expected on a re-run (delta import); NOT expected on a"
    echo "first pass. Investigate before importing any child table:"
    echo "  select * from $STAGE s where not exists"
    echo "    (select 1 from $QT b where b.id = s.id);"
  fi
  ;;

verify)
  echo "source rows: $(src -Atc "select count(*) from $QT")"
  echo "target rows: $(tgt -Atc "select count(*) from $QT")"
  echo
  # Match on the conflict key when one was given (id is not carried in that
  # case), otherwise on the primary key id.
  key=$(echo "${CONFLICT:-(id)}" | tr -d '()')
  echo "== rows staged but not landed, matched on $key (should be 0)"
  tgt -Atc "select count(*) from $STAGE s
            where not exists (select 1 from $QT b where b.\"$key\" = s.\"$key\")" 2>/dev/null \
    || echo "(no stage table)"
  ;;

cleanup)
  tgt -q -c "drop table if exists $STAGE"
  echo "dropped $STAGE."
  ;;

*) echo "unknown action '$ACTION' (check|copy|verify|cleanup)" >&2; exit 1 ;;
esac
