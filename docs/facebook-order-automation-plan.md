# Facebook Messenger Order Automation Plan

## Scope and executive summary

This document is an analysis and implementation plan only. No application behavior has been changed.

The repository already has useful foundations: product variants, transactional inventory movements, customer orders, payment records, an encrypted integrations store, AI providers, an AI order-draft review flow, database queues, roles/permissions, and focused inventory/order tests. The Facebook integration should reuse those foundations, but must not reuse the current AI draft conversion endpoint unchanged because it assumes an authenticated staff user and does not model Messenger conversations, webhook idempotency, human takeover, confirmation, delivery address, payment preference, or branch ownership.

The safest design is a webhook-ingestion boundary that authenticates and stores each Meta event, acknowledges it quickly, and dispatches a queued processor. Conversation state is persisted independently from the AI output. AI may propose structured draft changes and response text, but a deterministic state machine controls whether replies are allowed and whether the single explicit command named **Create Order** is available. That command is accepted only after a final summary has been recorded and explicitly confirmed by the customer or an authorized staff member.

There is currently **no branch model, `branches` table, `branch_id` column, global scope, middleware, policy, route binding, or branch-isolation test anywhere in the repository**. Therefore branch isolation cannot presently be “preserved”; it must be introduced (or an uncommitted/external branch implementation must be supplied) before Messenger ordering can safely support multiple branches. This is a phase-zero blocker for production deployment.

## Runtime and framework versions

- Laravel runtime: **Laravel Framework 12.61.0** (`php artisan --version`); Composer permits `laravel/framework ^12.0`.
- PHP requirement: **`^8.2`** in `composer.json`.
- PHP available in the inspected CLI environment: **8.2.12**.
- `CLAUDE.md` describes a different reference environment using PHP **8.4.21**. Deployment and CI should resolve this discrepancy; the codebase's declared minimum is PHP 8.2.
- Pest 3.8 / PHPUnit 11 are configured; tests use SQLite in-memory and the synchronous queue driver.

## Current architecture by domain

### Products and variants

Models:

- `app/Models/Product.php`: name/code/description/status; active scope; relationships to product-specific sizes, colors, and inventory cells.
- `app/Models/Color.php`, `Size.php`: master color and size records.
- `app/Models/ProductColor.php`, `ProductSize.php`: product-specific variants. Creating a color creates its size cells.
- `app/Models/ProductColorSize.php`: the sellable inventory cell with `current_stock`, `reserved_quantity`, and `reorder_level`.

Migrations:

- `2026_05_31_000000_create_sizes_table.php`
- `2026_05_31_000001_create_colors_table.php`
- `2026_05_31_000001_create_products_table.php`
- `2026_05_31_000002_create_product_sizes_table.php`
- `2026_05_31_000003_create_product_colors_table.php`
- `2026_05_31_000004_create_product_color_sizes_table.php`
- `2026_06_09_100001_add_image_path_to_product_color_table.php`

Controllers/services/routes:

- `ProductController`, `ProductColorController`, and `ProductSizeController` manage the catalog and variants.
- `ProductCodeService` generates variant item codes.
- `ProductCellLookup` formats cells, computes available stock, and rejects inactive products.
- All product/catalog routes are session-authenticated and permission-gated in `routes/web.php`.

There is no price column on products or inventory cells. Prices are entered per order item (`customer_order_items.unit_price`). Messenger product answers and summaries therefore have no authoritative catalog price source today.

### Inventory and stock movements

Models/migrations:

- `ProductColorSize` is the stock balance record.
- `StockMovement` is the append-style audit record created for every inventory operation.
- `2026_05_31_000004_create_product_color_sizes_table.php` defines balances.
- `2026_05_31_000005_create_stock_movements_table.php` defines movement history.

Services/controllers/events:

- `InventoryService` is the sole important mutation abstraction. It exposes `stockIn`, `stockOut`, `reserve`, `release`, `damage`, and `adjust`.
- Each mutation runs in a database transaction, locks the inventory cell with `lockForUpdate()`, updates the balance, writes a movement, then broadcasts `StockUpdated` after commit.
- Available stock is `current_stock - reserved_quantity`.
- `InventoryController` exposes permission-gated web endpoints for manual operations and bulk updates.
- `SupplierOrderService` stocks in received purchase-order quantities and then tries to reserve waiting customer orders FIFO.

