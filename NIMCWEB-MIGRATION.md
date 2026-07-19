# nimcweb → verification-site data migration

Working notes for migrating data out of the legacy Next.js/Prisma app (**nimcweb**)
into this Laravel app, table by table. Started 2026-07-18.

> **Credentials are deliberately not in this file.** The target's live in
> `/var/www/verification_site/.env` on the VPS; the source's in `nimcweb/.env`
> (`DATABASE_URL`). Every script below reads them at runtime.

---

## 1. The two systems

|                | source (nimcweb)              | target (this app)                  |
| -------------- | ----------------------------- | ---------------------------------- |
| stack          | Next.js + Prisma              | Laravel 12 + Inertia/Vue           |
| host           | `72.62.22.206`                | `185.255.94.216` (`abc.softel.ng`) |
| database       | `abcweb`                      | `abcweb`                           |
| db user        | `abcuser`                     | `abcuser`                          |
| Postgres reach | **public** (accepts external) | **localhost-only**                 |

**The two databases share a name, username, and password but are different
servers.** This caused real confusion — verify, don't assume. The decisive test:

```sql
select count(*) from information_schema.tables
 where table_schema='public' and table_name in ('migrations','sessions','cache','jobs');
-- target = 4, source = 0
```

Because target Postgres is localhost-only and SSH requires a password, **all
target-side work must be run by hand on the VPS.** The source can be queried
from anywhere.

## 2. Schema compatibility

This app's migrations are a faithful port of nimcweb's Prisma schema — same
table names, same columns, including camelCase (`createdAt`, `isTwoFactorEnabled`)
and Prisma's `emailVerified → email_verified` mapping. **Migration is a data copy,
not a transformation.** Two deltas only:

- `role` is a Postgres enum on source, `varchar` on target → cast `role::text`.
- `remember_token` is target-only and nullable → omit from the column list.

Primary keys are cuid strings, so **no sequences to resync** and source ids
transfer unchanged — which keeps child-table imports simple.

Passwords are bcrypt `$2a$`/`$2b$` (bcryptjs). PHP's `password_verify` accepts
both, so migrated users log in with existing passwords.

## 3. Shell gotchas that cost time

- `.env` values carry **inline comments** (`DB_PASSWORD=xxx   # strong password`).
  A naive `cut -d= -f2-` swallows the comment. Use the `strip()` helper below.
- The VPS **SSH session drops between commands** — shell variables do not
  survive. Put setup and work in a *single paste*.
- Always pass `psql -w` so a bad password fails loudly instead of prompting.

```bash
strip() { grep -E "^$1=" .env | head -1 | cut -d= -f2- \
  | sed -e 's/[[:space:]]*#.*$//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
        -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; }
```

## 4. Source protection

The source is a live production database and must never be written to. Every
source connection uses server-enforced read-only, so even a typo cannot write:

```bash
src() { PGOPTIONS='-c default_transaction_read_only=on' psql "$SRC" -w "$@"; }
```

---

## 5. users table — READY TO RUN, not yet executed

### Findings (measured 2026-07-18)

- Source: **2253 users** (was 2252 an hour earlier — **the source is live**,
  ~7 signups/day). 3 `ADMIN`, rest `USER`.
- Target: **4 users** (1 seeded admin, 3 real signups from 2026-07-08).
- **Overlap: exactly 1 user, ₦0 at stake.**
  `yauyusufsaeed@gmail.com` exists on both (target `zaks` / source `zaksdk`),
  colliding on email only. The source account has **zero child rows** across
  every table, so skipping it costs nothing and orphans nothing.
- Money on source: **₦612,248 across 731 users**; largest single balance ₦12,200.

### Decision

Plain copy with `on conflict do nothing`. Expected result: 2252 inserted,
1 skipped, **2256 total**. None of the merge/ledger machinery is needed because
the single collision carries no balance and no history.

### The script (run on the VPS, single paste)

