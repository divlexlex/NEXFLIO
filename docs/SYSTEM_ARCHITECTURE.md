# System Architecture — Perfect Nails Wellness & Aesthetics (NexFlio)

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                             │
│  ┌──────────────────┐  ┌──────────────────┐  ┌───────────────┐ │
│  │  Laravel Website  │  │  Flutter Mobile   │  │  Dialogflow   │ │
│  │  (Blade + BS5)   │  │  (Dart)           │  │  Chatbot      │ │
│  └────────┬─────────┘  └────────┬─────────┘  └───────┬───────┘ │
└───────────┼──────────────────────┼────────────────────┼─────────┘
            │                      │                    │
┌───────────┼──────────────────────┼────────────────────┼─────────┐
│           ▼                      ▼                    ▼         │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │                   Laravel 11 Backend                     │    │
│  │                                                          │    │
│  │  ┌─────────┐  ┌─────────────┐  ┌──────────────────┐   │    │
│  │  │ Web UI  │  │ REST API    │  │ Console/Scheduler │   │    │
│  │  │ (Blade) │  │ (Sanctum)   │  │ (Artisan)         │   │    │
│  │  └────┬────┘  └──────┬──────┘  └────────┬─────────┘   │    │
│  │       │               │                  │              │    │
│  │  ┌────▼───────────────▼──────────────────▼──────────┐   │    │
│  │  │              Application Layer                    │   │    │
│  │  │  Controllers → Services → Models → Eloquent       │   │    │
│  │  └──────────────────────┬───────────────────────────┘   │    │
│  └─────────────────────────┼───────────────────────────────┘    │
│                            │                                    │
│  ┌─────────────────────────┼───────────────────────────────┐    │
│  │                    DATA LAYER                            │    │
│  │  ┌─────────┐  ┌─────────┐  ┌──────────┐  ┌─────────┐  │    │
│  │  │ MySQL   │  │ Redis   │  │ Storage  │  │ Queue   │  │    │
│  │  │ (46 tbl)│  │ (Cache) │  │ (Images) │  │ (jobs)  │  │    │
│  │  └─────────┘  └─────────┘  └──────────┘  └─────────┘  │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
            │
┌───────────┼─────────────────────────────────────────────────────┐
│           ▼              EXTERNAL SERVICES                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐      │
│  │ Firebase     │  │ Google       │  │ Google Gemini    │      │
│  │ Cloud        │  │ Gmail SMTP   │  │ AI API           │      │
│  │ Messaging    │  │              │  │ (Insights)       │      │
│  └──────────────┘  └──────────────┘  └──────────────────┘      │
└─────────────────────────────────────────────────────────────────┘
```

## Layered Architecture

The application follows a strict layered architecture pattern:

1. **Presentation Layer** — Blade templates (web) and REST API (mobile)
2. **Routing Layer** — Route definitions with middleware guards
3. **Controller Layer** — HTTP request handling, validation, response formatting
4. **Service Layer** — Business logic encapsulation (AppointmentService, InventoryService, etc.)
5. **Model Layer** — Eloquent ORM, relationships, scopes, accessors
6. **Data Layer** — Migrations, seeders, database schema

### Request Lifecycle

```
HTTP Request
  → Kernel (middleware stack)
    → Route matching + parameter binding
      → Middleware: auth, role, verified
        → Controller method
          → Form Request validation
            → Service method (business logic)
              → Model / Eloquent
                → Database transaction
                  → Response (JSON / Redirect / View)
```

### Service Layer Pattern

The `AppointmentService` is the central state machine orchestrator. Every appointment status change MUST flow through `AppointmentService::transition()`:

```
Controller
  → $this->appointmentService->transition($appointment, $target, $actor, $options)
    → Validates transition legality (AppointmentStatus::canTransitionTo)
    → Enforces authorization (manager-only for verification, assigned-staff for updates)
    → Updates payment status (verified/rejected)
    → Records commission (idempotent firstOrCreate)
    → Consumes inventory (FIFO batch deduction)
    → Dispatches notifications (in-app + email + push)
    → Returns fresh model with eager-loaded relations
