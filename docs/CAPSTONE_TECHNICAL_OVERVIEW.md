# Capstone Technical Overview — Perfect Nails Wellness & Aesthetics (NexFlio)

## Project Summary

**NexFlio** is a full-stack salon management system for "Perfect Nails Wellness & Aesthetics" — a spa and salon business in the Philippines. The system streamlines appointment booking, payment verification, staff management, inventory tracking, and customer communication through a web application (Laravel) and mobile app (Flutter).

## Key Features

### 1. Multi-Channel Booking System
- **Branch Booking:** Clients book in-salon services with date/time/staff selection
- **Home Service Booking:** Clients book mobile services with address management
- **Walk-In Booking:** Admin creates bookings for walk-in clients
- **No Preference Booking:** Clients can opt for manager-assigned staff
- **Availability Engine:** Real-time slot grid generation respecting business hours, staff schedules, blocked slots, service hours, and existing bookings

### 2. Payment Verification Workflow
- Clients upload GCash/Bank Transfer/Card proof of payment
- Managers verify or reject payments with reason
- Automatic commission calculation on verification
- Email + in-app + push notifications for all status changes

### 3. Staff Management
- Role-based access control (Owner, Manager, Staff, Client)
- Position-based filtering (Nail Technician, Massage Technician, Facial Technician)
- Leave request system with overlap detection
- Time tracking (time-in/time-out)
- Commission tracking per appointment

### 4. Inventory Management
- FIFO (First-In-First-Out) batch consumption
- Immutable stock movement ledger
- Row-level locking for concurrency safety
- Automatic consumption on appointment completion
- Reorder level alerts

### 5. Communication System
- Direct messaging between clients and staff
- In-app notifications with unread counts
- Email notifications (queued)
- Push notifications via Firebase Cloud Messaging
- Dialogflow chatbot integration

### 6. Analytics & Reporting
- Dashboard with key metrics (appointments, revenue, completion rate)
- Revenue by service and by personnel charts
- Top services and top personnel rankings
- AI-powered business insights via Google Gemini API
- Walk-in vs online comparison

## Technical Architecture

### Technology Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 11, PHP 8.2+ |
| Database | MySQL 8.x |
| Cache/Queue | Redis (production), Database (dev) |
| API Auth | Laravel Sanctum |
| RBAC | spatie/laravel-permission |
| Frontend (Web) | Blade + Bootstrap 5.3 |
| Frontend (Mobile) | Flutter (Dart) |
| Push Notifications | Firebase Cloud Messaging (HTTP v1) |
| AI Insights | Google Gemini API |
| Chatbot | Dialogflow |
| Email | Gmail SMTP |

### Architecture Pattern

**Layered Architecture:**
```
Presentation (Blade/API) → Routing → Controllers → Services → Models → Database
```

**Service Layer Pattern:**
- `AppointmentService` — Central state machine for appointment lifecycle
- `InventoryService` — FIFO stock management with concurrency safety
- `NotificationService` — Unified multi-channel notification dispatch
- `EmailVerificationService` — 6-digit OTP verification with rate limiting
- `GeminiService` — AI-powered business insights
- `FcmService` — Firebase Cloud Messaging with JWT auth

### Database Design

- **46 tables** covering core business, availability, inventory, communication, and system concerns
- **Soft deletes** on User, Service, Message, MessageThread for data preservation
- **Immutable audit logs** on sensitive models
- **Row-level locking** for inventory concurrency safety
- **FIFO batch tracking** for stock consumption

### State Machine

**Appointment Lifecycle:**
```
Unverified → Booked → InService → Completed
    ↓          ↓         ↓
Cancelled  Cancelled  Cancelled
              ↓
            NoShow
```

**Payment Lifecycle:**
```
Pending → Verified (→ Appointment: Booked)
Pending → Rejected (→ Appointment: Cancelled)
```

## Security Implementation

### Authentication
- Session-based auth for web (Laravel default)
- Token-based auth for API (Sanctum)
- 6-digit OTP email verification (bcrypt-hashed, 10-min expiry, 5-attempt limit)

### Authorization
- Role-based access control via `spatie/laravel-permission`
- Route-level middleware: `auth`, `verified`, `role:1,2` (admin), `role:3` (staff), `role:4` (client)
- Controller-level authorization for sensitive operations

