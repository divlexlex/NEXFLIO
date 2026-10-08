# Booking Flow — Perfect Nails Wellness & Aesthetics (NexFlio)

## Overview

The booking system supports two modes:
1. **Branch Booking** — Client visits the physical salon
2. **Home Service Booking** — Staff visits the client's location

Both flows share the same core logic but differ in location handling and service eligibility.

## Booking Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    CLIENT BOOKING FLOW                       │
│                                                              │
│  1. Select Service Category                                  │
│     → Facial / Massage / Nails / Lashes & Brows /           │
│       Aesthetics / Head Spa / Home Service                   │
│                                                              │
│  2. Select Service                                           │
│     → Shows services filtered by category + location type    │
│     → Branch booking: service_location_type in [branch, both]│
│     → Home booking: service_location_type in [home, both]    │
│                                                              │
│  3. Select Staff (or "No Preference")                        │
│     → Filters personnel by service category → position map:  │
│       - Facial → Facial Technician                           │
│       - Massage → Massage Technician                         │
│       - Nails → Nail Technician                              │
│       - Other categories → All staff                         │
│     → "No Preference" sets personnel_id = null               │
│                                                              │
│  4. Select Date & Time                                       │
│     → Calendar shows available dates                         │
│     → Time slots filtered by:                                │
│       - Business hours (10AM–9PM daily)                      │
│       - Staff schedule (staff_schedules table)               │
│       - Blocked slots (blocked_slots table)                  │
│       - Service hours (service_hours table)                  │
│       - Existing bookings (collision check)                  │
│       - Past time slots hidden (not shown)                   │
│     → After 9PM → min date = tomorrow                        │
│                                                              │
│  5. Upload Payment Proof                                     │
│     → GCash / Bank Transfer / Card                           │
│     → Image upload (max 5MB)                                 │
│                                                              │
│  6. Confirm Booking                                          │
│     → Appointment created with status: unverified            │
│     → Payment created with status: pending                   │
│     → Booking received email sent                            │
│     → Manager notified for verification                      │
└─────────────────────────────────────────────────────────────┘
```

## Slot Grid Generation (`BookingController::buildSlotGrid`)

The slot grid is the core of availability calculation. For each potential 30-minute slot:

```
For each date in range (today → today + maxSlots days):
  For each time from service_start to service_end (30-min increments):
    Skip if time is in the past (today only)
    Skip if outside business hours
    Skip if outside staff schedule (if staff selected)
    Skip if staff has blocked slot at this time
    Skip if outside service hours (if configured)
    Skip if staff has existing booking at this time (collision)
    Skip if staff has approved leave covering this date
    → Slot is AVAILABLE
```

### Collision Detection

Two types of collision checks:

1. **Column conflict:** Same staff, same date, overlapping time range
   ```sql
   SELECT * FROM appointments
   WHERE personnel_id = :personnelId
     AND appointment_date = :date
     AND start_time < :endTime
     AND end_time > :startTime
     AND status NOT IN ('cancelled', 'no-show')
   ```

2. **Row conflict:** Same staff, same date, same start time
   ```sql
   WHERE personnel_id = :personnelId
     AND appointment_date = :date
     AND start_time = :startTime
     AND status NOT IN ('cancelled', 'no-show')
   ```

### Availability Sources (in order of precedence)

1. **Blocked Slots** — Hard blocks (staff vacation, emergencies)
2. **Staff Schedules** — Individual working hours per day
3. **Business Hours** — Branch operating hours (10AM–9PM)
4. **Service Hours** — Per-service time restrictions (e.g., Home Service 8AM–6PM)
5. **Existing Bookings** — Collision detection against confirmed appointments
6. **Leave Requests** — Approved staff leave

## Personnel Filtering

```php
// Category → Position mapping
$categoryPositionMap = [
    'Facial' => Position::FacialTechnician,
    'Massage' => Position::MassageTechnician,
    'Nails' => Position::NailTechnician,
];

// If category has a position mapping, filter by that position
// Otherwise, show all staff
$personnel = User::where('role_id', User::ROLE_STAFF)
    ->when($position, fn($q) => $q->where('position', $position))
    ->get();
```

## "No Preference" Booking

When a client selects "No preference":

1. `prepareForValidation()` converts `"any"` → `null` for `personnel_id`
2. Collision check is skipped (no specific staff to check)
3. Appointment created with `personnel_id = null`
4. Manager assigns staff later via "Assign Staff" modal
5. "Start service" button hidden until staff is assigned
6. Client notification sent when staff is assigned

```php
// BookingController::store()
$personnelId = $validated['personnel_id'] ?? null;

$collision = $personnelId
    ? $this->findCollision($personnelId, $date, $startTime, $endTime)
    : null;