### Customers

- `Customer` contains name, handle, contact, notes, and a `CustomerSource` enum value, and has many orders.
- `CustomerController` and form requests provide session-authenticated CRUD with `view customers` / `manage customers` permissions.
- `customers.handle` is nullable and not unique. There is no PSID column, Page association, or stable channel identity constraint.
- Customer orders also copy customer name/contact/source/notes as a historical snapshot.

### Customer orders / sales

Models:

- `CustomerOrder`: order number, optional customer, snapshot customer fields, due date, totals, delivery/release fields, status, production fields, optional supplier order, and creator.
- `CustomerOrderItem`: inventory cell, ordered/reserved quantity, unit price, and line status.
- `OrderActivity`, `OrderLayout`: audit timeline and artwork/layout workflow.
- `SupplierOrder` / `SupplierOrderItem`: procurement used for shortages.
- `AiOrderDraft`: raw staff-submitted conversation, parsed/matched JSON, draft status, optional converted order, and creator.

Primary migrations:

- `2026_06_09_000003_create_customer_orders_table.php`
- `2026_06_09_000004_create_customer_order_items_table.php`
- `2026_08_04_200043_add_customer_id_to_customer_orders_table.php`
- `2026_08_04_200043_add_unit_price_to_customer_order_items_table.php`
- `2026_08_04_200043_add_finance_fields_to_customer_orders_table.php`
- `2026_08_04_200044_add_delivery_fields_to_customer_orders_table.php`
- `2026_08_04_200044_add_production_fields_to_customer_orders_table.php`
- order activity/layout/image migrations dated 2026-08-04 and 2026-06-15.

Controllers/services/routes:

- `CustomerOrderController::store()` creates an order and items transactionally, then immediately calls `CustomerOrderService::reserveOrder()`.
- `CustomerOrderService` owns reservation, fulfillment, cancellation, and status synchronization.
- `AiOrderAssistantController` lets permitted staff submit message text, review a draft, and convert it; conversion also creates an order then invokes the same reservation workflow.
- Order list, board, create, show, fulfill, cancel, layouts, delivery, and release routes are inside the authenticated web group and use Spatie permissions.
- `OrderActivityLogger` records staff-facing order events.

Order statuses are `pending`, `partially_reserved`, `reserved`, `fulfilled`, and `cancelled`. Item statuses are untyped strings (`pending`, `partially_reserved`, `reserved`, `fulfilled`, `cancelled`).

Order-number generation queries the latest `CO-NNNN` string and increments it in a model creating hook. It is protected only by a unique database key, so concurrent creation can collide and fail rather than retry safely.

### Payments

- `OrderPayment` records amount, method, reference, notes, staff recorder, posted time, and reversal fields.
- `FinanceController` records/reverses payments transactionally and updates `customer_orders.amount_paid`.
- `StoreOrderPaymentRequest` prevents a payment exceeding the current balance due, but there is no row lock around concurrent payment posting.
- The customer order has no requested/preferred payment-method field. Messenger must collect a preference separately; it must not create an `OrderPayment`, alter `amount_paid`, or imply payment was received.

### Branches

No branch-related component exists in models, migrations, controllers, services, routes, requests, factories, seeders, permissions, or tests. All catalog, customer, integration, inventory, order, finance, and reporting queries are global. Introducing only `branch_id` on Messenger tables would not isolate underlying products, stock, customers, orders, or integrations.

## Current stock deduction and restoration workflow

1. On manual order creation or AI draft conversion, order/items are inserted and `reserveOrder()` runs inside the outer transaction.
2. Reservation checks available stock and reserves as much as possible. It increases `product_color_sizes.reserved_quantity`, records a `reserve` movement, and sets item/order status to reserved or partially reserved. A shortage is allowed; it can redirect staff toward a supplier order.
3. Physical stock is **not deducted when the order is created or reserved**.
4. On fulfillment, each reserved amount is first released (decrementing `reserved_quantity`) and then stocked out (decrementing `current_stock`). The order becomes fulfilled and production completed.
5. On cancellation, reserved amounts are released and item reservations are zeroed. Physical stock is unchanged because it was never deducted.
6. Supplier receipts increase current stock, then attempt to reserve waiting orders.

