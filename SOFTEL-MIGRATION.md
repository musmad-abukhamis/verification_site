# vtuportal (softel.ng) → myvtu (api.softel.ng) data migration

Working notes for copying table data out of the legacy Next.js/Prisma app
**vtuportal** into the Laravel **myvtu** deployment. Started 2026-08-02.

Companion to `NIMCWEB-MIGRATION.md`, which covers the earlier nimcweb → abcweb
copy. Same tooling (`scripts/migrate-table.sh`), different databases, and one
problem that migration did not have — see §4.

> **Credentials are deliberately not in this file.** The target's live in
> `/var/www/myvtu/.env`; the source's in vtuportal's `.env` (`DATABASE_URL`).
> Everything below reads them at runtime.

---

## 1. The two systems

|                | source (vtuportal)            | target (myvtu)                     |
| -------------- | ----------------------------- | ---------------------------------- |
| stack          | Next.js + Prisma              | Laravel 12 + Inertia/Vue           |
| site           | `softel.ng`                   | `api.softel.ng`                    |
| path on VPS    | `/var/www/vtuportal`          | `/var/www/myvtu`                   |
| database       | `vtuportal` @ `72.62.22.206`  | `myvtu` @ `127.0.0.1`              |
| db user        | `vtuuser`                     | `myvtuuser`                        |
| Postgres reach | **public**                    | **localhost-only**                 |

Both sites are served from the same VPS (`185.255.94.216`, `srv1184914`) but the
source *database* is on a different host. `/var/www/vtuportal/.env` has no `DB_*`
keys at all — only `DATABASE_URL` — so the `strip` helper returns empty strings
there and psql falls back to the unix socket as OS user `root`. **Run everything
from `/var/www/myvtu`.**

myvtu runs the same codebase as verification-site (confirmed at commit `869c362`),
so its schema is this repo's migrations and `config/hashing.php` already carries
`bcrypt.verify => false`.

## 2. Schema compatibility

The source is the same Prisma schema family as nimcweb — `users`, `Transactions`,
`NINDetails`, `wallethistory`, `BvnModification`, `bvnRetrieval`, `Pin` — plus
VTU-specific tables (`Plan`, `networks`, `vendorapi`, `ipe`, `validation`).
A data copy, not a transformation.

**Small dataset**: ~35k rows, ~20 MB total. Everything fits a direct pipe; none
of the spool-to-disk treatment `migrate-bvnmod.sh` needed.

| table | rows | | table | rows |
| --- | --- | --- | --- | --- |
| `Transactions` | 19,190 | | `accountkyc` | 78 |
| `NINDetails` | 14,480 | | `Plan` | 61 |
| `wallethistory` | 1,439 | | `personalisation` | 48 |
| `ipe` | 257 | | `validation` | 37 |
| `notification_users` | 212 | | `Pin` | 10 |
| `users` | 142 | | rest | ≤8 |

### Three structural gotchas

- **25 of 32 tables have `text`/cuid PKs** — ids transfer unchanged, re-runs are
  idempotent under `on conflict (id) do nothing`.
- **`Plan` and `vendorapi` have `serial` integer PKs.** Carrying those ids
  collides with ids the target sequence already issued. Use
  `EXCLUDE_COLS='id' CONFLICT='(<natural key>)'`.
- **Five tables have no declared PRIMARY KEY**: `ipe`, `validation`,
  `personalisation`, `bvnserviceprices`, `ninServicePrices`. They are not
  unkeyed, though — `ipe` and `validation` each carry a UNIQUE index on a
  `serial` `id` (`ipe_id_key`, `validation_id_key`), so `on conflict do nothing`
  does dedupe them. A probe filtering on `pg_index.indisprimary` misses these;
  check `pg_indexes` instead. The remaining three are unverified.
- **Integer ids are the real hazard, not the missing PK.** Carrying a source
  `serial` id into a target that assigns its own is only safe when the target is
  empty, and even then the target's sequence is left behind the data — see §6.

### users column delta

`last_seen` is **source-only** and gets dropped by the column intersection — the
target has no such column. `remember_token` is target-only, left at default.

## 3. Source profile — users (measured 2026-08-02)

142 rows, clean: **zero duplicates** on email, username, normalised phone, or
apitoken. Longest values name 32 / email 35 / phone 13 / username 30, all well
inside `varchar(255)`. Roles: 133 USER, 3 ADMIN, 6 API — the `API` role is new
versus nimcweb but is supported (`UserRole::API`, `ApiTokenMiddleware`), and 110
users carry an `apitoken`. Signups run 2025-07-22 → 2026-07-16, so unlike nimcweb
this source is **not actively drifting**; a single pass may be enough.

**Password hashes are bcryptjs** — `$2a$12$` 72, `$2b$10$` 68, `$2a$10$` 2. This
is the trap that 500'd the live site on 2026-07-19. `config/hashing.php` must
keep `bcrypt.verify => false`; verified present on myvtu.

