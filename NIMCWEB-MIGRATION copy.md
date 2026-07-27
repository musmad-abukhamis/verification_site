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

## 8. Open items

- [ ] Run the users copy; confirm 2256 and exactly one skipped row.
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
