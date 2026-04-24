# Bizz Connect — Laravel 11 Backend API

> RESTful API backend for Bizz Connect, a CRM-style business networking platform. Built solo end-to-end: system design, database schema, business logic, AI integration, and deployment.

---

## Table of Contents

- [Project Overview](#project-overview)
- [Tech Stack](#tech-stack)
- [System Architecture](#system-architecture)
- [Modules & Features](#modules--features)
- [My Role](#my-role)
- [Notable Implementations](#notable-implementations)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Installation & Setup](#installation--setup)
- [Deployment](#deployment)

---

## Project Overview

**What it does:**
Bizz Connect is a bilingual (Vietnamese/English) business contact management and networking platform. Users can manage a personal CRM of contacts, schedule follow-up reminders, create a shareable digital business card, organize contacts with tags, and get AI-powered assistance through a knowledge base backed by Google Gemini 2.0 Flash.

**Target market:**
Vietnamese professionals, freelancers, entrepreneurs, and SMEs who need a lightweight CRM with digital card exchange and AI assistant capabilities.

**Current status:**
Production-deployed. Backend hosted on Hostinger (MySQL 8 on `153.92.15.63`). Frontend on Render.com (`bizz-connect-web.onrender.com`). Custom domain: `biz-connect.online`. Email via Hostinger SMTP (`no-reply@biz-connect.online`).

---

## Tech Stack

| Layer | Technology | Version / Notes |
|-------|-----------|----------------|
| Framework | Laravel | 11.x |
| Language | PHP | 8.2+ |
| Authentication | Laravel Sanctum | 4.x — Bearer token |
| Database | MySQL | 8.0 (remote host `153.92.15.63`) |
| Queue | Laravel Queue | `database` driver |
| Cache | Laravel Cache | `database` driver |
| AI | Google Gemini 2.0 Flash | REST API via HTTP, SSE streaming |
| Excel / CSV | Maatwebsite/Laravel-Excel | 3.1.60 |
| API Docs | L5-Swagger / OpenAPI | 9.x — auto-generated at `/api/documentation` |
| Mail | Hostinger SMTP | SSL port 465, `no-reply@biz-connect.online` |
| Storage | Local disk | Images proxied through `/api/img/{path}` |
| Image Processing | PHP GD | Built-in, used for resize + recompress on upload |
| Scheduling | Laravel Scheduler | Artisan command for upcoming-reminder notifications |
| CORS | `fruitcake/laravel-cors` | Configured for production domain + all localhost ports |

---

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    React Frontend (Vite)                     │
│              bizz-connect-web.onrender.com                  │
└──────────────────────────┬──────────────────────────────────┘
                           │ HTTPS + Bearer Token
                           ▼
┌─────────────────────────────────────────────────────────────┐
│             Laravel 11 API  (biz-connect.online)            │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  │
│  │ Auth Layer   │  │ CRUD APIs    │  │ AI Guide (SSE)   │  │
│  │ (Sanctum)    │  │ Contacts     │  │ Gemini 2.0 Flash │  │
│  │ Email Verify │  │ Tags         │  │ Knowledge Base   │  │
│  │ Pwd Reset    │  │ Reminders    │  │ (app_knowledge)  │  │
│  └──────────────┘  │ Notifications│  └──────────────────┘  │
│                    │ Business Card│                         │
│  ┌──────────────┐  │ Company      │  ┌──────────────────┐  │
│  │ Image Proxy  │  │ Location     │  │ Import / Export  │  │
│  │ /api/img/*   │  └──────────────┘  │ Excel / CSV      │  │
│  │ GD Resize    │                    │ (Maatwebsite)    │  │
│  └──────────────┘                    └──────────────────┘  │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐                        │
│  │   Scheduler  │  │    Queue     │                        │
│  │ (Upcoming    │  │ (DB driver)  │                        │
│  │ Notifications│  │ Queued Mail  │                        │
│  └──────────────┘  └──────────────┘                        │
└───────────────────────────────┬─────────────────────────────┘
                                │
                    ┌───────────▼──────────┐
                    │   MySQL 8 (Remote)    │
                    │  153.92.15.63        │
                    │  u564264509_biz_     │
                    │  connect             │
                    └──────────────────────┘
```

**Route security tiers:**

| Tier | Middleware | Applies to |
|------|-----------|-----------|
| Public | none | Auth endpoints, public business card, AI guide, location lookup |
| Token required | `auth:sanctum` | `/auth/me`, logout, resend verification |
| Token + verified email | `auth:sanctum` + `verified` | All CRUD: contacts, tags, reminders, notifications, business card, company |

---

## Modules & Features

### 1. Authentication & User Management

Full auth flow built from scratch with Laravel Sanctum:

- **Registration** — creates user, issues Sanctum token, triggers queued email verification
- **Login** — validates credentials, returns Bearer token
- **Email Verification** — signed URL flow; backend redirects to frontend `/verify-success?email=...` on success
- **Password Reset** — 3-step: request (sends 6-digit code via email) → verify code → reset (revokes all tokens)
- **Magic Link Exchange** — one-time cache-based code (`POST /api/auth/magic/exchange`) for passwordless login
- **Profile Update** — `PATCH /api/auth/me` updates name, phone, avatar, locale, timezone

Key files: `AuthController.php`, `PasswordResetCode.php` (Notification), `ForceJsonResponse` middleware

---

### 2. Contact Management (CRM Core)

The most feature-rich module — full CRM contact lifecycle:

**CRUD:**
- Create contact with auto-address upsert (city/state/country lookup)
- Update with circular duplicate detection (`duplicate_of_id`)
- Soft delete (SoftDeletes trait)
- Show with eager-loaded tags, address hierarchy, reminders

**Search & Filtering** (`GET /api/contacts` query params):

| Parameter | Behavior |
|-----------|----------|
| `q` | Full-text search across name, company, email, phone; also parses inline `#hashtags` as tag filters |
| `tag_ids` | Filter by tag IDs (comma-separated) |
| `tags` | Filter by tag names |
| `tag_mode` | `any` (OR) or `all` (AND) for multi-tag filter |
| `without_tag` | Exclude contacts bearing a specific tag |
| `with_reminder` / `without_reminder` | Filter by reminder presence |
| `status`, `after`, `before` | Filter by linked reminder state/date |
| `exclude_ids` | Exclude specific contact IDs (used by tag/reminder pickers) |
| `sort` | `name`, `-name`, `id`, `-id` |
| `per_page` | Up to 100 per page |

**Image Management** (PHP GD):
- Avatar upload → resize to max 400px wide, JPEG 75% quality
- Business card photo (front/back) → resize to max 1200px, JPEG 80%
- Remote URL copy → fetch from URL + resize + store locally
- All images served through `/api/img/{path}` proxy (CORS-safe, supports private storage)

**Import / Export** (Maatwebsite/Laravel-Excel):
- Export to XLSX or CSV with all active filters applied; includes tags as `#tag1, #tag2`
- Import from XLSX/CSV; match existing contacts by `id`, `email`, or `phone`; auto-creates tags and addresses; returns `{ created, updated, skipped, errors[] }`
- Blank import template download with example rows and column headers

**Bulk Operations:**
- `POST /api/contacts/bulk-delete` — delete multiple contacts in one request

Key files: `ContactController.php`, `ContactImageController.php`, `ContactsExport.php`, `ContactsImport.php`, `ContactsTemplateExport.php`, `Contact.php`

---

### 3. Tag System

User-owned labels with many-to-many contact relationships:

- Full CRUD (create, rename, delete tags)
- Attach tags to a contact by ID or by name (auto-creates new tag if name doesn't exist)
- Detach individual tags from a contact
- Bulk attach/detach contacts to a tag (from the tag side)
- Hashtag parsing in contact search: `q=John #vip` is parsed to apply `vip` tag filter

Key file: `TagController.php`, `Tag.php`, `contact_tag` pivot

---

### 4. Reminder System

Multi-contact reminders with pivot-table tracking:

- Create reminder linked to multiple contacts via `contact_reminder` pivot; first contact is auto-set as `is_primary`
- Filter reminders by: `contact_id`, `status` (pending/done/skipped/cancelled), `before`/`after` date, `overdue` flag
- Mark done, bulk status update, bulk delete (with pivot cleanup)
- Detach a contact from a reminder (auto-promotes next contact to primary)
- `GET /api/reminders/pivot` — custom paginated JOIN query returning contact-reminder edge table for the frontend's pivot view
- Reminder creation/update triggers `UserNotification` log automatically

Channels: `app`, `email`, `calendar` (stored, not yet fully dispatched)

Key file: `ReminderController.php`, `Reminder.php`, `contact_reminder` pivot table

---

### 5. In-App Notifications

Activity feed with automatic pruning:

- `UserNotification::log()` static method creates a notification and auto-prunes to max 50 per user
- Scoped list endpoint: `scope=all|unread|upcoming|past`
- Mark read, mark done, bulk mark read, delete
- Scheduled command (`GenerateUpcomingNotifications`) generates upcoming-reminder notifications via `php artisan schedule:run`
- Indexes: composite `(owner_user_id, status)` and `(owner_user_id, scheduled_at)` for query performance

Key file: `NotificationController.php`, `UserNotification.php`

---

### 6. Digital Business Card

Public shareable professional card:

- One card per user; slug auto-generated from full name + unique numeric suffix
- Supports: avatar, card front image, card back image, background image (all GD-resized on upload)
- Social links: LinkedIn, Facebook, Twitter; contact info: email, phone, mobile, website
- `is_public` toggle; `view_count` incremented on each public view
- `GET /api/business-card/public/{slug}` — no auth required; view_count++
- `POST /api/business-card/connect/{slug}` — scan another user's card; creates a Contact from their card data
- `POST /api/business-card/extract` — receives raw OCR text (from client-side Tesseract.js); regex-parses name, job title, email, phone (up to 2 numbers), website, LinkedIn URL

Key file: `BusinessCardController.php`, `BusinessCard.php`

---

### 7. AI Guide (Gemini 2.0 Flash + Knowledge Base)

Bilingual AI assistant (Vietnamese / English):

**Knowledge-base-first strategy:**
1. `POST /api/guides/ask` or `/ask-stream` receives question + `locale` + `platform`
2. Keyword extraction (Vietnamese + English stop words stripped)
3. `app_knowledge` table searched by: `searchable_text` (LIKE), `title`, JSON `sample_questions`, JSON `keywords`
4. If match found → format answer from KB; if not → call Gemini API as fallback

**Gemini integration** (`GeminiService.php`):
- Model: `gemini-2.0-flash-exp` via Google Generative Language REST API v1beta
- Standard call: `POST /v1beta/models/gemini-2.0-flash-exp:generateContent`
- Streaming: `POST .../streamGenerateContent?alt=sse` — reads 8192-byte chunks, fires callback per SSE data line
- System prompt is bilingual; platform-aware (web vs mobile differences)
- Knowledge context is fetched from DB and injected into Gemini prompt
- Knowledge cache: `Cache::remember("gemini_knowledge_{locale}_{platform}", 3600s)`

**SSE Streaming endpoint** (`GET /api/guides/ask-stream`):
- Laravel `response()->stream()` with `Content-Type: text/event-stream`, `X-Accel-Buffering: no`
- Sends typed events: `start`, `title`, `description`, `step`, `tips`, `related`, `done` (KB path) or `start`, `chunk`, `done` (Gemini path)

**Gemini Vision** (`extractBusinessCardInfo`):
- Sends base64 image to Gemini 2.0 Flash with OCR extraction prompt
- Returns structured JSON: `full_name`, `job_title`, `email`, `phone`, `website`, `linkedin`, etc.

**Knowledge Base management:**
- `app_knowledge` table: `category`, `key` (unique), `platform`, `locale`, `title`, `content` (JSON), `searchable_text`, `keywords` (JSON), `sample_questions` (JSON), `related_keys` (JSON), `priority`, `view_count`

Key file: `GeminiService.php`, `AiGuideController.php`, `AppKnowledge.php`

---

### 8. Company Profile

- One company per user; linked via `users.company_id`
- Logo upload with address (city/state/country hierarchy)
- `POST /api/company` upserts company + address in a transaction, then updates `users.company_id`
- Soft deletes

---

### 9. Location Data (Countries / States / Cities)

- 3-level hierarchy: `countries` → `states` (with `country_id` FK) → `cities` (with `state_id` FK)
- Lookup by code: `GET /api/countries/{code}/states`, `GET /api/states/{code}/cities`
- Used as cascading dropdowns in frontend forms (contact address, company address, business card address)
- Public endpoints (no auth required)

---

### 10. API Documentation (OpenAPI / Swagger)

- Full `@OA\` docblock annotations on all controllers and models
- L5-Swagger generates interactive Swagger UI at `/api/documentation`
- `@OA\Schema` annotations on `BusinessCard` and `Company` models
- All endpoints documented: parameters, request bodies, response schemas, security requirements

---

## My Role

I designed and built this backend **end-to-end, solo**, across all phases:

| Phase | What I did |
|-------|-----------|
| **Business Analysis** | Defined domain model, entities, and relationships (contacts, tags, reminders, business card, company, AI guide, notifications) based on product requirements |
| **System Design** | Designed the 3-tier auth system (public / token-only / token+verified), multi-contact reminder pivot, notification pruning strategy, image proxy pattern |
| **Database Design** | Designed all 21 migration files: table schemas, indexes (composite, full-text), foreign keys, soft delete strategy, pivot tables with extra columns (`is_primary`) |
| **Backend Development** | Implemented all 10 controllers (~60+ API endpoints), 11 models, GeminiService, Excel import/export, image processing pipeline, Artisan scheduler command, all middleware |
| **AI Integration** | Integrated Google Gemini 2.0 Flash for both conversational Q&A (standard + SSE streaming) and Gemini Vision OCR; designed knowledge-base-first query strategy |
| **Email & Auth** | Built full auth lifecycle: registration with email verification (signed URLs), 3-step password reset (email 6-digit code), magic link exchange, profile update |
| **DevOps** | Configured Hostinger MySQL remote database, Hostinger SMTP, CORS for multi-origin production setup, Render.com deployment, Laravel scheduler cron, Supervisor queue workers |
| **API Documentation** | Annotated all controllers and models with full OpenAPI 3.0 `@OA\` docblocks |

---

## Notable Implementations

### Image Proxy (`GET /api/img/{path}`)
All stored images (avatars, card photos, company logos) are served through a single proxy route instead of direct storage URLs. This gives CORS control, allows future migration to cloud storage without breaking frontend URLs, and keeps storage private. PHP GD is used on upload to resize and recompress images before saving (not on-the-fly).

### Full-Text + Hashtag Search on Contacts
`GET /api/contacts?q=John+#vip` is parsed server-side: hashtags are extracted from the query string, resolved to tag IDs, and merged with any explicit `tag_ids` parameter. The remaining text is applied as a MySQL `FULLTEXT` search across `name`, `company`, `email`, `phone`. This lets users do natural search like `John #client #vn` in one field.

### SSE Streaming for AI Guide
The AI guide supports real-time streaming answers via Server-Sent Events. Laravel's `response()->stream()` is used with `X-Accel-Buffering: no` to prevent Nginx buffering. When answering from the knowledge base, typed events (`title`, `description`, `step`, `tips`, `related`, `done`) are emitted individually so the frontend can progressively render structured content. When falling back to Gemini, raw `chunk` events are streamed as Gemini produces them.

### Knowledge-Base-First AI Strategy
Rather than hitting Gemini for every query (slow + costly), the system first searches the `app_knowledge` table using extracted keywords matched against `searchable_text`, `title`, JSON `sample_questions`, and JSON `keywords` columns. Gemini is only called if no KB match is found. KB results are cached for 1 hour per locale+platform combination.

### Multi-Contact Reminders with Pivot `is_primary`
The `contact_reminder` pivot table has an `is_primary` boolean column. When contacts are attached to a reminder, the first one is flagged `is_primary`. When that primary contact is detached, the next contact in the pivot is automatically promoted to `is_primary`. The frontend has a dedicated pivot view (`GET /api/reminders/pivot`) that returns a paginated JOIN of reminders × contacts to show who has what reminder.

### 3-Step Password Reset (Code-Based, Not Link-Based)
Instead of the standard Laravel password-reset link (which breaks on mobile or when opening in a different browser), a 6-digit numeric code is emailed and stored in Laravel Cache with a TTL. The user submits `POST /api/auth/password/verify` with `{email, code, password}`. On success, all existing Sanctum tokens for that user are revoked before the new password is set.

### Automatic Notification Pruning
`UserNotification::log()` creates a notification then immediately calls `pruneForUser()`, which deletes all but the 50 most-recent notifications for that user. This keeps the notification table bounded without a background job or scheduled cleanup.

### Circular Duplicate Detection
Contacts have a `duplicate_of_id` field. The update logic checks for circular references before saving (contact A cannot be marked as duplicate of B if B is already marked as duplicate of A or points back to A through a chain).

### Contactless Reminders
`reminder.contact_id` is nullable — reminders can exist without a primary contact, linked only via the `contact_reminder` pivot. This was added after initial design to support general task-style reminders.

---

## API Documentation

Interactive Swagger UI auto-generated by L5-Swagger from `@OA\` annotations:

```
GET /api/documentation
```

All 60+ endpoints are documented with request/response schemas, parameter descriptions, and authentication requirements.

---

## Database Schema

### Tables

| Table | Description | Key Columns |
|-------|-------------|-------------|
| `users` | User accounts | `email` (unique+index), `email_verified_at`, `company_id` FK, `business_card_id` FK, `address_id` FK |
| `contacts` | CRM contacts | `owner_user_id`, `name`, `email`, `phone`, `ocr_raw`, `duplicate_of_id`, `avatar`, `card_image_front`, `card_image_back`; FULLTEXT on (name, company, email, phone); softDeletes |
| `tags` | User-owned labels | `owner_user_id`, `name` |
| `contact_tag` | Contact↔Tag pivot | `contact_id`, `tag_id`; composite index `(tag_id, contact_id)` |
| `reminders` | Follow-up reminders | `contact_id` (nullable), `owner_user_id`, `due_at`, `status`, `channel`; indexes on `(owner_user_id, status, due_at)` |
| `contact_reminder` | Multi-contact pivot | `contact_id`, `reminder_id`, `is_primary` (bool) |
| `user_notifications` | Activity feed | `type`, `title`, `body`, `data` (JSON), `status`, `scheduled_at`, `read_at`; composite indexes on `(owner_user_id, status)` and `(owner_user_id, scheduled_at)` |
| `business_cards` | Digital cards | `slug` (unique), `user_id` (unique FK), `is_public`, `view_count`, social links, image columns; softDeletes |
| `companies` | Company profiles | `name`, `tax_code` (unique), `address_id` FK; softDeletes |
| `addresses` | Structured addresses | `address_detail`, `city_id`, `state_id`, `country_id` (all FK) |
| `countries` | Country reference | `code` (unique), `name` |
| `states` | State/Province reference | `country_id` FK, `code`, `name` |
| `cities` | City reference | `state_id` FK, `code`, `name` |
| `app_knowledge` | AI knowledge base | `key` (unique), `category`, `platform`, `locale`, `content` (JSON), `searchable_text`, `keywords` (JSON), `sample_questions` (JSON); composite index `(category, platform, locale, is_active)` |
| `personal_access_tokens` | Sanctum tokens | Standard Sanctum schema |

### Entity Relationships

```
users
  ├─ hasMany → contacts (owner_user_id)
  ├─ hasMany → tags (owner_user_id)
  ├─ hasMany → reminders (owner_user_id)
  ├─ hasMany → user_notifications (owner_user_id)
  ├─ hasOne  → business_cards
  └─ belongsTo → companies

contacts
  ├─ belongsToMany → tags (via contact_tag)
  ├─ belongsToMany → reminders (via contact_reminder, +is_primary)
  └─ belongsTo → addresses

reminders
  ├─ belongsTo → contacts (primary, nullable)
  └─ belongsToMany → contacts (via contact_reminder)

addresses
  ├─ belongsTo → cities
  ├─ belongsTo → states
  └─ belongsTo → countries
```

---

## Installation & Setup

### Requirements

- PHP 8.2+
- Composer
- MySQL 8.0+
- Node.js (for Vite assets, optional for API-only)

### Steps

```bash
git clone <repo-url>
cd bizz_connect

composer install

cp .env.example .env
php artisan key:generate

# Configure .env:
# DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD
# MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
# GEMINI_API_KEY
# APP_FRONTEND_URL (for email redirect links)

php artisan migrate --seed
php artisan storage:link
php artisan l5-swagger:generate

# Start all services concurrently:
composer dev
# Equivalent to:
# php artisan serve &
# php artisan queue:listen --tries=1 &
# php artisan pail --timeout=0 &
```

### Key Environment Variables

```env
APP_URL=http://127.0.0.1:8000
APP_FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=153.92.15.63
DB_DATABASE=u564264509_biz_connect

MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=no-reply@biz-connect.online

QUEUE_CONNECTION=database
CACHE_STORE=database

GEMINI_API_KEY=your_gemini_api_key
```

---

## Deployment

**Production stack:**
- Web server: Nginx + PHP-FPM (or Apache)
- PHP 8.2+, Composer
- MySQL 8 (remote at `153.92.15.63`)
- Queue: `php artisan queue:work` managed by Supervisor
- Scheduler: cron `* * * * * php artisan schedule:run`
- SSL: Certbot / Let's Encrypt

**Production build:**
```bash
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
php artisan migrate --force
php artisan storage:link
```

**CORS origins configured for:**
- `bizz-connect-web.onrender.com`
- `biz-connect.online` and `*.biz-connect.online`
- All `localhost:*` and `127.0.0.1:*` ports (dev)