Messenger-created orders must preserve this exact lifecycle: reserve at successful **Create Order**, deduct only through existing fulfillment, and restore a cancelled order by releasing reservations. The integration must not directly update stock columns.

For Messenger, the recommended policy is stricter than the current staff UI: do not ask for final confirmation unless every requested line has sufficient available stock. Recheck all cells under database locks when **Create Order** executes. If stock changed, do not create a partial order; return the conversation to review and send a revised availability summary. This satisfies “validate stock before confirming” without changing the existing behavior of staff-created orders.

## Authentication and authorization

- The only configured guard is Laravel's `web` session guard backed by `User`.
- Login/logout are CSRF-protected web routes; `EnsureUserIsActive` is appended to the web group.
- Spatie Laravel Permission protects staff operations with route middleware and request/controller checks.
- There is no `routes/api.php`, API guard, Sanctum/Passport/token dependency, service-account authentication, or existing public webhook route.
- Meta webhooks cannot use staff sessions. They require independent verification: GET subscription challenge verification and POST `X-Hub-Signature-256` validation against the Meta app secret. A webhook must never be authorized merely by a verify token query parameter on POST.
- Staff takeover and staff-triggered **Create Order** endpoints should remain inside the authenticated/permission-gated web application. Add dedicated permissions rather than treating inbound webhook identity as a user.

## Queues and jobs

- `config/queue.php` defaults to the database driver.
- The standard `jobs`, `job_batches`, and `failed_jobs` tables exist via `0001_01_01_000002_create_jobs_table.php`.
- `.env.example` sets `QUEUE_CONNECTION=database`.
- Composer's `dev` script starts `queue:listen --tries=1`.
- Tests override the queue to `sync`.
- There is currently no `app/Jobs` directory and no application job class. The webhook integration will be the first real queued workload.
- Production needs a supervised `queue:work` process, retry/backoff policy, failed-job alerting, and retention/pruning. Webhook processing and outbound sends should be idempotent because workers may retry.

## Relevant existing tests

- `InventoryServiceTest`: stock-in, insufficient stock-out, reserve/release, adjustments, stock status.
- `InventoryConcurrencyTest`: a second stock-out fails after available stock is consumed.
- `CustomerOrderTest`: automatic reservation, full/partial reservation, fulfillment release plus stock-out, cancellation release, status/permission behavior.
- `SupplierOrderTest`: receiving stock and FIFO reservation of waiting orders.
- `AiOrderAssistantTest`: provider parsing, draft creation, matching/review, conversion, exact payload items, customer linking/creation, and reservation after conversion.
- `CustomerCrmTest`: customer CRUD and linked/priced order creation.
- `FinanceTest`: payment posting/reversal, balance validation, finance permissions.
- `IntegrationTest`: encrypted AI credentials, redaction, connection management, and default provider.
- `AuthTest` and `PermissionAccessTest`: session and permission boundaries.
- `CustomerOrderBoardTest`, `CustomerOrderOpsUiTest`, `ProductionBoardTest`, and `OrderLayoutDeliveryHistoryTest`: downstream order workflow behavior.

Missing test coverage includes Meta challenge/signature verification, malformed payloads, webhook replay/idempotency, queue retries/out-of-order events, PSID identity, conversation state transitions, confirmation expiry, exact **Create Order** gating, AI tool denial, human takeover, branch isolation, stock changes between summary and confirmation, concurrent create attempts, Messenger API failures, outbound-message idempotency, and privacy/redaction.

## Components missing for Facebook Messenger