if ($collision) {
    return back()->withErrors(['start_time' => 'That slot was just taken.']);
}
```

## Branch Booking vs Home Booking

| Aspect | Branch Booking | Home Booking |
|--------|---------------|--------------|
| Route | `/account/booking/branch/*` | `/account/booking/home/*` |
| Service Filter | `service_location_type IN (branch, both)` | `service_location_type IN (home, both)` |
| Address | Not required | Required (street, barangay, city, province) |
| Saved Addresses | Optional selection | Can select from saved addresses |
| Controller | `BookingController@branch*` | `BookingController@home*` |
| Form Request | `StoreWebBranchBookingRequest` | `StoreWebHomeBookingRequest` |

## Walk-In Booking (Admin)

Admin can create walk-in appointments:

```
1. Admin clicks "Walk-In" button on appointments page
2. Modal shows: service, staff, date, time, notes
3. personnel_id is REQUIRED (admin must assign staff)
4. Payment proof is REQUIRED
5. Appointment created with status: unverified (or verified if admin)
6. Payment record created
```

## Appointment Status Lifecycle

```
┌─────────────┐
│ Unverified   │ ← Initial state after booking
│              │ ← Payment proof uploaded, awaiting verification
└──────┬──────┘
       │
       ▼
┌─────────────┐     ┌─────────────┐
│ Booked       │     │ Cancelled   │ ← Manager rejects payment
│              │────►│              │
└──────┬──────┘     └─────────────┘
       │
       ▼
┌─────────────┐     ┌─────────────┐
│ InService    │     │ NoShow      │ ← Client didn't show up
│              │────►│              │
└──────┬──────┘     └─────────────┘
       │
       ▼
┌─────────────┐
│ Completed    │ ← Terminal state
└─────────────┘
```

### Valid Transitions

| From | To | Trigger |
|------|-----|---------|
| Unverified | Booked | Manager verifies payment |
| Unverified | Cancelled | Manager rejects payment |
| Booked | InService | Staff starts service |
| Booked | Cancelled | Manager/Client cancels |
| Booked | NoShow | Manager marks no-show |
| InService | Completed | Staff finishes service |
| InService | Cancelled | Manager cancels |

### Transition Enforcement

```php
// AppointmentStatus enum
public function transitions(): array
{
    return match ($this) {
        self::Unverified => [self::Booked, self::Cancelled],
        self::Booked => [self::InService, self::Cancelled, self::NoShow],
        self::InService => [self::Completed, self::Cancelled],
        self::Completed => [],
        self::Cancelled => [],
        self::NoShow => [],
    };
}
```

## Payment Verification Flow

```
1. Client uploads payment proof with booking
2. Appointment created: status = unverified, payment = pending
3. Manager sees payment in admin dashboard
4. Manager reviews proof image
5. Manager clicks "Verify" or "Reject"
6. If Verify:
   → Payment status → verified
   → Appointment status → booked
   → Commission calculated (service_price × commission_rate / 100)
   → Client notified (PaymentVerifiedMail)
7. If Reject:
   → Payment status → rejected
   → Appointment status → cancelled
   → Client notified (PaymentRejectedMail) with reason
```

## Commission Calculation

```php
// Only calculated on payment verification
$commission = $service->price * ($personnel->commission_rate ?? 10) / 100;
$commission = round($commission, 2);

// Idempotent: firstOrCreate ensures no duplicate commissions
Commission::firstOrCreate(
    ['appointment_id' => $appointment->id],
    [
        'user_id' => $appointment->personnel_id,
        'amount' => $commission,
    ]
);
```

## Inventory Consumption

On appointment completion (`AppointmentService::transition`):

```php
// Inventory consumed only when status = Completed
if ($target === AppointmentStatus::Completed) {
    $this->consumeItems($appointment, $items, $actor);
}

// FIFO consumption via InventoryService
// 1. Lock inventory row
// 2. Get batches in FIFO order (received_at, id)
// 3. Deduct from oldest batch first
// 4. Create stock_movements for each batch touched
// 5. Decrement inventory quantity cache
// 6. Throw InsufficientStockException if stock inadequate
```

## Client Change Requests

Clients can request changes to existing appointments:

| Type | Description | Requirements |
|------|-------------|-------------|
| `reschedule` | Move to different date/time | New date + time required |
| `cancel` | Cancel the appointment | Reason optional |

### Change Request Validation

- Only allowed for `Unverified` or `Booked` status
- Only the appointment owner can request changes
- No duplicate pending requests allowed
- Reschedule to same date/time is rejected
- Manager reviews and approves/rejects

## Notification Triggers

| Event | In-App | Email | Push |
|-------|--------|-------|------|
| Booking submitted | Manager | Client (BookingReceivedMail) | Manager |
| Payment verified | Client | Client (PaymentVerifiedMail) | Client |
| Payment rejected | Client | Client (PaymentRejectedMail) | Client |
| Appointment reminder | Client | Client (AppointmentReminderMail) | Client |
| Staff assigned | Client | — | Client |
| Appointment started | Client | — | Client |
| Appointment completed | Client | — | Client |
| Change request submitted | Manager | — | Manager |
| Change request approved | Client | — | Client |
| Change request rejected | Client | — | Client |

## Console Scheduler

```
// routes/console.php
Schedule::command('appointments:remind')->dailyAt('08:00');

// SendAppointmentReminders
// → Queries tomorrow's booked appointments
// → Sends in-app + email + push reminders
// → Skips appointments with no user_id
```

## Booking Time Constraints

| Constraint | Value | Notes |
|-----------|-------|-------|
| Business hours | 10:00–21:00 | Daily, including weekends |
| Slot duration | 30 minutes | Fixed interval |
| After 9PM | Min date = tomorrow | Prevents impossible bookings |
| Service duration | Per service config | 30–480 minutes |
| Max advance booking | Configurable | Default: 30 days |
| Past slots | Hidden | Not shown as disabled |
