# Technology Stack — Perfect Nails Wellness & Aesthetics (NexFlio)

## Backend

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | Laravel | 11.x | MVC web framework |
| Language | PHP | 8.2+ | Server-side logic |
| ORM | Eloquent | Built-in | Database abstraction |
| Authentication | Laravel Sanctum | Built-in | SPA + API token auth |
| Authorization | spatie/laravel-permission | Latest | Role-based access control |
| Testing | PHPUnit | Built-in | Unit + feature tests |

### Key Composer Dependencies

| Package | Purpose |
|---------|---------|
| `laravel/framework` | Core framework |
| `laravel/sanctum` | API token authentication |
| `spatie/laravel-permission` | Role & permission management |
| `phpunit/phpunit` | Test framework |

## Frontend — Web Application

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Templating | Blade | Built-in | Server-side rendering |
| CSS Framework | Bootstrap | 5.3 | Responsive UI components |
| Design System | Custom `nx-*` CSS | Custom | NexFlio brand identity |
| Icons | Bootstrap Icons | Latest | UI iconography |
| Charts | Chart.js | Latest | Dashboard analytics |
| Calendar | Custom JS | Vanilla | Appointment calendar view |

## Frontend — Mobile Application

| Component | Technology | Version | Purpose |
|-----------|-----------|---------|---------|
| Framework | Flutter | Stable | Cross-platform mobile app |
| Language | Dart | Latest | Mobile application logic |
| State Management | (Flutter built-in) | — | UI state handling |
| HTTP Client | (Flutter built-in) | — | REST API communication |

## Database

| Component | Technology | Purpose |
|-----------|-----------|---------|
| RDBMS | MySQL 8.x | Primary data store |
| Cache Store | Database (dev) / Redis (prod) | Query caching, session |
| Queue Driver | Database (dev) / Redis (prod) | Job processing |
| Session Driver | Database | User session persistence |

### Database Configuration

- **Charset:** `utf8mb4` (full Unicode support)
- **Collation:** `utf8mb4_unicode_ci`
- **Strict Mode:** Enabled
- **Connection:** TCP/IP (`127.0.0.1:3306`)
- **Database:** `nexflio`
- **Root Password:** Empty (local development only)

## External Services

| Service | Protocol | Purpose | Configuration |
|---------|----------|---------|---------------|
| Firebase Cloud Messaging | HTTPS (HTTP v1) | Push notifications to mobile devices | `FIREBASE_CREDENTIALS` JSON file |
| Google Gmail SMTP | SMTP (SSL:465) | Transactional email delivery | `MAIL_USERNAME`, `MAIL_PASSWORD` |
| Google Gemini API | HTTPS REST | AI-generated business insights | `GEMINI_API_KEY` |
| Dialogflow | Webhook | Chatbot integration for booking | `DIALOGFLOW_WEBHOOK_TOKEN` |
| OpenSSL | Local | JWT RS256 signing for FCM auth | PHP extension |

## DevOps & Tooling

| Tool | Purpose |
|------|---------|
| Laragon | Local development environment (Windows) |
| Git | Version control |
| Composer | PHP dependency management |
| PHPUnit | Automated testing |
| Artisan CLI | Laravel command-line interface |

## Server Requirements

| Requirement | Minimum | Recommended |
|-------------|---------|-------------|
| PHP Version | 8.2+ | 8.3+ |
| MySQL Version | 8.0+ | 8.0+ |
| Memory Limit | 256M | 512M |
| Max Execution Time | 60s | 120s |
| Composer | 2.x | 2.x |
| Node.js | 18+ (if building assets) | 20+ |

### PHP Extensions Required

- `php_openssl` (FCM JWT signing)
- `php_mbstring` (Unicode handling)
- `php_xml` (XML processing)
- `php_curl` (HTTP client)
- `php_gd` (image processing)
- `php_mysql` (database driver)
- `php_redis` (production cache/queue)

## Testing Stack

| Component | Technology | Configuration |
|-----------|-----------|---------------|
| Framework | PHPUnit | `phpunit.xml` |
| Test DB | SQLite (in-memory) | `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` |
| Test Cache | Array | `CACHE_STORE=array` |
| Test Queue | Sync | `QUEUE_CONNECTION=sync` |
| Test Session | Array | `SESSION_DRIVER=array` |
| Test Mail | Array | `MAIL_MAILER=array` |
| BCrypt Rounds | 4 | Fast hashing for test speed |
| Pulse/Telescope | Disabled | `PULSE_ENABLED=false`, `TELESCOPE_ENABLED=false` |

### Test Summary

- **Total Tests:** 128
- **Total Assertions:** 498
- **Coverage:** Models, Controllers, Services, Requests, Middleware, Enums

## Environment Variables

### Required

| Variable | Example | Purpose |
|----------|---------|---------|
| `APP_KEY` | `base64:kXaG...` | Encryption key |
| `APP_DEBUG` | `true` (dev) / `false` (prod) | Error display |
| `APP_URL` | `http://localhost:8000` | Application URL |
| `DB_HOST` | `127.0.0.1` | Database host |
| `DB_PORT` | `3306` | Database port |
| `DB_DATABASE` | `nexflio` | Database name |
| `DB_USERNAME` | `root` | Database user |
| `DB_PASSWORD` | (empty in dev) | Database password |

### Optional (External Services)

| Variable | Purpose |
|----------|---------|
| `MAIL_MAILER` | `smtp` for production |
| `MAIL_HOST` | `smtp.gmail.com` |
| `MAIL_PORT` | `465` |
| `MAIL_USERNAME` | Gmail address |
| `MAIL_PASSWORD` | Gmail app password |
| `MAIL_FROM_ADDRESS` | Sender address |
| `GEMINI_API_KEY` | Google Gemini API key |
| `FIREBASE_CREDENTIALS` | Path to Firebase service account JSON |
| `DIALOGFLOW_WEBHOOK_TOKEN` | Dialogflow authentication token |
| `QUEUE_CONNECTION` | `database` or `redis` |
| `CACHE_STORE` | `database` or `redis` |
| `SESSION_DRIVER` | `database` or `redis` |

## Security Configuration

| Setting | Value | Notes |
|---------|-------|-------|
| `APP_DEBUG` | `true` (dev) | Must be `false` in production |
| `BCRYPT_ROUNDS` | 12 (prod) / 4 (test) | Password hashing cost |
| Cache Serialization | `false` | Prevents gadget chain attacks |
| HTTPS | Required in production | Enforced via middleware |
| CSRF | Enabled | Laravel default |
| XSS Protection | Blade auto-escaping | Built-in |
| SQL Injection | Eloquent parameterized queries | Built-in |