- Meta configuration: Page ID, Page access token, app secret, webhook verify token, Graph API version, and optional Page-to-branch mapping.
- Public GET/POST webhook routes that do not depend on session auth and whose POST signature is verified.
- Durable raw webhook event inbox with Meta event identity and unique replay protection.
- Queued webhook processing and outbound Messenger client.
- Conversation/thread, message, participant/PSID, and state-machine persistence.
- Human takeover state, staff ownership, timestamps, reason/audit, and staff UI/actions.
- Structured Messenger order draft and draft items (rather than relying only on opaque JSON).
- Delivery method/address and payment preference fields.
- Final-summary snapshot/version/hash, confirmation actor/channel/time, confirmation expiry, and invalidation when the draft changes.
- A deterministic command handler/action whose exact name is **Create Order**.
- A system/automation actor strategy compatible with non-null `customer_orders.created_by`, or a deliberate schema/audit change.
- Branch tenancy across all relevant domain records and queries.
- Product pricing source, if the bot is expected to quote totals.
- Rate limits, observability, retry/dead-letter operations, data retention, and privacy controls.

## Security and data-integrity risks

1. **Cross-branch leakage (critical):** there is currently no branch isolation. Catalog questions, PSIDs, stock, customers, integrations, and created orders would all be global.
2. **Forged webhooks (critical):** accepting unsigned POSTs could create conversations, send messages, or reach order actions. Validate the raw request body with HMAC-SHA256 and use constant-time comparison.
3. **Duplicate/replayed events (critical):** Meta and queues retry. Persist an immutable event key under a unique constraint before dispatch; make processing and order creation idempotent. Do not rely on an in-memory/cache lock alone.
4. **AI authority confusion (critical):** the current parser emits an `intent=create_order`. That must never authorize creation. AI output is untrusted structured input; it may update a draft or suggest a reply only.
5. **Confirmation races (critical):** stock and draft data can change after a summary. Bind confirmation to a version/hash, expire it, invalidate it after edits, and transactionally recheck stock and state during **Create Order**.
6. **Concurrent order creation (critical):** lock the conversation/draft row and enforce a unique source/idempotency key on `customer_orders`. Improve order-number allocation or retry unique collisions.
7. **Human takeover race (high):** check takeover state immediately before generating and again before sending an automated reply. A queued reply prepared before takeover must be suppressed at send time.
8. **Echo/self-message loops (high):** Messenger webhook events can include Page echoes. Record them for audit if useful, but never feed outbound echoes back to AI.
9. **PSID misuse (high):** a PSID is Page-scoped. Uniqueness must be `(facebook_page_id, psid)` (and branch scope if Page mapping permits); `customers.handle` is insufficient.
10. **Secrets exposure (high):** Page access token, app secret, and verify token must be encrypted at rest or sourced from secret-managed environment configuration, never returned to the browser or logs. Existing generic integration UI assumes `api_key` and needs Facebook-specific handling.
11. **Prompt injection/data leakage (high):** never give the model unrestricted database/tool access. Supply a branch-filtered product projection and a strict response schema; escape/sanitize staff-rendered content.
12. **Outbound API abuse (high):** apply rate limits, Graph API timeouts, bounded retries with jitter, and error classification. Respect Meta messaging policies/windows and stop repeated failure loops.
13. **PII/privacy (high):** PSIDs, names, addresses, message bodies, and payment preferences require access control, log redaction, retention/deletion policy, backups policy, and appropriate consent/privacy notices.
14. **Partial reservation mismatch (high):** current order creation allows shortages. Messenger confirmation should require full availability or very clearly implement backorder consent; the initial implementation should fail closed and create no order when stock is insufficient.
15. **Payment ambiguity (high):** a preferred payment method is not proof of payment. Keep it separate from `order_payments` and `amount_paid`.
16. **Actor integrity (medium):** webhook jobs have no authenticated user, while orders require `created_by`. Use an auditable automation user per branch/configuration or evolve actor fields explicitly; never impersonate the customer or arbitrary staff.
17. **Global integration row (medium):** `integrations.provider` is globally unique and current accessors use `forProvider()`. This cannot support different Facebook Pages/credentials or AI providers per branch without schema/query changes.
18. **Payment race (existing, medium):** concurrent payment requests can both pass balance validation because the order is not locked. Not introduced by Messenger, but relevant if payment automation is later added.

## Recommended data model changes

Names can be adjusted to project conventions during implementation, but the constraints and ownership boundaries should remain.

### Phase-zero branch foundation