### Data Protection
- Passwords hidden via `$hidden` array
- Audit logs exclude sensitive attributes
- Eloquent parameterized queries (SQL injection prevention)
- Blade auto-escaping (XSS prevention)
- CSRF protection on all forms

### Inventory Concurrency
- `lockForUpdate()` on inventory and batch rows
- Database transactions on all stock operations
- Idempotent commission creation via `firstOrCreate`

## Testing

- **128 tests** with **498 assertions**
- In-memory SQLite for test isolation
- BCrypt rounds reduced to 4 for speed
- Mail and queue disabled in tests
- Coverage: Models, Controllers, Services, Requests, Middleware, Enums

## Deployment Considerations

### Production Requirements
- PHP 8.2+ with extensions: openssl, mbstring, xml, curl, gd, mysql, redis
- MySQL 8.x with utf8mb4 charset
- Redis for cache, queue, and sessions
- Nginx/Apache with SSL termination
- Supervisor for queue workers
- Cron for scheduler

### Security Checklist
- Set `APP_DEBUG=false`
- Use strong database password
- Enable HTTPS
- Add rate limiting on auth endpoints
- Add security headers (CSP, X-Frame-Options, etc.)
- Rotate secrets if exposed
- Regular backups

## Capstone Documentation Files

| Document | Purpose |
|----------|---------|
| `SYSTEM_ARCHITECTURE.md` | High-level architecture, layered design, state management |
| `TECHNOLOGY_STACK.md` | Complete technology inventory with versions |
| `DATABASE_ARCHITECTURE.md` | ER diagram, table descriptions, relationships, indexes |
| `AUTHENTICATION_FLOW.md` | Login, registration, OTP, RBAC, session management |
| `BOOKING_FLOW.md` | End-to-end booking process, slot generation, collision detection |
| `MESSAGING_AND_NOTIFICATION_FLOW.md` | In-app, email, push notification architecture |
| `API_DOCUMENTATION.md` | REST API endpoints, request/response formats |
| `SECURITY_AUDIT.md` | Security findings, implemented measures, recommendations |
| `DEPLOYMENT_ANALYSIS.md` | Deployment options, server setup, scaling |
| `CAPSTONE_TECHNICAL_OVERVIEW.md` | This document — project summary for capstone |

## Project Metrics

| Metric | Value |
|--------|-------|
| Total PHP Files | 140+ |
| Eloquent Models | 28 |
| Web Controllers | 15 |
| API Controllers | 7 |
| Form Requests | 8 |
| Services | 6 |
| Mailable Classes | 5 |
| Blade Views | 60+ |
| Migrations | 46 |
| Enums | 7 |
| Tests | 128 |
| Assertions | 498 |
| Lines of Code | ~15,000+ |

## Development Timeline

### Phase 1: Foundation
- Laravel 11 project setup
- Database schema design and migrations
- User authentication and role management
- Basic CRUD operations

### Phase 2: Core Features
- Service catalog management
- Appointment booking system
- Payment verification workflow
- Staff management with positions

### Phase 3: Advanced Features
- Availability management (business hours, staff schedules, blocked slots)
- Inventory management with FIFO
- Direct messaging system
- Notification system (in-app, email, push)

### Phase 4: Polish
- Analytics dashboard with charts
- AI-powered business insights
- Dialogflow chatbot integration
- Mobile-responsive design
- Comprehensive testing (128 tests)

### Phase 5: Deployment Preparation
- Security audit
- Performance optimization
- Documentation
- Production configuration

## Future Enhancements

1. **Multi-branch support** — Currently single-branch, can be extended
2. **Recurring appointments** — Weekly/monthly booking patterns
3. **Loyalty program** — Points/rewards system
4. **Online payment integration** — Direct GCash/Stripe integration
5. **SMS notifications** — Via Twilio or local provider
6. **Advanced analytics** — Predictive demand, staff optimization
7. **Multi-language support** — Filipino, English toggle
8. **Progressive Web App** — Offline support for mobile web
9. **Staff mobile app** — Dedicated Flutter app for staff
10. **Client mobile app** — Enhanced Flutter app with full booking flow