**All 142 have `email_verified = NULL`.** Dormant, as before: `User` implements
`MustVerifyEmailContract`, so the day any route gets `verified` middleware every
migrated user is locked out at once.

**Balances are safe to copy raw.** `CheckLedgerIntegrity` only inspects users who
already have `wallet_entries` rows, and `WalletLedger` derives `balance_after`
from the live `users.balance` at movement time. So importing `balance` as a plain
column produces no drift and no bogus ledger state. (`NIMCWEB-MIGRATION.md`'s
"never move money with raw SQL" is about mutating *existing* balances — it does
not apply to seeding fresh users.)

## 4. The identity collision — what made this migration different

**The target was not empty.** myvtu already held 4 accounts, and all four belong
to the same people as source accounts. Byte-exact collisions (which is what the
unique constraints actually enforce):

| source account | blocked by target | on | target had history? |
| --- | --- | --- | --- |
| `musmadvtu` ADMIN ₦53,120 | `musmady` | email | **no — zero rows anywhere** |
| `abubilal` API ₦61,080 | `Abubilal17` | email | yes (250 wallethistory, 20 wallet_entries) |
| `smart` ADMIN ₦6,795 | `SMART` | email | yes (52 wallet_entries, 45 data_transactions) |
| `muhammad` USER ₦66,600 | `SMART` | phone | yes (same target account) |

**Match on the constraint's own semantics, not on meaning.** An earlier pass
normalised phones and produced a scarier, wrong answer: source `musmadvtu`
(`07063523516`) and target `musmad` (`+2347063523516`) are the same human but
different byte strings, so they never collide. That pair imports as two separate
accounts — cosmetic, mergeable later. Only byte-exact matches block.

These four are the busiest accounts in the database: **73.6% of all Transactions**
and **57.3% of all NINDetails** hang off them. `on conflict do nothing` would have
skipped them silently and every child import would then have aborted on the FK
precheck. This is precisely the case `NIMCWEB-MIGRATION.md` §7 flagged as the
thing that changes everything.

### Decisions taken 2026-08-02

- **`musmadvtu` → SKIP.** Deleting the empty `musmady` would have let it import
  intact; keeping `musmady` was chosen instead. Cost: 3,123 Transactions, 3,264
  NINDetails, 87 ipe, 31 wallethistory and ₦53,120 stay behind on softel.ng.
- **`abubilal`, `smart`, `muhammad` → MERGE** onto their existing target
  accounts. `smart` and `muhammad` both collapse onto `SMART`, which therefore
  inherits two source histories.
- **Balances: target wins.** Source balances (₦134,475 across the three merged
  accounts) are discarded, not summed.

Encoded in `scripts/vtuportal-user-map.sql` as `mt_user_map(src_id, tgt_id)`,
where a NULL `tgt_id` means skip. `migrate-table.sh` applies it automatically
whenever that table exists on the target — to the **stage** table only, so the
source is never touched and a bad map is undone by re-staging.

### Projected effect (verified against source totals)

| table | drop | remap | plain | total |
| --- | --- | --- | --- | --- |
| `users` | — | — | 138 inserted | 142 |
| `Transactions` | 3,123 | 10,996 | 5,071 | 19,190 |
| `NINDetails` | 3,264 | 5,040 | 6,176 | 14,480 |
| `wallethistory` | 31 | 195 | 1,213 | 1,439 |

Every row is accounted for in exactly one bucket.

## 5. Running it

```bash
cd /var/www/myvtu && git pull

# Substitute the real password -- do NOT keep the angle brackets, they are not
# quoting, they become part of the password and psql fails "password
# authentication failed for user vtuuser".
unset SRC_PW      # else a shell that loses SRC_URI silently reads abcweb (see below)
export SRC_URI='postgresql://vtuuser:THEPASSWORD@72.62.22.206:5432/vtuportal'

# once, before the first table. Read the target credentials exactly the way
# migrate-table.sh does -- a raw `cut -d= -f2-` keeps the surrounding quotes
# when .env has DB_PASSWORD="..." and psql then rejects them.
strip(){ grep -E "^$1=" .env | head -1 | cut -d= -f2- \
  | sed -e 's/[[:space:]]*#.*$//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//' \
        -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"; }

PGPASSWORD="$(strip DB_PASSWORD)" psql -h "$(strip DB_HOST)" -U "$(strip DB_USERNAME)" \
  -d "$(strip DB_DATABASE)" -w -f scripts/vtuportal-user-map.sql

bash scripts/migrate-table.sh users check    # read the identity-map lines
bash scripts/migrate-table.sh users copy     # expect inserted=138
bash scripts/migrate-table.sh users verify
bash scripts/migrate-table.sh users cleanup
```

`copy` now measures the insert (`before`/`after`/`inserted`) and shouts when the
count is short of the stage, because a silent skip on a parent table strands
every child row that references it — the most expensive failure mode here.