- `branches`: `id`, unique code, name, status, timestamps.
- `users.branch_id` (or a user/branch pivot if staff can operate multiple branches), with an explicit current-branch mechanism.
- Add `branch_id` to `products`, `customers`, `customer_orders`, `supplier_orders`, `stock_movements`, `ai_order_drafts`, and `integrations`; inventory cells inherit branch from product, though denormalizing `branch_id` onto cells can make hard constraints/querying safer.
- Replace global unique constraints with tenant-aware constraints where appropriate: `(branch_id, products.code)`, `(branch_id, customer_orders.order_number)`, and `(branch_id, integrations.provider, provider_account_id)`.
- Add policies/scopes or a mandatory branch query abstraction plus route-binding checks. Avoid relying solely on request-provided `branch_id`.
- Backfill existing records into a default branch before making columns non-null.

### Facebook integration records

- `facebook_pages`: `id`, `branch_id`, `integration_id` (or encrypted credentials directly), Page ID, name, status, Graph API version, encrypted Page access token, optional encrypted app secret/verify token reference, timestamps. Unique Page ID.
- Prefer application-level app secret/verify token in `config/services.php` if a single Meta app serves all Pages; never store plaintext secrets.
- Evolve `integrations` so a provider can have multiple tenant/account rows. The present `provider` unique key and `forProvider()` API are inadequate.

### Webhook inbox and messages

- `facebook_webhook_events`: `id`, `facebook_page_id`, `branch_id`, `event_key`, event type, sender PSID, recipient Page ID, Meta timestamp, encrypted or access-controlled raw payload, processing status, attempts, processed/failed timestamps, error code/message, timestamps. Unique `(facebook_page_id, event_key)`; build a deterministic fallback hash when Meta does not provide a message ID.
- `facebook_conversations`: `id`, `branch_id`, `facebook_page_id`, PSID, optional `customer_id`, state enum, control mode (`ai`/`human`), assigned staff user, takeover/release timestamps, last inbound/outbound timestamps, optimistic version, timestamps. Unique `(facebook_page_id, psid)`.
- `facebook_messages`: conversation/event association, direction, Meta message ID, type, body/attachment metadata, sender kind, AI-generated flag, send status/error, timestamps. Unique nullable Meta message ID scoped to Page; include an application idempotency key for outbound sends.

### Structured order draft and confirmation

- Prefer dedicated `messenger_order_drafts` and `messenger_order_draft_items`, linked one-to-one/current to a conversation. This avoids overloading `ai_order_drafts`, whose design is staff-created, one-message, JSON-heavy, and requires a creator concept.
- Draft fields: `branch_id`, conversation/customer, customer name, PSID snapshot, fulfillment method, delivery address fields, payment preference, state, version, summary text/JSON, summary hash, summarized/confirmed timestamps, confirmation actor type/id/message ID, expiry, created order ID, and timestamps.
- Draft item fields: inventory cell ID, quantity, quoted unit price, product/variant snapshot, availability snapshot, timestamps. Unique/merge repeated cells.
- Add `source_type`/`source_id` or `external_source`/`external_id` to `customer_orders`, with a unique constraint such as `(branch_id, source_type, external_id)`. This is the final database guard against duplicate **Create Order** execution.
- Add Messenger fulfillment details to `customer_orders`: at minimum `delivery_address` (prefer structured address fields) alongside existing `delivery_method`. Add `payment_method_preference`; do not write `order_payments` from this field.
- Add Facebook identity through a normalized `customer_channel_identities` table (`branch_id`, customer, provider, provider account/Page ID, external user ID/PSID, display metadata), unique on provider account plus external user ID. This is more extensible than a single `customers.facebook_psid` column.
- Consider an `order_confirmations` audit table if confirmation evidence must be retained independently of draft retention.

Use database foreign keys, non-null tenant keys after backfill, check/application enums, maximum lengths, and unique keys as the primary integrity layer. Index conversation state/control mode, event processing status, PSID lookup, and order source lookup.

## Recommended application architecture

### Inbound boundary

- `FacebookWebhookController` has only two responsibilities: verify the GET challenge and validate/parse the signed POST envelope.
- `VerifyFacebookWebhookSignature` middleware (or a small verifier service called before JSON parsing) computes HMAC over the exact raw body.
- `FacebookWebhookIngestService` resolves Page to branch, derives event keys, inserts inbox rows with `insertOrIgnore`/unique-conflict handling, and dispatches processing only for newly inserted rows.
- Respond `200` promptly after durable ingestion. Do not call AI, Graph API, or create orders in the webhook request.

