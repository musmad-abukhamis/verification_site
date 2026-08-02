-- Identity map for the vtuportal (softel.ng) -> myvtu (api.softel.ng) copy.
--
-- Both databases already held accounts for the same four people, so a plain
-- copy is impossible: the source rows collide with live target rows on the
-- byte-exact unique constraints (email, phone). "on conflict do nothing" would
-- skip them SILENTLY and then every child table would abort on the FK
-- precheck, because 57% of Transactions hang off these ids.
--
-- This table encodes the decisions taken 2026-08-02:
--
--   tgt_id NOT NULL -> merge. The source account's child rows are rewritten
--                     onto the existing target account. Target keeps its own
--                     balance; the source balance is deliberately discarded.
--   tgt_id NULL     -> skip. The source account is not imported and its child
--                     rows are dropped from every later table.
--
-- migrate-table.sh applies this automatically whenever the table exists:
--   * on `users`, every src_id listed here is removed from the stage table
--     (merged accounts must not be inserted as new rows);
--   * on any table with a "userId", NULL rows are deleted and mapped rows are
--     rewritten, before the FK check and the insert.
--
-- Drop the table to fall back to a plain copy.
--
-- Run against the TARGET (myvtu), from /var/www/myvtu:
--   PGPASSWORD=... psql -h 127.0.0.1 -U myvtuuser -d myvtu -w -f scripts/vtuportal-user-map.sql

begin;

drop table if exists mt_user_map;

create table mt_user_map (
    src_id   text primary key,
    tgt_id   text,           -- null = drop the source account and its rows
    note     text not null
);

insert into mt_user_map (src_id, tgt_id, note) values

-- SKIP. Blocked by target `musmady` (ymusmad@gmail.com), an account with zero
-- rows in every table. Deleting that empty account would have let this one
-- import intact; keeping it was chosen instead, so musmadvtu and its ~6,500
-- child rows (3,123 Transactions, 3,264 NINDetails, 87 ipe, 31 wallethistory)
-- stay behind on softel.ng. Source balance NGN 53,120 is not carried.
('cm7gdkkd10000770hlcsu10xy', null,
 'musmadvtu ADMIN - skipped; blocked by empty target musmady on email'),

-- MERGE. Same email as target `Abubilal17`, which is live (250 wallethistory,
-- 20 wallet_entries, 18 data_transactions). Source balance NGN 61,080 discarded.
('cma4ebc2p000jnpzyjhec169p', 'cmryul8dk00004jp9ef2a050e',
 'abubilal API -> Abubilal17'),

-- MERGE. Same email as target `SMART`. The single largest account on the
-- source: 10,251 Transactions. Source balance NGN 6,795 discarded.
('cm8ee289f0002i7rhn2omt5r6', 'cms2zkafl00006l7rd31d3bba',
 'smart ADMIN -> SMART'),

-- MERGE. Same *phone* as target `SMART` (different email). Note this collapses
-- a SECOND source account onto the same target user, so SMART inherits both
-- histories. Source balance NGN 66,600 discarded.
('cm7mxfbrs0000fn5x8jdrmebj', 'cms2zkafl00006l7rd31d3bba',
 'muhammad USER -> SMART (second account merged onto the same target)');

commit;

select src_id, coalesce(tgt_id, '<SKIP>') as tgt_id, note from mt_user_map order by note;
