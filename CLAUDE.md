# bizz_connect — Laravel Backend

## Auth

- **Method:** Bearer Token via Laravel Sanctum
- Token stored in `localStorage` key `bc_token` on the frontend
- Token created with `createToken('api')` on register/login
- Email verification required (`MustVerifyEmail`) for most routes
- Password reset: email sends a code (not a link)
- Magic link exchange: `POST /api/auth/magic/exchange`

## Route Groups

```
Public (no auth)
  POST /api/auth/register|login|magic/exchange
  POST /api/auth/password/request|resend|verify
  GET  /api/email/verify/{id}/{hash}          signed URL
  GET  /api/guides/ask|categories|...
  GET  /api/business-card/public/{slug}
  GET  /api/countries, /states, /cities

auth:sanctum (token required)
  GET/PATCH /api/auth/me
  POST      /api/auth/logout
  GET       /api/email/verified
  POST      /api/email/verification-notification

auth:sanctum + verified (most CRUD)
  /api/contacts/**
  /api/tags/**
  /api/reminders/**
  /api/notifications/**
  /api/companies/** + /api/company
  /api/business-cards/** + /api/business-card
```

OPTIONS `/{any}` → CORS preflight handled manually.

## Controllers (app/Http/Controllers)

| Controller | Key Actions |
|------------|-------------|
| AuthController | register, login, magicExchange, passwordRequest/Resend/Verify, verifyEmail, me, updateMe, logout |
| ContactController | CRUD, export (Excel), exportTemplate, import (Excel/CSV), bulkDelete, attachTags, detachTag |
| ReminderController | CRUD, markDone, bulkStatus/Delete, attachContacts, detachContact, pivotIndex, byContact |
| BusinessCardController | CRUD, showPublic (view count++), connect (slug-based) |
| TagController | CRUD |
| CompanyController | CRUD |
| NotificationController | index, markRead, markDone, bulkRead, destroy |
| AiGuideController | ask, askStream (SSE), getCategories, getByCategory, getKnowledge, getPopular, search |
| ContactTagController | tag-contact attach/detach |
| LocationController | countries(), states($code), cities($code) |

All controllers use `@OA` annotations for Swagger docs (generated to `storage/api-docs/`).

## Models (app/Models)

| Model | Traits / Notes |
|-------|----------------|
| User | HasApiTokens, MustVerifyEmail; relations: company, businessCard, address |
| Contact | SoftDeletes; full-text search on name/company/email/phone; scope ownedBy, withReminder |
| Tag | BelongsToMany contacts |
| Reminder | SoftDeletes; BelongsTo contact + BelongsToMany contacts via `contact_reminder` pivot |
| BusinessCard | auto slug generation; `is_public`, `view_count` |
| Company | company profile |
| Address | city_id → state_id → country_id hierarchy |
| Country / State / City | location hierarchy |
| AppKnowledge | AI knowledge base: key, title, category, content (JSON), platform, locale |
| UserNotification | read_at, done_at |

## Database Migrations (18 total)

Order by prefix `2025_12_10_*` then `2025_12_23_*` then `2026_04_03_*`:

```
000000 create_users_table
000001 create_audit_logs_table
000002 create_personal_access_tokens_table
000003 create_user_notifications_table
000004 create_tags_table
000005 create_countries_and_states_tables
000006 create_cities_table
000007 create_addresses_table
000008 create_companies_table
000009 create_business_cards_table
000010 create_contacts_table            (softDeletes, full-text indexes)
000011 create_reminders_table           (softDeletes)
000012 create_contact_tag_pivot
000013 create_contact_reminder_table    (is_primary flag)
000014 add_foreign_keys_to_users_table
2025_12_23 create_app_knowledge_table
2026_04_03 add_address_id_to_contacts_table
2026_04_03 make_reminders_contact_id_nullable
```

## Services (app/Services)

### GeminiService
- Calls Google Gemini 2.0 Flash API
- `generateGuide($question, $context)` — standard response
- `generateGuideStream($question, $callback, $context)` — SSE streaming
- `getRelevantKnowledge($question, $platform, $locale)` — searches `app_knowledge` table
- `buildSystemInstruction()` — platform-aware (web vs mobile)
- `buildPrompt()` — locale-aware (vi/en)
- Knowledge base cached for performance

## Middleware (app/Http/Middleware)

| Middleware | Purpose |
|------------|---------|
| ForceJsonResponse | All responses return JSON |
| EnsurePlus | Feature gating (premium features) |
| EncryptCookies | Cookie encryption |
| VerifyCsrfToken | CSRF protection |
| TrustProxies | Proxy trust |

## Exports & Imports (app/Exports, app/Imports)

- `ContactsExport` — exports contacts to Excel
- `ContactsTemplateExport` — blank import template
- `ContactsImport` — imports Excel/CSV with validation

## Requests (app/Http/Requests)

- `StoreContactRequest` — validation for creating contacts
- `UpdateContactRequest` — validation for updating contacts

## Resources (app/Http/Resources)

- `ContactResource` — formats Contact model for API
- `TagResource` — formats Tag model

## Key Config Files

| File | Purpose |
|------|---------|
| config/cors.php | CORS allowed origins / headers |
| config/sanctum.php | Token expiry, guard settings |
| config/l5-swagger.php | Swagger output path, annotations scan path |
| config/services.php | Gemini API key, Postmark, Slack |
| config/mail.php | SMTP settings |

## Environment (.env key variables)

```
APP_URL=http://127.0.0.1:8000
FRONTEND_URL=http://localhost:5173
DB_HOST=153.92.15.63
DB_DATABASE=u564264509_biz_connect
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=no-reply@biz-connect.online
MAIL_ENCRYPTION=ssl
QUEUE_CONNECTION=database
GEMINI_API_KEY=...
```

## Console Commands

- `app/Console/Commands/GenerateUpcomingNotifications.php` — scheduled command to create notifications for upcoming reminders

## Dev Commands

```bash
php artisan serve                # port 8000
php artisan queue:listen
php artisan pail --timeout=0     # log viewer
php artisan migrate
php artisan l5-swagger:generate  # regenerate API docs
composer dev                     # runs all of the above concurrently
composer test                    # PHPUnit tests
```