```

### Inventory Service Pattern

`InventoryService` is the sole gateway for stock changes:

- `receive()` — incoming stock: creates batch + IN movement + increments quantity
- `consume()` — outgoing stock: FIFO batch deduction + OUT movement + decrements quantity
- All operations use `lockForUpdate()` for concurrency safety
- Enforces whole-unit consumption (no fractional bottles)

## Application Boundaries

### Web Application (Blade)
- Guest routes: Home page, services catalog, about page
- Auth routes: Login, registration, email verification (6-digit OTP)
- Client portal: Booking (branch/home), appointments, profile, notifications, messages, addresses
- Staff portal: Dashboard (today's appointments), leave requests, availability, time tracking
- Admin panel: Full CRUD for services, employees, schedules, appointments, payments, inventory, reports, analytics, settings, activity log

### REST API (Sanctum)
- Mobile app: Login, OTP, registration, profile, booking, appointments, addresses, notifications, messages, staff dashboard
- Guest: Services list, nearby branches
- Dialogflow webhook: Chatbot integration for services, availability, booking initiation

### Console / Scheduler
- `appointments:remind` — daily at 08:00, sends reminders for tomorrow's booked appointments
- `SendAppointmentReminders` job handles notification dispatch

## File Structure Overview

```
app/
├── Console/Commands/          # Artisan commands (1)
├── Enums/                     # Backed string enums (7)
│   ├── AppointmentStatus.php  # State machine definition
│   ├── Gender.php
│   ├── PaymentStatus.php
│   ├── Position.php           # Staff role specialization
│   └── ServiceLocationType.php
├── Http/
│   ├── Controllers/Web/       # Blade controllers (15)
│   ├── Controllers/Api/       # REST API controllers (7)
│   ├── Middleware/             # Custom middleware (4)
│   └── Requests/              # Form request validators (8)
├── Mail/                      # Mailable classes (5)
├── Models/                    # Eloquent models (28)
├── Services/                  # Business logic services (6)
├── Support/                   # Read-only query builders (1)
└── Traits/                    # Reusable model traits (2)

resources/views/               # Blade templates
├── admin/                     # Admin panel views (22)
├── auth/                      # Authentication views (5)
├── staff/                     # Staff portal views (4)
├── account/                   # Client portal views (12)
├── guest/                     # Public pages (5)
├── layouts/                   # Shared layouts (4)
└── components/                # Reusable Blade components (6)

routes/
├── web.php                    # All web routes (~220 lines)
└── api.php                    # REST API routes (~100 lines)
```

## State Management

### Appointment Lifecycle (State Machine)

```
Unverified → Booked → InService → Completed
    ↓          ↓         ↓
Cancelled  Cancelled  Cancelled
              ↓
            NoShow
```

- Terminal states: Completed, Cancelled, NoShow (no further transitions)
- Transition validation: `AppointmentStatus::canTransitionTo()`
- Authorization: Manager-only for verification; assigned-staff or manager for status updates

### Payment Lifecycle

```
Pending → Verified → (linked to Appointment: Booked)
Pending → Rejected → (linked to Appointment: Cancelled)
```

### Inventory Lifecycle

```
Received (IN) → Consumed (OUT) / Pulled Out (PULL_OUT)
```

- FIFO consumption: oldest batch depleted first
- Row-level locking for concurrency safety
- Immutable ledger: `stock_movements` table tracks every change

## Cross-Cutting Concerns

### Audit Logging
- `Auditable` trait on models creates immutable `audit_logs` rows
- Covers create, update, delete, restore events
- Filters out hidden attributes (passwords, tokens)
- Actor tracking via `Auth::id()`

### Authentication
- Laravel Sanctum for API token authentication
- Session-based authentication for web routes
- Role-based access control via `spatie/laravel-permission`

### Notification Dispatch
- Unified `NotificationService::notify()` for all channels
- In-app notification (database)
- Email (queued via Laravel Mail)
- Push (FCM HTTP v1 via `SendPushNotification` job)

### File Storage
- Laravel Storage `public` disk for uploaded images
- Service images, payment proofs, profile photos, schedule images
- `HasImage` trait provides `image_url` accessor and `deleteImageFile()` cleanup

## Technology Stack Summary

| Layer | Technology |
|-------|-----------|
| Backend Framework | Laravel 11 |
| Language | PHP 8.2+ |
| Database | MySQL 8 (Laragon dev) |
| Cache | Database (Redis in production) |
| Queue | Database (Redis in production) |
| Session | Database |
| API Auth | Laravel Sanctum |
| Role Management | spatie/laravel-permission |
| Frontend (Web) | Blade + Bootstrap 5.3 |
| CSS Design System | Custom `nx-*` classes |
| Frontend (Mobile) | Flutter (Dart) |
| Push Notifications | Firebase Cloud Messaging (HTTP v1) |
| AI Insights | Google Gemini API |
| Chatbot | Dialogflow (webhook integration) |
| Email | Gmail SMTP |
| Testing | PHPUnit (128 tests, 498 assertions) |
| Dev Environment | Laragon (Windows) |