### Queued processing and conversation orchestration

- `ProcessFacebookWebhookEvent` loads the inbox row, locks/claims it, ignores echoes/unsupported events, upserts the conversation/identity, stores the inbound message, and invokes the orchestrator. It records terminal success/failure safely across retries.
- `MessengerConversationService` is a deterministic state machine. It collects required fields, invalidates a previous summary after any edit, checks human control, and decides the next allowed application action.
- `MessengerAiService` uses the existing `AiProviderManager` for extraction/response generation but returns a strict DTO/schema. It cannot persist orders or call inventory mutation methods. Product context is branch-filtered and minimal.
- `SendFacebookMessage` (or an outbox plus sender job) sends through `FacebookGraphClient`. It checks human takeover immediately before send and uses an outbound idempotency key. Customer-requested automated acknowledgements after a staff takeover should be explicitly designed; default is no AI reply.

### Human takeover

- Staff inbox/controller routes remain under `auth`, active-user middleware, branch enforcement, and new permissions such as `view messenger conversations`, `take over messenger conversations`, and `create messenger orders`.
- **Take Over** atomically changes `control_mode` to human, assigns staff, increments version, and cancels/suppresses pending AI outbound messages. **Return to AI** is a separate explicit staff action with an audit entry.
- Every AI-processing and outbound-send path checks control mode. This double check closes the queued-work race.
- Staff messages should be sent through the same message/outbox audit path and marked as staff-authored.

### Confirmation and the single Create Order action

The state machine should enforce this sequence:

1. Collect and validate name, Page-scoped PSID, exact product cells/quantities, delivery or pickup, delivery address when needed, and payment preference.
2. Resolve products without guessing ambiguous variants. Require the customer or staff to choose when ambiguous.
3. Check full available stock for every item. If unavailable, explain and keep the draft editable; do not present it as confirmable.
4. Persist a final summary snapshot and hash/version, then send/show it with a clear confirmation instruction.
5. Record explicit confirmation from an inbound customer message/button tied to that summary, or an authorized staff confirmation action. Mere phrases earlier in the conversation, AI intent classification, confidence, or “readiness” are not confirmation.
6. Only now expose/dispatch one deterministic command/action with the exact name **Create Order**. AI tools must not invoke it automatically. If tool calling is used, the tool registry must not include this action for the model.
7. `CreateMessengerOrderService` locks the draft/conversation and all inventory cells in stable ID order, verifies branch ownership, control/confirmation state, summary hash/version, expiry, no existing order/source key, active products, and full stock.
8. It creates/links the customer and channel identity, creates the order and items, copies fulfillment/address/payment preference, uses an auditable automation/staff actor, calls existing `CustomerOrderService::reserveOrder()`, requires every line to be fully reserved, links the draft/order, and writes activity/confirmation audit—all in one transaction.
9. A unique source key makes repeated customer messages, staff clicks, webhook replays, and job retries return the already-created order rather than creating another.
10. Send the order number only after transaction commit. On a stock race, create no order, invalidate confirmation, refresh the summary, and ask for reconfirmation.

The existing `CustomerOrderController::store()` and `AiOrderDraftService::convertDraftToCustomerOrder()` duplicate order-building logic. Before adding a third path, extract a shared application service (for example `CreateCustomerOrderService`) that accepts a validated command/DTO and consistently creates items, reserves stock, and logs activity. Keep Messenger-specific confirmation/idempotency in the Messenger command service around that core.

### Meta client and configuration

- Add Facebook settings to `config/services.php` and `.env.example`; application code reads only `config()`.
- Use Laravel's HTTP client with explicit connect/request timeouts, `retry` only for safe/transient failures, redacted logging, and Graph error parsing.
- Pin the Graph API version in configuration and test payload fixtures. Do not hardcode secrets or version strings throughout services.

## Exact proposed files

Final names may follow migrations' generated timestamps, but this is the intended file-level scope.

### Create

