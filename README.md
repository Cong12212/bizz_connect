# Bizz Connect API

A comprehensive contact and reminder management system built with Laravel 11, featuring business card management, multi-contact reminders, and real-time notifications.

## 📋 Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [API Documentation](#api-documentation)
- [Key API Endpoints](#key-api-endpoints)
- [Advanced Features](#advanced-features)
- [Configuration](#configuration)
- [Deployment](#deployment)
- [Database Schema](#database-schema)
- [Security](#security)
- [Testing](#testing)
- [License](#license)

## ✨ Features

- **Contact Management**: Full CRUD operations with advanced filtering and tagging
- **Smart Reminders**: Multi-contact reminders with status tracking and notifications
- **Business Cards**: Digital business cards with public sharing capabilities
- **Tag System**: Flexible tagging with AND/OR search modes and hashtag support
- **Notifications**: Real-time activity tracking and upcoming reminder alerts
- **Data Import/Export**: Excel/CSV import and export with customizable templates
- **Location Management**: Hierarchical location data (Countries → States → Cities)
- **Company Profiles**: Company information management with address support

## 🛠 Tech Stack

- **Framework**: Laravel 11
- **Authentication**: Laravel Sanctum (Bearer Token)
- **Database**: MySQL 8.0+
- **API Documentation**: L5-Swagger (OpenAPI 3.0)
- **Excel Processing**: Maatwebsite/Laravel-Excel
- **Timezone**: Asia/Ho_Chi_Minh (configurable)

## 📦 Requirements

- PHP 8.2+
- MySQL 8.0+
- Composer
- Node.js & NPM (for assets)

## 🚀 Installation

### 1. Clone the repository
```bash
git clone https://github.com/yourusername/bizz-connect-api.git
cd bizz-connect-api
```

### 2. Install dependencies
```bash
composer install
npm install
```

### 3. Environment setup
```bash
cp .env.example .env
php artisan key:generate
```

Configure your `.env` file:
```env
APP_NAME="Bizz Connect"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-api-domain.com
FRONTEND_URL=https://your-frontend-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bizz_connect
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

### 4. Database setup
```bash
php artisan migrate --seed
php artisan storage:link
```

### 5. Generate API documentation
```bash
php artisan l5-swagger:generate
```

### 6. Set up scheduler (for upcoming notifications)

Add to your crontab:
```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Or run manually for testing:
```bash
php artisan notifications:generate-upcoming --minutes=10
```

### 7. Start the development server
```bash
php artisan serve
```

Visit: `http://localhost:8000/api/documentation`

## 📚 API Documentation

Access the interactive API documentation at:
```
https://your-api-domain.com/api/documentation
```

The root URL (`/`) automatically redirects to the API documentation.

## 🔑 Key API Endpoints

### Authentication
```http
POST   /api/auth/register                    # Register new user
POST   /api/auth/login                       # Login and get bearer token
POST   /api/auth/logout                      # Logout (revoke token)
GET    /api/auth/me                          # Get current user info
PATCH  /api/auth/me                          # Update current user
GET    /api/email/verify/{id}/{hash}         # Verify email
POST   /api/email/verification-notification  # Resend verification email
POST   /api/auth/password/request            # Request password reset code
POST   /api/auth/password/resend             # Resend password reset code
POST   /api/auth/password/verify             # Verify code and reset password
```

### Contacts
```http
GET    /api/contacts                         # List contacts with filters
POST   /api/contacts                         # Create contact
GET    /api/contacts/{id}                    # Get contact details
PUT    /api/contacts/{id}                    # Update contact
DELETE /api/contacts/{id}                    # Delete contact
GET    /api/contacts/export                  # Export contacts (Excel/CSV)
GET    /api/contacts/export-template         # Download import template
POST   /api/contacts/import                  # Import contacts
POST   /api/contacts/bulk-delete             # Bulk delete contacts
POST   /api/contacts/{id}/tags               # Attach tags to contact
DELETE /api/contacts/{id}/tags/{tagId}       # Detach tag from contact
GET    /api/contacts/{id}/reminders          # Get reminders for contact
```

### Tags
```http
GET    /api/tags                             # List tags
POST   /api/tags                             # Create tag
PUT    /api/tags/{id}                        # Update tag
DELETE /api/tags/{id}                        # Delete tag
```

### Reminders
```http
GET    /api/reminders                        # List reminders with filters
POST   /api/reminders                        # Create reminder
GET    /api/reminders/{id}                   # Get reminder details
PATCH  /api/reminders/{id}                   # Update reminder
DELETE /api/reminders/{id}                   # Delete reminder
POST   /api/reminders/{id}/done              # Mark as done
POST   /api/reminders/{id}/contacts          # Attach contacts to reminder
DELETE /api/reminders/{id}/contacts/{id}     # Detach contact from reminder
POST   /api/reminders/bulk-status            # Bulk update status
POST   /api/reminders/bulk-delete            # Bulk delete
GET    /api/reminders/pivot                  # Get reminder-contact relationships
```

### Notifications
```http
GET    /api/notifications                    # List notifications
POST   /api/notifications/{id}/read          # Mark as read
POST   /api/notifications/{id}/done          # Mark as done
POST   /api/notifications/bulk-read          # Bulk mark as read
DELETE /api/notifications/{id}               # Delete notification
```

### Business Cards
```http
GET    /api/business-card                    # Get current user's card
POST   /api/business-card                    # Create/update card
DELETE /api/business-card                    # Delete card
GET    /api/business-card/public/{slug}      # View public card (no auth)
POST   /api/business-card/connect/{slug}     # Connect with card owner
```

### Company
```http
GET    /api/company                          # Get current user's company
POST   /api/company                          # Create/update company
DELETE /api/company                          # Delete company
```

### Locations (Public)
```http
GET    /api/countries                        # List all countries
GET    /api/countries/{code}/states          # Get states by country code
GET    /api/states/{code}/cities             # Get cities by state code
```

## 🎯 Advanced Features

### Multi-Contact Reminders

Reminders can be associated with multiple contacts via the `contact_reminder` pivot table:
```json
POST /api/reminders
{
  "title": "Quarterly meeting",
  "due_at": "2025-12-01T10:00:00Z",
  "contact_ids": [1, 2, 3],
  "status": "pending",
  "channel": "app",
  "note": "Discuss Q4 goals"
}
```

### Smart Search with Hashtags

Search contacts using hashtags with AND/OR modes:
```http
GET /api/contacts?q=#vip #client John
```

Features:
- Multiple hashtags automatically use AND mode
- Combine text search with tag filtering
- Support for `tag_mode=any` or `tag_mode=all`
- Use `without_tag` to exclude contacts with specific tag

### Contact Filters

Advanced filtering options:
```http
GET /api/contacts?q=John&tag_ids=1,2&tag_mode=all&without_tag=5&exclude_ids=10,20&sort=-name&per_page=50
```

**Query Parameters:**

| Parameter | Description | Example |
|-----------|-------------|---------|
| `q` | Text search (name, email, phone, company) + hashtags | `John #vip` |
| `tag_ids` | Filter by tag IDs (comma-separated) | `1,2,3` |
| `tags` | Filter by tag names (comma-separated) | `vip,client` |
| `tag_mode` | `any` (OR) or `all` (AND) | `all` |
| `without_tag` | Exclude contacts with tag ID or name | `5` or `archived` |
| `with_reminder` | Filter contacts with reminders | `true` |
| `without_reminder` | Filter contacts without reminders | `true` |
| `status` | Reminder status filter | `pending` |
| `after` | Reminders due after date | `2025-01-01` |
| `before` | Reminders due before date | `2025-12-31` |
| `exclude_ids` | Exclude contact IDs | `10,20,30` |
| `sort` | Sort by field | `name`, `-name`, `id`, `-id` |
| `per_page` | Items per page (max 100) | `50` |

### Reminder Filters
```http
GET /api/reminders?contact_id=1&status=pending&before=2025-12-31&overdue=true&with_contacts=true
```

**Query Parameters:**

| Parameter | Description | Example |
|-----------|-------------|---------|
| `contact_id` | Filter by contact ID | `1` |
| `status` | Filter by status | `pending`, `done`, `skipped`, `cancelled` |
| `before` | Due before date | `2025-12-31` |
| `after` | Due after date | `2025-01-01` |
| `overdue` | Show only overdue reminders | `true` |
| `with_contacts` | Include contacts relation | `true` |
| `per_page` | Items per page (max 100) | `20` |

### Notification Filters
```http
GET /api/notifications?scope=unread&limit=20
```

**Query Parameters:**

| Parameter | Description | Example |
|-----------|-------------|---------|
| `scope` | Filter scope | `all`, `unread`, `upcoming`, `past` |
| `limit` | Number of items (max 20) | `20` |

### Automatic Notifications

The system automatically generates notifications for:

- ✅ Contact creation
- ✅ Reminder creation
- ⏰ Upcoming reminders (10 minutes before due)
- ✔️ Reminder completion

Notifications are automatically pruned to keep max 50 per user.

### Data Import/Export

**Export contacts with filters:**
```http
GET /api/contacts/export?format=xlsx&ids=1,2,3&q=#vip&tag_mode=all
```

**Parameters:**
- `format`: `xlsx` or `csv` (default: `xlsx`)
- All contact filter parameters are supported

**Import contacts from Excel/CSV:**
```http
POST /api/contacts/import
Content-Type: multipart/form-data

file: contacts.xlsx
match_by: email  # Options: id, email, phone
```

**Response:**
```json
{
  "status": "ok",
  "summary": {
    "created": 10,
    "updated": 5,
    "skipped": 2,
    "errors": []
  }
}
```

**Download import template:**
```http
GET /api/contacts/export-template?format=xlsx
```

**Template columns:**
- Name * (required)
- Company
- Job Title
- Email
- Phone
- Address Detail
- City (code)
- State (code)
- Country (code)
- Notes
- LinkedIn URL
- Website URL
- Tags (comma-separated)
- Source

## ⚙️ Configuration

### CORS Configuration

Update `config/cors.php` to allow your frontend domain:
```php
'allowed_origins' => [
    'https://your-frontend-domain.com',
],
'allowed_origins_patterns' => [
    '/^http:\/\/localhost:\d+$/',
    '/^http:\/\/127\.0\.0\.1:\d+$/',
],
```

### Timezone Configuration

Edit `config/app.php`:
```php
'timezone' => 'Asia/Ho_Chi_Minh',
```

### Email Configuration

For production, configure SMTP in `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

**Gmail Setup:**
1. Enable 2-factor authentication
2. Generate App Password: https://myaccount.google.com/apppasswords
3. Use the generated password in `MAIL_PASSWORD`

### Scheduler Configuration

The scheduler runs every minute and executes:
```php
// Generates upcoming notifications (10 minutes before due)
$schedule->command('notifications:generate-upcoming --minutes=10')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
```

## 🚢 Deployment

### Using Docker

A Dockerfile is included for containerized deployment:
```bash
docker build -t bizz-connect-api .
docker run -p 8000:80 bizz-connect-api
```

### Manual Deployment (Production)

1. **Clone and install dependencies:**
```bash
git clone https://github.com/yourusername/bizz-connect-api.git
cd bizz-connect-api
composer install --optimize-autoloader --no-dev
npm install && npm run build
```

2. **Configure environment:**
```bash
cp .env.example .env
php artisan key:generate
# Edit .env with production settings
```

3. **Set up database:**
```bash
php artisan migrate --force
php artisan storage:link
```

4. **Set proper permissions:**
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

5. **Optimize for production:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan l5-swagger:generate
```

6. **Configure web server (Nginx example):**
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/bizz-connect-api/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

7. **Set up SSL (using Certbot):**
```bash
sudo certbot --nginx -d your-domain.com
```

8. **Set up supervisor for queue worker:**
```bash
sudo nano /etc/supervisor/conf.d/bizz-connect-worker.conf
```
```ini
[program:bizz-connect-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/bizz-connect-api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/bizz-connect-api/storage/logs/worker.log
stopwaitsecs=3600
```
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start bizz-connect-worker:*
```

9. **Set up cron for scheduler:**
```bash
crontab -e
```

Add:
```
* * * * * cd /path/to/bizz-connect-api && php artisan schedule:run >> /dev/null 2>&1
```

### Deploy to Render.com

1. Create a new Web Service
2. Connect your GitHub repository
3. Set build command: `composer install --optimize-autoloader --no-dev`
4. Set start command: `php artisan serve --host=0.0.0.0 --port=$PORT`
5. Add environment variables from `.env`
6. Deploy!

## 🗄️ Database Schema

### Key Tables

| Table | Description |
|-------|-------------|
| `users` | User accounts with email verification |
| `contacts` | Contact information with address support |
| `tags` | User-specific tags |
| `contact_tag` | Many-to-many pivot for contacts and tags |
| `reminders` | Reminder records with soft deletes |
| `contact_reminder` | Many-to-many pivot for multi-contact reminders |
| `user_notifications` | Activity feed and upcoming alerts |
| `business_cards` | Digital business cards with public sharing |
| `companies` | Company profiles |
| `addresses` | Address records |
| `countries` | Country reference data |
| `states` | State/province reference data |
| `cities` | City reference data |

### Entity Relationships
```
users
  ├─ hasMany contacts
  ├─ hasMany tags
  ├─ hasMany reminders
  ├─ hasMany user_notifications
  ├─ hasOne business_card
  └─ belongsTo company

contacts
  ├─ belongsToMany tags (via contact_tag)
  ├─ belongsToMany reminders (via contact_reminder)
  └─ belongsTo address

reminders
  ├─ belongsTo contact (primary)
  └─ belongsToMany contacts (via contact_reminder)

addresses
  ├─ belongsTo city
  ├─ belongsTo state
  └─ belongsTo country
```

## 🔒 Security

### Authentication Flow

1. **Register**: Email verification required
2. **Login**: Returns bearer token
3. **API Requests**: Include token in header: `Authorization: Bearer {token}`
4. **Logout**: Revokes current token

### Security Features

- ✅ Email verification required
- ✅ Bearer token authentication (Sanctum)
- ✅ Password reset with 6-digit verification code (10 min expiry)
- ✅ Rate limiting on sensitive endpoints
- ✅ CSRF protection for web routes
- ✅ Input validation and sanitization
- ✅ SQL injection prevention via Eloquent ORM
- ✅ Signed URLs for email verification
- ✅ One-time use magic codes
- ✅ Password hashing with bcrypt
- ✅ HTTPS enforced in production

### Best Practices

1. **Never expose `.env` file**
2. **Use strong `APP_KEY`** (generated automatically)
3. **Enable HTTPS** in production
4. **Set `APP_DEBUG=false`** in production
5. **Configure CORS** properly
6. **Rotate tokens** regularly
7. **Monitor logs** for suspicious activity

## 🧪 Testing

### Test Email Configuration
```bash
curl https://your-api-domain.com/_test-mail
```

### Manual API Testing
```bash
# Register
curl -X POST https://your-api-domain.com/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"John Doe","email":"john@example.com","password":"password123"}'

# Login
curl -X POST https://your-api-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"john@example.com","password":"password123"}'

# Get Profile (replace {token} with your bearer token)
curl -X GET https://your-api-domain.com/api/auth/me \
  -H "Authorization: Bearer {token}"

# Create Contact
curl -X POST https://your-api-domain.com/api/contacts \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"name":"Jane Smith","email":"jane@example.com","phone":"+1234567890"}'
```

## 📊 API Response Format

### Success Response
```json
{
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "created_at": "2025-01-15T10:30:00Z",
    "updated_at": "2025-01-15T10:30:00Z"
  }
}
```

### Paginated Response
```json
{
  "data": [
    {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 100,
  "last_page": 5,
  "from": 1,
  "to": 20
}
```

### Error Response
```json
{
  "message": "Validation error",
  "errors": {
    "email": ["The email field is required."],
    "name": ["The name must not be greater than 255 characters."]
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (successful deletion) |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Internal Server Error |

## 🏗️ Project Structure
```
bizz-connect-api/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── GenerateUpcomingNotifications.php
│   ├── Exports/
│   │   ├── ContactsExport.php
│   │   └── ContactsTemplateExport.php
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── ContactController.php
│   │   │   ├── ReminderController.php
│   │   │   ├── TagController.php
│   │   │   ├── NotificationController.php
│   │   │   ├── BusinessCardController.php
│   │   │   ├── CompanyController.php
│   │   │   └── LocationController.php
│   │   └── Middleware/
│   ├── Imports/
│   │   └── ContactsImport.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Contact.php
│   │   ├── Tag.php
│   │   ├── Reminder.php
│   │   ├── UserNotification.php
│   │   ├── BusinessCard.php
│   │   ├── Company.php
│   │   ├── Address.php
│   │   ├── Country.php
│   │   ├── State.php
│   │   └── City.php
│   └── Notifications/
│       └── PasswordResetCode.php
├── config/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   ├── api.php
│   └── web.php
├── storage/
├── .env.example
├── composer.json
├── Dockerfile
└── README.md
```

## 🤝 Contributing

Contributions are welcome! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is proprietary software. All rights reserved.

## 👥 Authors

- Development Team - Initial work

## 🙏 Acknowledgments

- Laravel community for the excellent framework
- Contributors to the Laravel ecosystem packages used in this project
- All developers who have contributed to open-source packages

## 📞 Support

For issues and feature requests, please use the [GitHub issue tracker](https://github.com/yourusername/bizz-connect-api/issues).

---

**Made with ❤️ using Laravel**