Then in FK order: `Pin`, `OTP`, `accountkyc` → `wallethistory`, `Transactions` →
`NINDetails`, `ipe`, `bvnsdkform`, `bvnRetrieval`, `BvnModification` →
`notification_users`. Config tables (`Plan`, `networks`, `vendorapi`,
`vendorselection`, `settings`) are independent — mind the serial-PK and no-PK
rules in §2.

### users — DONE 2026-08-02

`staged 142 → 138 after map → inserted=138`, target went 4 → 142, and `verify`
reported 0 rows staged-but-not-landed. The four mapped accounts were removed
from the stage as intended.

**It took three attempts to run against the right database.** Twice the copy was
launched with `SRC_PW` exported instead of `SRC_URI`, which selects the legacy
nimcweb/abcweb default; `check` then cheerfully reported 2263 source rows. The
tell was the row count, not an error — nothing failed. Hence the
`mt_migration_meta` guard added in `4bc179a`: the target now records which source
it expects and the script aborts on a mismatch.

### Child tables — preflight (measured 2026-08-02)

**Zero orphan `userId`s** in every child table on the source
(`Transactions`, `NINDetails`, `wallethistory`, `notification_users`,
`accountkyc`, `ipe`, `Pin`, `OTP`, `bvnsdkform`, `bvnRetrieval`,
`BvnModification`). With users imported and the map applied, every FK resolves.

**`verification_versions` (8 rows) has no target counterpart** and cannot be
copied — it is the only source table missing from this schema. Everything else
exists on both sides.

Expected inserts, derived from the map:

| table | source | drop | remap | insert |
| --- | --- | --- | --- | --- |
| `Transactions` | 19,190 | 3,123 | 10,996 | 16,067 |
| `NINDetails` | 14,480 | 3,264 | 5,040 | 11,216 |
| `wallethistory` | 1,439 | 31 | 195 | 1,408 |
| `ipe` | 257 | 87 | 5 | 170 |
| `notification_users` | 212 | 5 | 10 | 197 |
| `accountkyc` | 78 | 1 | 2 | 77 |
| `Pin` | 10 | 0 | 0 | 10 |
| `OTP` | 7 | 0 | 0 | 7 |

## 6. ipe and validation — DONE 2026-08-02

Scope was narrowed to `users`, `ipe`, `validation`. Both of these key on a
`serial` integer, and they took opposite paths for that reason.

| table | source | dropped | remapped | inserted | target after |
| --- | --- | --- | --- | --- | --- |
| `ipe` | 257 | 87 | 5 | **170** | 0 → 170 |
| `validation` | 37 | 12 | 0 | **25** | 160 → 185 |

**`ipe` — target was empty, so source ids were carried across.**

**`validation` — target already held 160 rows** occupying the same id range as
the incoming 1–37. The first attempt inserted **0 of 25**: every row hit the
unique index and `on conflict do nothing` discarded it. Nothing was corrupted,
but nothing landed, and the only reason this was noticed is the measured-insert
warning added in `819a0d0` — the old script would have printed "inserted." and
moved on. Re-run with `EXCLUDE_COLS='id'` so the target assigns ids: 25 landed.

> With `EXCLUDE_COLS='id'` there is no key left to dedupe on, so **`validation`
> is not safe to re-run** — a second pass duplicates all 25 rows.

### The sequence trap — confirmed, not theoretical

After `ipe` was copied with its source ids, the target read:

```
ipe | max(id)=267 | ipe_id_seq.last_value=1
```

The sequence had never been used, so the app's next IPE insert would have drawn
id=1 and collided with an imported row, repeatedly, until it climbed past 267.
This is a production failure that appears *after* a migration reports success.
`migrate-table.sh` now calls `setval` after any copy that carried ids (`03dc3e2`);
`ipe` was fixed by hand because it ran on the previous version. Final state:
`ipe` 267/267, `validation` 185/185.

**Any future table with an integer key needs this check.** `Plan` and
`vendorapi` are the remaining ones.

## 7. Open items

- [x] Run the users copy — done 2026-08-02, `inserted=138`, verified.
- [x] `ipe` (170) and `validation` (25) — done 2026-08-02, see §6.
- [ ] `personalisation` (48), `bvnserviceprices`, `ninServicePrices`: key
      structure still unverified. Check `pg_indexes`, not `indisprimary`.
- [ ] `accountkyc` and `notification_users` carry rows for the three merged
      accounts. If the target already holds a row for the same user, the merge
      can produce a duplicate or trip a unique constraint — check both before
      copying, not after.
- [ ] `verification_versions` (8 rows) has nowhere to go. Confirm it is dead.
- [ ] `Plan` / `vendorapi`: pick natural keys for `CONFLICT`.
- [ ] Source phone `7080222272` (`abubilal`) is 10 digits, missing its leading
      zero. Other rows may share this; normalise before any phone-based lookup.
- [ ] **Rotate the database passwords.** Source and target currently share one,
      and it has been pasted into a chat transcript.
- [ ] ymusmad@gmail.com will hold two accounts on api.softel.ng (`musmad` and,
      if the skip is ever reversed, `musmadvtu`). Merge by hand if unwanted.