- `routes/api.php` — public Meta webhook routes with purpose-specific throttling; register it from `bootstrap/app.php`. Alternatively a narrowly exempted web route is possible, but a stateless API route is cleaner.
- `app/Http/Controllers/Webhooks/FacebookWebhookController.php`
- `app/Http/Middleware/VerifyFacebookWebhookSignature.php`
- `app/Http/Requests/Webhooks/FacebookWebhookRequest.php`
- `app/Services/Facebook/FacebookWebhookIngestService.php`
- `app/Services/Facebook/FacebookWebhookEventKey.php`
- `app/Services/Facebook/FacebookGraphClient.php`
- `app/Services/Facebook/MessengerConversationService.php`
- `app/Services/Facebook/MessengerAiService.php`
- `app/Services/Facebook/CreateMessengerOrderService.php` — exposes the single application action **Create Order**.
- `app/Jobs/ProcessFacebookWebhookEvent.php`
- `app/Jobs/SendFacebookMessage.php` (or a transactional outbox sender equivalent).
- `app/Models/Branch.php`
- `app/Models/FacebookPage.php`
- `app/Models/FacebookWebhookEvent.php`
- `app/Models/FacebookConversation.php`
- `app/Models/FacebookMessage.php`
- `app/Models/MessengerOrderDraft.php`
- `app/Models/MessengerOrderDraftItem.php`
- `app/Models/CustomerChannelIdentity.php`
- corresponding enums for event status, conversation control/state, message direction/status, fulfillment method, confirmation actor, and draft status.
- migrations for branch foundation/backfill/constraints; Facebook pages; webhook events; conversations; messages; structured drafts/items; customer identities; and Messenger fields/source uniqueness on orders.
- model factories for every new persisted model.
- `app/Http/Controllers/FacebookConversationController.php` and Form Requests for takeover, return-to-AI, staff confirmation, staff reply, and **Create Order**.
- staff views and JavaScript for a Messenger inbox/conversation panel if in-app operations are in scope (recommended): `resources/views/facebook-conversations/*` and `resources/js/facebook-conversations.js`.
- `tests/Feature/FacebookWebhookTest.php`
- `tests/Feature/FacebookWebhookIdempotencyTest.php`
- `tests/Feature/FacebookConversationTest.php`
- `tests/Feature/FacebookHumanTakeoverTest.php`
- `tests/Feature/FacebookCreateOrderTest.php`
- `tests/Feature/FacebookBranchIsolationTest.php`
- `tests/Feature/FacebookGraphClientTest.php`
- `tests/Feature/FacebookQueueRetryTest.php`

### Modify

- `bootstrap/app.php` — register API routing and signature middleware alias if used.
- `config/services.php` and `.env.example` — Meta configuration without real secrets.
- `routes/web.php` — authenticated staff inbox/takeover/confirmation/Create Order routes and permissions.
- `app/Models/User.php`, `Product.php`, `ProductColorSize.php`, `Customer.php`, `CustomerOrder.php`, `SupplierOrder.php`, `StockMovement.php`, `Integration.php`, and `AiOrderDraft.php` — branch/identity/source relationships and fillable/casts where applicable.
- `app/Services/InventoryService.php` and `CustomerOrderService.php` — preserve behavior, but add explicit branch assertions and a lock-aware/full-reservation pathway rather than duplicating stock mutation logic.
- `app/Http/Controllers/CustomerOrderController.php` and `app/Services/AiOrderDraftService.php` — delegate common order creation to a shared service.
- Create `app/Services/CreateCustomerOrderService.php` (or equivalent command handler) and associated DTO; use it from all creation paths.
- `app/Models/Integration.php`, `AiProviderManager.php`, `IntegrationController.php`, related requests/views/tests — replace global provider lookup/uniqueness with branch/account-aware behavior and support Facebook-specific secrets safely.
- `app/Services/Ai/AbstractAiProvider.php` and provider prompts/schema — add the required Messenger fields for conversational extraction, while removing any implication that `intent=create_order` authorizes an order.
- `database/seeders/PermissionSeeder.php` — Messenger permissions and branch-aware roles.
- `resources/views/partials/sidebar.blade.php`, `resources/js/app.js`, and `vite.config.js` — staff inbox navigation/assets if UI is approved.
- Existing factories and relevant tests to require/establish branch context.
- Deployment documentation/process configuration to run a supervised queue worker.

