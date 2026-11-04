# Official Email, IMAP, Templates & Horizon Operations Runbook

**Phase:** 08 — Official Email, IMAP, Templates, and Workflow Rules  
**Requirements:** `MAIL-01`, `MAIL-02`, `MAIL-03`, `MAIL-04`, `MAIL-05`  
**Decisions Enforced:** `DEC-01` (Multi-Workspace Tenancy Isolation), `DEC-02` (Zero Dynamic Evaluation), `DEC-11` (Append-Only Audit Logging & Secret Exclusion)

---

## 1. Information Desk Mailbox Architecture (`MAIL-01`, `MAIL-02`)

### 1.1 Overview
The EMK Membership CRM integrates directly with the workspace's official Information Desk email account (e.g., `infodesk@emkcenter.org` for `ws-emk-dhaka`) via an adapter-isolated IMAP/SMTP synchronization pipeline:

- **Adapter Contract:** `App\Domain\Mail\Contracts\ImapClientInterface` implemented by `App\Domain\Mail\Services\ImapMailboxAdapter`.
- **Synchronization Service:** `App\Domain\Mail\Services\MailboxSyncService` polls inbound and outbound folders and correlates messages into workspace-scoped `EmailThread` and `EmailMessage` records.
- **Scheduled Command:** `php artisan crm:mailbox-sync` runs automatically every 5 minutes (`Schedule::command('crm:mailbox-sync')->everyFiveMinutes()` in `routes/console.php`).

### 1.2 RFC-5322 Threading & Entity Matching
1. **Idempotency Guard:** Every message is keyed by `(workspace_id, message_id_header)` (`uk_email_messages_workspace_msgid`). Re-running `crm:mailbox-sync` skips previously ingested messages and increments `skipped_duplicates`.
2. **Thread Correlation Order:**
   - First, `MailboxSyncService` inspects RFC-5322 `In-Reply-To` and `References` headers to attach replies to their parent `EmailThread`.
   - Second, if no header match exists, it matches by `(workspace_id, participant_email)` and normalized subject (stripping `Re:` / `Fwd:` prefixes).
3. **Workspace Lead & Member Matching:**
   - Counterparty email addresses are matched against active workspace `Member` records first (linking both `member_id` and originating `lead_id`), and workspace `Lead` records second.
   - Because converted `Member` records retain their `lead_id`, the `EmailThreadPanel` embedded on `/crm/{workspace}/members/{member:uuid}` displays both pre-conversion Lead correspondence and post-conversion Member correspondence in one unified chronological view.
4. **HTML Sanitization:** All inbound HTML bodies are sanitized on ingestion (`MailboxSyncService::sanitizeHtml`) to strip `<script>`, `<iframe>`, `<object>`, `<embed>`, and inline `on*` event attributes (`T-08-01`).

### 1.3 Manual Synchronization & Staff Reply Operations
- Run a manual sync for all active workspaces:
  ```bash
  php artisan crm:mailbox-sync
  ```
- Run a manual sync for a single workspace:
  ```bash
  php artisan crm:mailbox-sync --workspace=ws-emk-dhaka
  ```
- Staff with `leads.communicate` or `members.edit` authority can also click **Sync Mailbox** or compose replies directly inside the **Official Emails** tab on any Lead Profile (`/crm/{workspace}/leads/{lead}`) or Member Profile (`/crm/{workspace}/members/{member:uuid}`). Every outbound reply logs `official_email_reply_sent` to `audit_logs`.

---

## 2. Environment Configuration & Secret Rotation (`DEC-11`)

### 2.1 Required Environment Variables
All IMAP and SMTP credentials reside strictly in deployment-managed environment variables (`.env` / platform secret store) and are read through `config/mail.php`:

```dotenv
# Outbound SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_USERNAME=infodesk@emkcenter.org
MAIL_PASSWORD=***REDACTED_SECRET***
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=infodesk@emkcenter.org
MAIL_FROM_NAME="EMK Center Information Desk"

# Inbound IMAP Configuration
IMAP_HOST=outlook.office365.com
IMAP_PORT=993
IMAP_ENCRYPTION=ssl
IMAP_VALIDATE_CERT=true
IMAP_USERNAME=infodesk@emkcenter.org
IMAP_PASSWORD=***REDACTED_SECRET***
IMAP_INBOX_FOLDER=INBOX
IMAP_SENT_FOLDER="Sent Items"
```

### 2.2 Secret Exclusion Invariant (`DEC-11`, `T-08-02`, `T-08-07`)
- **Zero Database / UI Exposure:** IMAP and SMTP passwords (`IMAP_PASSWORD`, `MAIL_PASSWORD`) are never stored in database tables, rendered in Livewire component properties, or displayed in administrative UI screens.
- **Exception & Audit Log Redaction:** `QueuedEmailDispatcher::sanitizeFailureMessage()` and `ImapMailboxAdapter::redactSecrets()` scrub URI credentials (`smtp://user:pass@host`) and secret key-value pairs (`password=...`, `secret=...`, `token=...`) to `[REDACTED]` before persisting `email_messages.failure_reason` or `audit_logs`.

### 2.3 Zero-Downtime Credential Rotation Procedure
1. Generate the new mailbox password or app-specific secret in the email provider admin console.
2. Update `IMAP_PASSWORD` and `MAIL_PASSWORD` in the production environment configuration.
3. Clear cached configuration and gracefully restart Horizon workers so queued jobs pick up the new credentials:
   ```bash
   php artisan config:cache
   php artisan horizon:terminate
   ```
