# Prompt: Build a Production Buy-Data (VTU) Module

> Copy everything below the line into your AI coding agent (or hand it to a developer) to rebuild
> the data-vending module of this site in a new project. Adjust the **Stack** section to match the
> target project before using it.

---

## Role & Goal

You are building a production-grade **VTU data-reselling module** for a Nigerian fintech-style web
app. Users fund a wallet, pick a network (MTN, Airtel, Glo, 9mobile) and a data plan, and the system
purchases the plan from one of several upstream VTU vendor APIs on their behalf. The module must be
**vendor-agnostic, queue-driven, idempotent, and safe with money** — a failed vendor call must never
double-charge or double-send data.

## Stack

- Laravel 11+ (PHP 8.2+), PostgreSQL, Redis/database queue.
- Inertia.js + Vue 3 (Composition API) + Tailwind for the UI, with SSR enabled.
- Money stored as numeric columns; all balance changes go through a single ledger service inside DB
  transactions.

## Data Model (normalized — no `vendor1..vendor5` columns anywhere)

Create these tables in one migration:

1. **`vendors`** — one row per upstream API. Columns: name, slug, `driver` (string: which HTTP
   driver to use), `base_url`, **encrypted** `credentials` (json), `status` (active/inactive),
   `priority` hints, timestamps.
2. **`plans`** — the sellable catalog. network (lowercase slug), `type` (SME, CORPORATE_GIFTING,
   GIFTING, DATASHARE, …), name/size, validity string, `cost_price`, and per-role selling prices
   (e.g. `price_user`, `price_agent`, `price_vendor`, `price_api`), `status` toggle.
3. **`plan_vendor_mappings`** — plan_id × vendor_id → the vendor's own `vendor_plan_code`. A plan
   can be fulfillable by several vendors with different codes.
4. **`network_vendor_mappings`** — network × vendor_id → the vendor's network code (e.g. MTN is
   `"1"` on one vendor, `"mtn"` on another).
5. **`vendor_routes`** — the routing matrix: network × plan type → **ordered list** of vendor_ids
   (`position` column). Position 0 is primary; the rest are failover candidates.
6. **`network_prefixes`** — phone prefix (e.g. `0803`) → network, used only for a client-side hint,
   never to override the user's explicit choice.
7. **`data_settings`** — key/value module settings (failover on/off, reconcile cutoff minutes,
   purchase enabled, support notes). Cache-backed accessor with explicit flush.
8. **`data_transactions`** — one per purchase: user_id, unique `reference`, unique per-user
   `client_ref` (idempotency key), network, plan snapshot (name/type/size), phone, `amount` charged,
   `cost_price`, status (`pending` → `processing` → `success` / `failed` / `refunded` /
   `refunded_unconfirmed`), winning vendor_id, vendor response reference, timestamps.
9. **`data_transaction_attempts`** — audit trail: one row per vendor call (transaction_id,
   vendor_id, request payload sent, raw response, outcome, duration).
10. **`wallet_entries`** — double-entry-ish ledger: user_id, `direction` (credit/debit), amount,
    `balance_after`, source type/id (polymorphic to the transaction), narration. **Every** balance
    mutation writes a row here; the user's `balance` column is only ever updated together with a
    ledger row, atomically, with the user row locked (`lockForUpdate`).
11. **`beneficiaries`** — saved phone numbers per user (label, phone, network).

## Purchase Pipeline

### 1. `DataPurchaseService::initiate(user, payload)`
- Validate network/plan/phone server-side. **Never trust a client-supplied price or plan name** —
  reload the plan and compute the price for the user's role on the server.
- Idempotency: if a `client_ref` already exists for this user, return the existing transaction
  instead of creating a new one.
- In one DB transaction: lock the user row, check balance, debit via the ledger service, create the
  `data_transactions` row as `pending`.
- Dispatch a queued job and return immediately. The HTTP request never waits on the vendor.

### 2. `ProcessDataPurchase` job
- Resolve the ordered vendor list from `vendor_routes` for the transaction's network+type, filtered
  to active vendors that have both a plan mapping and a network mapping.
- Call vendors in order through a **driver abstraction** (below). Record every attempt in
  `data_transaction_attempts`.
- Failover rules (critical for money safety):
  - Move to the next vendor **only on an explicit failure** from the current one.
  - On timeout or an ambiguous/unparseable response, **stop** — leave the transaction `processing`
    and let reconciliation decide. Never re-send data that might already have been delivered.
  - Failover beyond the first vendor only when the admin failover setting is on.
- On success: mark `success`, store vendor reference. On exhausting all vendors with explicit
  failures: mark `failed` and **auto-refund** through the ledger (credit entry linked to the
  transaction).