```bash
cd /var/www/verification_site

strip() { grep -E "^$1=" .env | head -1 | cut -d= -f2- \
  | sed -e 's/[[:space:]]*#.*$//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
        -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; }
DBN=$(strip DB_DATABASE); DBU=$(strip DB_USERNAME); DBP=$(strip DB_PASSWORD)
DBH=$(strip DB_HOST); DBH=${DBH:-127.0.0.1}

SRC_PW='<from nimcweb/.env DATABASE_URL>'
SRC="postgresql://abcuser:$SRC_PW@72.62.22.206:5432/abcweb"
COLS='id,name,email,phone,balance,username,email_verified,password,role,image,apitoken,"isTwoFactorEnabled","createdAt","updatedAt"'

tgt() { PGPASSWORD="$DBP" psql -h "$DBH" -U "$DBU" -d "$DBN" -w "$@"; }
src() { PGOPTIONS='-c default_transaction_read_only=on' psql "$SRC" -w "$@"; }

# 1. backup target
PGPASSWORD="$DBP" pg_dump -h "$DBH" -U "$DBU" -d "$DBN" -t users -Fc \
  -f ~/users-backup-$(date +%F-%H%M).dump && echo "backup written to ~/"

# 2. stage source rows
tgt -c "drop table if exists users_stage" -c "create unlogged table users_stage (like users)"
src -c "\copy (select id,name,email,phone,balance,username,email_verified,password,role::text,image,apitoken,\"isTwoFactorEnabled\",\"createdAt\",\"updatedAt\" from users) to stdout with csv" \
| tgt -c "\copy users_stage($COLS) from stdin with csv"
echo "staged: $(tgt -Atc 'select count(*) from users_stage')"

# 3. insert, skipping unique collisions
tgt --single-transaction -c "insert into users ($COLS) select $COLS from users_stage on conflict do nothing"

# 4. verify + list what was skipped
echo "users now: $(tgt -Atc 'select count(*) from users')"
tgt -c "select s.email, s.username, s.balance from users_stage s
        where not exists (select 1 from users u where u.id = s.id)"

# 5. cleanup
tgt -c "drop table if exists users_stage" -c "drop table if exists users_probe"
```

Rollback: `pg_restore -d "$DBN" -t users --clean ~/users-backup-<timestamp>.dump`

---

## 6. Data-quality issues found (apply to later tables too)

**Phone formats defeat unique constraints.** Source phone formats: `0…` 2209,
other 28, `+234…` 15, `234…` 1. Postgres unique is byte-exact, so
`08012345678` and `+2348012345678` are the same human but do **not** collide.
The source already contains **15 self-duplicates** by this measure. Any
duplicate detection must normalise:

```sql
right(regexp_replace(phone,'[^0-9]','','g'),10)   -- phone
lower(email)                                       -- email (0 dupes in source)
```

**`users.balance` is not the source of truth on this app.** `app/Services/WalletLedger.php`
maintains it as a materialisation of the `wallet_entries` ledger, and
`php artisan data:ledger-check` asserts the latest `balance_after` equals
`users.balance`. **Never move money with raw SQL** — it writes no ledger row and
trips the integrity check. Use `WalletLedger::credit()` from an artisan command.

**All 2253 source users have `email_verified = NULL`.** `App\Models\User`
implements `MustVerifyEmailContract` (`hasVerifiedEmail()` → `email_verified is not null`).
No route currently uses `verified` middleware, so this is dormant — but the day
one does, every migrated user is locked out at once.

**`balance` is `double precision`** (float) in both schemas and cast `'float'` in
the model. Expect sub-kobo drift when reconciling totals; not an import bug.

---

## 7. Remaining tables

Import in FK order, rewriting nothing (source ids transfer as-is):

| order | table             | source rows |
| ----- | ----------------- | ----------- |
| 1     | `users`           | 2253        |
| 2     | `accounts`        | 0           |
| 2     | `Pin`             | 976         |
| 2     | `OTP`             | —           |
| 2     | `TwoFactorConfirmation` | —     |
| 3     | `wallethistory`   | 5118        |
| 3     | `Transactions`    | 454         |
| 4     | `NINDetails`      | 9173        |
| 4     | `BvnModification` | 1573        |
| 4     | `IdCard`          | 379         |
| 4     | `bvnsdkform`, `bvnRetrieval` | —  |
| 5     | `Notification`, `NotificationUser` | — |

The one skipped user (`zaksdk`) has no child rows, so no id remapping is needed
anywhere. **If a future table import skips a parent, that changes** — child rows
would reference a missing id and the FK would reject them.

## 8. BvnModification table

### Findings (measured 2026-07-19)

- Source: **1577 rows** (§7 said 1573 on 2026-07-18 — the source is still taking
  submissions; newest row is same-day). Range 2025-06-27 → now.
- Status mix: `modified` 1301, `rejected` 201, `refunded` 46, `picked` 25,
  `pending` 4.
- **Orphan `userId`: 0.** Every row resolves against source `users`, so with the
  users copy done the FK is satisfied without any id remapping.
- **399 MB table, 383 MB of it the `ninSlipImage` bytea** (avg 243 KB, max 1 MB;
  exactly 1 row has a zero-length image). This is the first table where transfer
  size dictates method.
- All target `string(255)` columns are safe — longest source values are `bvn` 14,
  `nin` 13, `userId`/`id` 25. `ninSlipUrl` maxes at **4 chars**: it is the literal
  string `"null"` on every row, not a URL. Dead column, carried as-is.

### Method — why not a straight pipe

CSV-encodes bytea as hex, so a source→target pipe is ~800 MB in a single
connection, and per §3 the SSH session drops between commands. Instead: dump to
a file on the VPS (resumable, verifiable), then load from disk. Binary `COPY`
would halve the bytes but is **not usable** — source columns are `text`, target
is `varchar(255)`, and binary COPY matches on type OID.