4. Verify connectivity by running `php artisan crm:mailbox-sync --workspace=ws-emk-dhaka` and checking `/crm/ws-emk-dhaka/settings/email-delivery` for any failed dispatches.

---

## 3. Laravel Horizon Queue Supervision & Deduplication Invariant (`MAIL-04`)

### 3.1 Queued Delivery Pipeline
All lifecycle and workflow emails are queued through `App\Domain\Mail\Services\QueuedEmailDispatcher` and processed asynchronously by `App\Jobs\DispatchOfficialEmailJob` under Laravel Horizon:

- **Retry & Backoff Policy:** `$tries = 3`, `$backoff = [30, 120, 300]` seconds (`T-08-08`).
- **Unique Job Lock:** Implements `ShouldBeUnique` with `$uniqueFor = 300` keyed by `email-msg-{id}`.

### 3.2 Dual-Layer Deduplication Invariant
To guarantee that retries or repeated domain events never send duplicate emails to applicants or members:
1. **Dispatcher Layer (`QueuedEmailDispatcher`):** Computes a deterministic `deduplication_key` in the format:
   ```text
   {workspace_id}:{template_key}:{recipient_email}:{entity_key}
   ```
   If an `EmailMessage` row with that `deduplication_key` already exists in `'queued'` or `'sent'` status, `dispatchTemplate()` returns the existing record immediately without creating a duplicate row or pushing a duplicate job.
2. **Worker Layer (`DispatchOfficialEmailJob`):** Acquires a pessimistic row-level lock (`EmailMessage::withoutGlobalScopes()->lockForUpdate()->find(...)`) inside a transaction and immediately returns if `status === 'sent'`.

### 3.3 Inspecting & Retrying Failed Deliveries
1. Navigate to **Email Delivery Monitor** at `/crm/{workspace}/settings/email-delivery` (requires `platform_services.manage_settings` / Workspace Manager authority).
2. Filter by status `Failed` to inspect the sanitized `failure_reason` diagnostic.
3. Click **Retry Delivery** on any failed message (`EmailDeliveryMonitor::retryMessage`). This resets the message status to `'queued'`, dispatches a fresh `DispatchOfficialEmailJob`, and writes an immutable `official_email_retried` event to `audit_logs`. Already-`sent` messages cannot be re-queued.

---

## 4. Bilingual Email Templates & Bounded Workflow Rules (`MAIL-03`, `MAIL-05`)

### 4.1 Bilingual Template Catalog & Zero-Eval Rendering (`MAIL-03`, `DEC-02`)
Each workspace is provisioned via `EmailTemplateSeeder` with 9 canonical bilingual (English + Bengali) templates:
1. `application_receipt` — Application Submission Receipt
2. `missing_items` — Missing / Resubmission Items Request
3. `application_rejection` — Application Rejection Notice
4. `conversion_confirmation` — Membership Approval & Paid Invoice Confirmation
5. `portal_activation` — Member Portal Account Activation
6. `id_card_ready` — Physical ID Card Ready for Pickup
7. `renewal_window` — 30-Day Membership Renewal Window Reminder
8. `membership_expiry` — Membership Expiration & Inactive Status Notice
9. `request_outcome` — Profile / Email Change Request Outcome

**Allowlisted Placeholders (`EmailTemplateRenderer::ALLOWED_PLACEHOLDERS`):**
- `{{applicant_name}}`, `{{member_name}}`, `{{member_id}}`, `{{workspace_name}}`, `{{expiry_date}}`, `{{invoice_number}}`, `{{amount_bdt}}`, `{{reason}}`, `{{action_url}}`

**Security Invariant (`DEC-02`, `T-08-04`, `T-08-05`):**
`EmailTemplateRenderer` performs deterministic `strtr` replacement strictly over the 9 allowlisted tokens and HTML-escapes all variable values (`htmlspecialchars`) before injection into HTML bodies. Dynamic Blade compilation, `eval()`, and `create_function()` are strictly prohibited and enforced by `tests/Arch/ModuleRegistryArchTest.php`.

Managers can customize English and Bengali copy and preview rendered output in `en`, `bn`, or `bilingual` mode at `/crm/{workspace}/settings/email-templates`. Every template save logs `email_template_updated` to `audit_logs`.

### 4.2 Bounded Workflow Rules & Daily Milestone Scheduler (`MAIL-05`)
Authorized managers configure the 9 bounded lifecycle workflow triggers (`WorkflowRule::ALLOWED_TRIGGERS`) at `/crm/{workspace}/settings/workflow-rules`:
- `lead.submitted` -> `application_receipt`
- `lead.missing_items` -> `missing_items`
- `lead.rejected` -> `application_rejection`
- `lead.converted` -> `conversion_confirmation`
- `member.portal_activated` -> `portal_activation`
- `id_card.ready_for_pickup` -> `id_card_ready`
- `member.renewal_window_open` -> `renewal_window` (default `days_offset = 30`)
- `member.expired` -> `membership_expiry`
- `member.change_request_outcome` -> `request_outcome`

**Daily Membership Milestone Scheduler (`crm:process-membership-milestones`):**
- Scheduled daily at `01:00` (`Schedule::command('crm:process-membership-milestones')->dailyAt('01:00')`).
- Scans active workspace members whose `expiry_date` falls within the configured `days_offset` window and queues the `renewal_window` notice using the term-scoped deduplication key `renewal_window:{member_id}:{expiry_date}`.
- Transitions active members whose `expiry_date` is strictly before today (`expiry_date < today`) to `status = 'inactive'`, records an append-only `member_status_changed` audit event, and queues the `membership_expiry` notice using `expired:{member_id}:{expiry_date}`.
