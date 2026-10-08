# Database Architecture — Perfect Nails Wellness & Aesthetics (NexFlio)

## Overview

- **RDBMS:** MySQL 8.x
- **Charset:** `utf8mb4` (full Unicode support)
- **Collation:** `utf8mb4_unicode_ci`
- **Total Tables:** 46 (from migrations)
- **ORM:** Laravel Eloquent with strict mode enabled

## Entity-Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                          ENTITY-RELATIONSHIP DIAGRAM                            │
└─────────────────────────────────────────────────────────────────────────────────┘

┌──────────────┐        ┌──────────────┐        ┌──────────────┐
│    users      │        │   roles       │        │ permissions  │
│──────────────│   M:N  │──────────────│   M:N  │──────────────│
│ id (PK)      │◄──────►│ id (PK)      │◄──────►│ id (PK)      │
│ first_name   │        │ name         │        │ name         │
│ last_name    │        │ guard_name   │        │ guard_name   │
│ email        │        └──────────────┘        └──────────────┘
│ username     │               ▲
│ password     │               │
│ role_id (FK) │───────────────┘
│ gender       │
│ contact_num  │
│ position     │
│ base_pay     │
│ commission   │
│ image_path   │
│ email_ver_at │
│ deleted_at   │
│ created_at   │
│ updated_at   │
└──────┬───────┘
       │
       ├──────────────────────────────────────────────────────────────┐
       │                                                              │
       ▼                                                              ▼
┌──────────────┐                                              ┌──────────────┐
│ appointments  │                                              │   services    │
│──────────────│                                              │──────────────│
│ id (PK)      │                                              │ id (PK)      │
│ user_id (FK) │──── clients                                  │ name         │
│ personnel_id │──── staff (nullable for "No Preference")      │ category     │
│ service_id   │──── services                                 │ price        │
│ appt_date    │                                              │ duration     │
│ start_time   │                                              │ status       │
│ end_time     │                                              │ description  │
│ status       │                                              │ image_path   │
│ notes        │                                              │ location_type│
│ payment_id   │──── payments                                 │ avail_from   │
│ created_at   │                                              │ avail_until  │
│ updated_at   │                                              │ created_at   │
└──────┬───────┘                                              │ updated_at   │
       │                                                      │ deleted_at   │
       │                                                      └──────────────┘
       ▼
┌──────────────┐        ┌──────────────┐
│   payments    │        │  commissions  │
│──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │
│ appt_id (FK) │◄───────│ appt_id (FK) │
│ amount       │        │ user_id (FK) │
│ method       │        │ amount       │
│ proof_path   │        │ created_at   │
│ status       │        └──────────────┘
│ verified_by  │
│ verified_at  │
│ reject_reason│
│ created_at   │
└──────────────┘

┌──────────────┐        ┌──────────────┐        ┌──────────────┐
│ business_    │        │ staff_       │        │ blocked_     │
│ hours        │        │ schedules    │        │ slots        │
│──────────────│        │──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │        │ id (PK)      │
│ day_of_week  │        │ user_id (FK) │        │ staff_id(FK) │
│ open_time    │        │ day_of_week  │        │ blocked_date │
│ close_time   │        │ start_time   │        │ start_time   │
│ is_closed    │        │ end_time     │        │ end_time     │
│ created_at   │        │ is_day_off   │        │ reason       │
│ updated_at   │        │ eff_from     │        │ created_by   │
└──────────────┘        │ eff_until    │        │ created_at   │
                        │ created_at   │        └──────────────┘
                        │ updated_at   │
                        └──────────────┘

┌──────────────┐        ┌──────────────┐        ┌──────────────┐
│  inventories  │        │ inventory_   │        │ stock_       │
│               │        │ batches      │        │ movements    │
│──────────────│        │──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │        │ id (PK)      │
│ name         │        │ inv_id (FK)  │        │ inv_id (FK)  │
│ category     │        │ quantity     │        │ batch_id(FK) │
│ quantity     │◄───────│ qty_remain   │───────►│ type         │
│ unit         │        │ unit_cost    │        │ quantity     │
│ reorder_lvl  │        │ received_at  │        │ ref_type     │
│ status       │        │ created_at   │        │ ref_id       │
│ created_at   │        └──────────────┘        │ notes        │
│ updated_at   │                                │ user_id (FK) │
└──────────────┘                                │ created_at   │
                                                └──────────────┘

┌──────────────┐        ┌──────────────┐
│ notifications│        │ device_tokens│
│──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │
│ user_id (FK) │        │ user_id (FK) │
│ title        │        │ token        │
│ body         │        │ platform     │
│ read_at      │        │ last_used_at │
│ created_at   │        │ created_at   │
└──────────────┘        └──────────────┘