Files that should **not** be changed to deduct stock at a new stage: fulfillment must continue through `CustomerOrderService::fulfillOrder()` and `InventoryService` movements.

## Phased implementation plan

### Phase 0 — tenancy decision and branch isolation

1. Confirm whether one user belongs to one branch or can switch among several, and map each Facebook Page to exactly one branch.
2. Add branches, backfill existing data to a default branch, tenant-aware unique keys, scopes/policies/middleware, and cross-branch route-binding protection.
3. Add branch-isolation tests across products, stock, customers, orders, finance, integrations, reports, AI, and Messenger.
4. Do not expose the webhook to production until this phase passes.

### Phase 1 — refactor the order creation boundary

1. Extract common order creation from manual and AI-draft paths into a validated command service.
2. Preserve current staff behavior, including partial reservation and supplier-order redirects.
3. Add a Messenger option that requires full reservation and fails atomically.
4. Make source idempotency and order-number concurrency safe.
5. Run the existing order/inventory/AI/finance suite to prove no behavior regression.

### Phase 2 — secure webhook ingestion

1. Add Meta configuration, Page mapping, GET challenge, raw-body signature validation, throttling, and payload size limits.
2. Persist webhook events with unique replay keys and immediately queue only newly inserted events.
3. Add fixtures/tests for valid, invalid, replayed, batched, echo, malformed, and unsupported events.
4. Configure and observe the production queue worker/failed jobs.

### Phase 3 — conversations, AI collection, and outbound messaging

1. Add conversation/messages/customer identity and a deterministic state machine.
2. Add branch-filtered catalog Q&A and strict AI structured output.
3. Collect all required fields; do not guess variants, address, quantity, payment preference, or fulfillment method.
4. Add the Graph client/outbox sender with retry, rate limiting, redaction, and send-time takeover check.
5. Test prompt injection boundaries and Graph failures.

### Phase 4 — human takeover and staff operations

1. Add permissions and staff inbox/read views.
2. Add atomic Take Over, staff reply, Return to AI, staff confirmation, and audit entries.
3. Suppress queued AI replies after takeover, including race-condition tests.
4. Ensure every query and action is branch-scoped.

### Phase 5 — confirmation and Create Order

1. Add structured draft/items, final summary version/hash, confirmation evidence and expiry.
2. Require full stock before presenting confirmation.
3. Implement the sole explicit **Create Order** command, unavailable until explicit customer/staff confirmation.
4. Revalidate under locks; create/link/reserve atomically; use a unique source key to return the existing order on repeats.
5. Add adversarial tests: AI says ready, no confirmation, old confirmation after edit, duplicate webhook, duplicate click, concurrent confirmations, stock race, worker retry, and cross-branch IDs.

### Phase 6 — hardening and rollout

1. Add metrics/alerts for signature failures, duplicate rate, queue age, processing failures, takeover suppression, Graph errors, and order-conversion outcomes.
2. Establish PII retention/deletion, staff access, secret rotation, Page token renewal, backup, and incident procedures.
3. Run in shadow mode (ingest/store without auto-reply), then staff-only drafting, then limited AI replies, then enable customer confirmation/Create Order for one branch/Page.
4. Keep a per-Page kill switch that stops AI replies and order actions while retaining secure event ingestion.
5. Expand only after idempotency, stock, confirmation, and branch-isolation telemetry is clean.

## Decisions required before implementation approval

- Branch membership model and the branch to which existing records will be backfilled.
- Whether each branch has its own Facebook Page and AI credentials.
- Authoritative product price source and whether Messenger may quote prices.
- Allowed fulfillment values and required structured address fields.
- Allowed payment preference values; confirm this is preference only, not payment capture.
- Exact customer confirmation phrases/buttons, confirmation expiry, and whether staff may confirm on a customer's behalf.
- Whether insufficient stock blocks Messenger order creation (recommended) or supports an explicitly confirmed backorder policy.
- Automation actor strategy for `created_by` and activity auditing.
- Message/PII retention period and staff roles allowed to view Messenger conversations.

No implementation should begin until the branch model and these business decisions are approved.