### 3. `ReconcilePendingTransactions` (scheduled command)
- For each `processing` transaction, re-query the last-attempted vendor's status endpoint.
- Confirmed success → `success`. Confirmed failure → refund → `refunded`.
- Still unknown after a configurable cutoff (e.g. 30 min) → refund and mark
  `refunded_unconfirmed` so admins can review manually.

## Vendor Driver Abstraction

- `VendorDriverInterface`: `purchase(vendor, mappings, transaction): VendorResult` and
  `requery(...): VendorResult`.
- `VendorResult` value object with three outcomes: **success**, **explicit fail**, **unknown** —
  the pipeline branches on this, so drivers must be honest about ambiguity (HTTP error, timeout,
  unrecognized body ⇒ unknown, not fail).
- `AbstractHttpDriver` base + concrete drivers keyed by the `vendors.driver` column, e.g.:
  - `token_style_a`: `Authorization: Token <key>`, JSON body
    `{network, phone, data_plan, bypass: true, "request-id": <reference>}`; success when
    `status`/`Status` ∈ {`success`, `successful`}.
  - `token_style_b`: `Authorization: Token <key>`, body `{network, mobile_number, plan,
    Ported_number: true}`; same success check.
  - `oauth`: fetch a short-lived `AccessToken` first (HTTP Basic to a `/user` endpoint), then
    purchase with it.
- `VendorDispatcher` picks the driver from the vendor row. Adding a vendor = one DB row (+ a new
  driver class only if its API shape is genuinely new).
- Vendor keys/credentials live encrypted in the DB or in `.env` — never in git.

## User-Facing UI (Inertia/Vue)

- **Buy Data page**: network selector → type selector → plan cards (role-priced, from a cached
  catalog), phone input with prefix-based network *hint* (never auto-switch a chosen network),
  "ported number" toggle, beneficiary picker/save, balance display, confirm modal.
- After submit, show the transaction status and poll for the queued result with partial reloads
  (`router.reload({ only: ['transaction'] })`) until it leaves `processing`.
- Must be SSR-safe (no `window`/`document` at setup time).
- Transaction history page with status badges and receipt/detail view.

## Admin Surface

All under the admin middleware/prefix, with a "Data (VTU)" nav group:

1. **Vendors** — CRUD + activate/deactivate; credential fields write-only (blank = keep existing).
2. **Data Plans** — CRUD, per-role prices, per-vendor plan-code mapping editor, status toggles.
3. **Routing** — the matrix UI: for each network × type, an ordered vendor list (primary +
   failovers); network-code mappings; prefix management; failover & module settings.
4. **Data Transactions** — filterable list + detail view showing every vendor attempt
   (request/response/outcome) for support debugging.
5. **Wallet** — search user, credit/debit with narration via the same ledger service (never a raw
   balance update).

Every admin write flushes the catalog/settings cache.

## Public API (for resellers)

- `POST /api/v1/data` — body: network, plan id, phone, `client_ref`. Auth: bearer token looked up
  against the user's API token, role must be API. Wraps the same `DataPurchaseService` (same
  idempotency, same pricing-by-role, same ledger).
- `GET /api/v1/data/{reference}` — status lookup.
- Consistent JSON envelope with explicit status strings; document that `processing` means "poll
  again", not failure.

## Caching

- A `DataCache` service exposing `catalog()` / `catalogForRole(role)` / `prefixMap()` with ~1h TTL;
  the buy page and API read only from it. All admin mutations call `DataCache::flush()`.

## Non-Negotiable Invariants

1. No balance change outside the ledger service; ledger row + balance update are atomic with the
   user row locked.
2. Idempotent initiation on `client_ref`; retried HTTP requests must not double-charge.
3. Never send to a second vendor after an ambiguous outcome — only after an explicit fail.
4. Prices, plan names, and amounts are always computed server-side from the DB, never taken from
   the request.
5. Every vendor interaction is persisted in the attempts table before/after the call.
6. Refunds are ledger credits linked to the transaction — visible, auditable, and only issued once.

## Deliverables & Verification

- Migration, models, services, jobs, scheduled command, controllers, Vue pages, seeders (sample
  networks, prefixes, a demo vendor + plans, routing matrix).
- Feature tests covering: idempotent initiate, insufficient balance, successful purchase, explicit
  fail → failover → success, explicit fail exhausted → refund, timeout stays processing (no second
  send), reconcile → success / refund / refunded_unconfirmed, admin CRUD authorization, API token
  auth + envelope. Run on sqlite in-memory with the sync queue.
- `npm run build` (including SSR) and the full test suite must pass before you consider the work
  done.