┌──────────────┐        ┌──────────────┐
│   messages    │        │ message_     │
│               │        │ threads      │
│──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │
│ thread_id(FK)│◄───────│ user_a (FK)  │
│ sender_id(FK)│        │ user_b (FK)  │
│ body         │        │ last_msg_at  │
│ read_at      │        │ created_at   │
│ deleted_at   │        │ updated_at   │
│ created_at   │        │ deleted_at   │
└──────────────┘        └──────────────┘

┌──────────────┐        ┌──────────────┐
│  audit_logs   │        │ verification │
│               │        │ _codes       │
│──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │
│ user_id (FK) │        │ user_id (FK) │
│ event        │        │ code_hash    │
│ auditable_ty │        │ channel      │
│ auditable_id │        │ expires_at   │
│ old_values   │        │ attempts     │
│ new_values   │        │ consumed_at  │
│ created_at   │        │ created_at   │
└──────────────┘        └──────────────┘

┌──────────────┐        ┌──────────────┐
│leave_requests│        │ appointment_ │
│              │        │ change_req   │
│──────────────│        │──────────────│
│ id (PK)      │        │ id (PK)      │
│ user_id (FK) │        │ appt_id (FK) │
│ start_date   │        │ user_id (FK) │
│ end_date     │        │ type         │
│ type         │        │ reason       │
│ reason       │        │ req_date     │
│ status       │        │ req_time     │
│ reviewed_by  │        │ req_person   │
│ reviewed_at  │        │ status       │
│ review_notes │        │ reviewed_by  │
│ created_at   │        │ reviewed_at  │
│ updated_at   │        │ review_notes │
└──────────────┘        └──────────────┘

┌──────────────┐
│ staff_       │
│ profiles     │
│──────────────│
│ id (PK)      │
│ user_id (FK) │
│ specializatn │
│ bio          │
│ years_exp    │
│ languages    │
│ certificatns │
│ is_featured  │
│ created_at   │
│ updated_at   │
└──────────────┘
```

## Table Descriptions

### Core Business Tables

| Table | Records | Purpose | Key Columns |
|-------|---------|---------|-------------|
| `users` | ~30 | All system users | `role_id`, `position`, `commission_rate`, `deleted_at` |
| `services` | ~20 | Service catalog | `category`, `price`, `duration_minutes`, `service_location_type` |
| `appointments` | ~100 | Booking records | `user_id`, `personnel_id` (nullable), `status` |
| `payments` | ~100 | Payment records | `amount`, `method`, `proof_path`, `status` |
| `commissions` | ~50 | Staff earnings | `user_id`, `appointment_id`, `amount` |

### Availability Tables

| Table | Purpose | Indexes |
|-------|---------|---------|
| `business_hours` | Branch hours per day (10AM-9PM) | `day_of_week` (unique) |
| `staff_schedules` | Individual staff hours per day | `user_id`, `day_of_week` |
| `blocked_slots` | Specific date/time blocks | `staff_user_id`, `blocked_date` |
| `service_hours` | Per-service time restrictions | `service_id`, `day_of_week` |

### Inventory Tables

| Table | Purpose | Key Feature |
|-------|---------|-------------|
| `inventories` | Product stock records | Running `quantity` cache |
| `inventory_batches` | FIFO batch tracking | `quantity_remaining` per batch |
| `stock_movements` | Immutable ledger | Polymorphic `reference_type/id` |

### Communication Tables

| Table | Purpose | Key Feature |
|-------|---------|-------------|
| `notifications` | In-app notifications | `read_at` for read status |
| `device_tokens` | FCM token registry | Auto-cleanup of dead tokens |
| `messages` | Direct messages | Soft deletes |
| `message_threads` | Conversation containers | Two-user threads |

### System Tables

| Table | Purpose | Key Feature |
|-------|---------|-------------|
| `audit_logs` | Immutable audit trail | JSON `old_values`/`new_values` |
| `verification_codes` | Email OTP records | bcrypt-hashed codes |
| `leave_requests` | Staff leave applications | Overlap detection |
| `appointment_change_requests` | Reschedule/cancel requests | Status workflow |

### Laravel Framework Tables

| Table | Purpose |
|-------|---------|
| `roles` | Spatie role definitions |
| `permissions` | Spatie permission definitions |
| `model_has_roles` | User-role pivot |
| `model_has_permissions` | User-permission pivot |
| `role_has_permissions` | Role-permission pivot |
| `personal_access_tokens` | Sanctum API tokens |
| `failed_jobs` | Failed queue jobs |
| `jobs` | Queue job storage |
| `job_batches` | Batch job tracking |
| `sessions` | User session storage |
| `cache` | Query result caching |
| `cache_locks` | Cache lock mechanism |
| `password_reset_tokens` | Password reset tokens |
| `migrations` | Migration tracking |

## Key Relationships

```
User (1) ──── (M) Appointment          [as client]
User (1) ──── (M) Appointment          [as personnel]
User (1) ──── (M) StaffSchedule
User (1) ──── (M) BlockedSlot          [as staff]
User (1) ──── (M) LeaveRequest
User (1) ──── (M) Notification
User (1) ──── (M) DeviceToken
User (1) ──── (M) Message              [as sender]
User (1) ──── (M) AuditLog
User (1) ──── (1) StaffProfile
User (M) ──── (M) Role                 [via spatie pivot]
User (M) ──── (M) Permission           [via spatie pivot]