Idempotent by design (`on conflict (id) do nothing`), so re-run for the delta
after cutover.

### Running it

**Do not paste long scripts into the VPS shell** — a truncated paste cost us a
run. The steps live in `scripts/migrate-bvnmod.sh`, committed to the repo, so
the VPS gets them by `git pull` instead:

```bash
cd /var/www/verification_site
git pull
export SRC_PW='<from nimcweb/.env DATABASE_URL>'

bash scripts/migrate-bvnmod.sh check     # connectivity, disk, FK precheck
screen -S bvnmod                         # dump takes minutes; survive SSH drops
bash scripts/migrate-bvnmod.sh dump      # ctrl-a d to detach, screen -r bvnmod
bash scripts/migrate-bvnmod.sh load
bash scripts/migrate-bvnmod.sh verify
bash scripts/migrate-bvnmod.sh cleanup
```

`export` (not `SRC_PW=...` alone) matters — the script is a child process.
The password stays out of the repo and out of this file.

Gotchas the script encodes, worth knowing:

- **`psql \copy` does not expand `~`.** `to '~/bvnmod.csv'` writes a file
  literally named `~` in the working directory. Absolute paths only.
- `load` aborts before inserting if any staged row references a missing user,
  and leaves the stage table up for inspection.
- `load` backs up first only if the target table is non-empty; on the initial
  virgin run the rollback is simply `delete from "BvnModification"`.
- `check` runs its FK probe against **target** users, not source, so it catches
  the real failure mode.

**Do not treat the 46 `refunded` rows as money to move.** Per §6, balances live
in the `wallet_entries` ledger; the refunds themselves arrive with
`wallethistory` (§7 order 3). This import is rows only.

### Result (2026-07-19)

Ran clean: staged 1577, `INSERT 0 1577`, nothing dropped by the conflict clause.
The target table held **one row before the import** — a test submission made on
abc.softel.ng, deleted (with `returning`, output in the session log) after
confirming its origin. Expect this on later tables too: **the target is no longer
virgin**, so "rollback is just a delete" stops being true.

## 9. Generic table copy — `scripts/migrate-table.sh`

BvnModification needed its own script only because 400 MB of bytea has to spool
to disk. Everything else is small enough for a direct pipe, so the remaining
tables share one script:

```bash
cd /var/www/verification_site && git pull
export SRC_PW='<from nimcweb/.env DATABASE_URL>'

bash scripts/migrate-table.sh bvnRetrieval check
bash scripts/migrate-table.sh bvnRetrieval copy
bash scripts/migrate-table.sh bvnRetrieval verify
bash scripts/migrate-table.sh bvnRetrieval cleanup
```

Table names are **case-sensitive**: `bvnRetrieval`, `NINDetails`, `Transactions`,
`wallethistory`, `Pin`.

It computes the column list as the **intersection** of source and target columns
in source order, so `remember_token` (target-only) and any source-only column are
skipped instead of breaking the copy. `check` prints both sets — **read that
output**; a source-only column means data is being silently discarded, which is
fine for `remember_token` and not fine in general.

`check` also probes `userId` against **target** `users` when the table has that
column, and `copy` re-checks it on the staged rows and aborts before inserting.

### bvnRetrieval — measured 2026-07-19

452 rows, 168 kB, no bytea, 113 distinct users, **0 orphan userIds**, longest
value 25 chars (all target columns are `varchar(255)`), no embedded newlines.
Status mix: completed 415, rejected 26, pending 9, processing 2. Range
2026-06-15 → same-day, so this table is **live and recent** — expect drift and
plan a delta re-run at cutover.

## 10. Open items

- [x] Run the users copy — done, confirmed on the VPS 2026-07-19.
- [x] Run the BvnModification copy (§8) — done 2026-07-19, 1577 inserted.
- [ ] Confirm BvnModification image bytes (~383 MB, 1 empty) via
      `migrate-bvnmod.sh verify` — count is proven, byte integrity is not.
- [ ] Delete `/root/bvnmod.csv` (766 MB) once verified.
- [ ] Email `yauyusufsaeed@gmail.com` — their nimcweb password won't work on
      abc.softel.ng; they keep the target account (`zaks`).
- [ ] **`admin@example.com` on live production holds a balance of ₦975,200.**
      Seeded test account on a system taking real purchases — zero or delete it.
- [ ] Verify the 3 source `ADMIN` accounts should hold admin on abc.softel.ng.
- [ ] Source has 2224 non-null `apitoken` values (unique column). Decide whether
      to carry them (live API consumers keep working, now against a different
      app) or null and re-issue.
- [ ] **Cutover plan.** The source keeps taking signups and payments, so any
      snapshot is stale on arrival. Either freeze nimcweb for the final run, or
      make each import idempotent and re-run for the delta.