Service (1) ──── (M) Appointment
Service (1) ──── (M) ServiceHour

Appointment (1) ──── (1) Payment
Appointment (1) ──── (M) Commission
Appointment (1) ──── (M) AppointmentChangeRequest

BusinessHour (1) ──── (0..1) BusinessHour  [day_of_week unique]

Payment (1) ──── (1) User               [verified_by]

Inventory (1) ──── (M) InventoryBatch
Inventory (1) ──── (M) StockMovement
InventoryBatch (1) ──── (M) StockMovement

MessageThread (1) ──── (M) Message
MessageThread (1) ──── (2) User          [user_a_id, user_b_id]

VerificationCode (M) ──── (1) User
```

## Soft Deletes

| Model | Table | Purpose |
|-------|-------|---------|
| `User` | `users` | Preserves appointment history, audit trails |
| `Service` | `services` | Preserves appointment history |
| `Message` | `messages` | Soft-deleted conversations |
| `MessageThread` | `message_threads` | Soft-deleted conversation containers |

## Audit Logging

Models with `Auditable` trait: User, Service, Appointment, Payment, Inventory, BusinessHour, StaffSchedule, BlockedSlot, LeaveRequest

Audit logs are immutable and include:
- `user_id` (actor)
- `event` (created/updated/deleted/restored)
- `auditable_type` + `auditable_id` (target)
- `old_values` (JSON)
- `new_values` (JSON)

Hidden attributes (passwords, tokens) are automatically filtered.

## Index Strategy

| Table | Index | Purpose |
|-------|-------|---------|
| `appointments` | `personnel_id`, `appointment_date`, `start_time` | Slot availability queries |
| `appointments` | `user_id`, `status` | Client appointment listing |
| `appointments` | `status`, `appointment_date` | Dashboard calendar queries |
| `blocked_slots` | `staff_user_id`, `blocked_date` | Availability checks |
| `staff_schedules` | `user_id`, `day_of_week` | Schedule lookups |
| `business_hours` | `day_of_week` (unique) | Operating hours lookup |
| `inventory_batches` | `inventory_id`, `received_at` | FIFO batch ordering |
| `stock_movements` | `inventory_id`, `created_at` | Movement history |
| `notifications` | `user_id`, `read_at` | Unread notification count |
| `messages` | `thread_id`, `created_at` | Message listing |
| `audit_logs` | `auditable_type`, `auditable_id` | Audit trail lookup |

## Data Types

| Column Type | Usage | Example |
|-------------|-------|---------|
| `bigint` | Primary keys, foreign keys | `id`, `user_id` |
| `string` | Names, emails, tokens | `first_name`, `email` |
| `text` | Long content | `notes`, `body`, `description` |
| `decimal(8,2)` | Money amounts | `price`, `amount`, `base_pay` |
| `time` | Time values | `start_time`, `end_time` |
| `date` | Date values | `appointment_date`, `blocked_date` |
| `timestamp` | DateTime values | `created_at`, `verified_at` |
| `boolean` | Flags | `is_closed`, `is_day_off` |
| `json` | Flexible data | `old_values`, `new_values`, `specializations` |
| `enum` | Restricted values | `status`, `method`, `platform` |
| `unsignedTinyInteger` | Role IDs | `role_id` (1-4) |
| `softDeletes` | Soft delete marker | `deleted_at` |

## Migration History

46 migrations covering:
- Core tables (users, services, appointments, payments)
- Availability system (business_hours, staff_schedules, blocked_slots, service_hours)
- Inventory system (inventories, inventory_batches, stock_movements)
- Communication (notifications, device_tokens, messages, message_threads)
- System (audit_logs, verification_codes, leave_requests, appointment_change_requests)
- Framework tables (roles, permissions, sessions, cache, jobs)

## Backup Strategy

| Component | Frequency | Method |
|-----------|-----------|--------|
| Database | Daily | `mysqldump` + cron |
| Storage files | Daily | S3 sync / rsync |
| Application code | On deploy | Git |
| Logs | Weekly | Archive + rotate |
