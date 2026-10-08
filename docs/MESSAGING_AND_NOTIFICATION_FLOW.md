# Messaging & Notification Flow — Perfect Nails Wellness & Aesthetics

## Notification Architecture

The system uses three notification channels:

```
┌─────────────────────────────────────────────────┐
│              NotificationService::notify()        │
│                                                  │
│  ┌──────────────┐  ┌──────────────┐  ┌────────┐ │
│  │  In-App       │  │  Email       │  │  Push  │ │
│  │  (Database)   │  │  (SMTP)      │  │ (FCM)  │ │
│  └──────────────┘  └──────────────┘  └────────┘ │
└─────────────────────────────────────────────────┘
```

### Channel Details

| Channel | Storage | Delivery | Reliability |
|---------|---------|----------|-------------|
| In-App | `notifications` table | Polling/API | 100% (database) |
| Email | SMTP (Gmail) | Queued (Laravel Mail) | Depends on mail server |
| Push | FCM HTTP v1 | Queued (SendPushNotification job) | Depends on device token |

## NotificationService

```php
// Unified dispatch point
NotificationService::notify(
    User $user,
    string $title,
    string $body,
    ?Mailable $mailable = null,  // Optional email
    array $pushData = []          // Optional push deep-link data
);

// Implementation:
// 1. Creates in-app notification record
// 2. Queues email if mailable provided AND user has email
// 3. Dispatches push notification job
// 4. Degrades silently if provider not configured
```

## In-App Notifications

### Storage

```sql
notifications
├── id (PK)
├── user_id (FK → users)
├── title (string)
├── body (text)
├── read_at (timestamp, nullable)
└── created_at (timestamp)
```

### Unread Count

```php
// User model
public function unreadNotifications(): int
{
    return $this->notifications()->whereNull('read_at')->count();
}

// Admin sidebar badge
auth()->user()->unreadNotifications()
```

### Marking as Read

```php
// Single notification
$notification->update(['read_at' => now()]);

// All notifications for user
$user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
```

## Email Notifications

### Mailable Classes

| Mailable | Trigger | Queued | Subject |
|----------|---------|--------|---------|
| `BookingReceivedMail` | Booking submitted | Yes | "We received your booking — Perfect Nails" |
| `PaymentVerifiedMail` | Payment verified | Yes | "Your booking is confirmed — Perfect Nails" |
| `PaymentRejectedMail` | Payment rejected | Yes | "About your booking payment — Perfect Nails" |
| `AppointmentReminderMail` | Daily scheduler | Yes | "Reminder: your appointment tomorrow — Perfect Nails" |
| `VerificationCodeMail` | Registration | **No** (sync) | "Your Perfect Nails verification code" |

### Email Template

All emails use `mail.notification` Blade view with consistent branding.

### Queue Configuration

```php
// Mail sending
Mail::to($user->email)->queue($mailable);

// Uses Laravel's mail queue
// Queue driver: database (dev) / redis (prod)
```

## Push Notifications (FCM)

### Architecture

```
NotificationService
  → SendPushNotification job (queued)
    → FcmService::sendToUser()
      → DeviceToken::where('user_id', $userId)->get()
      → For each device:
        → FcmService::sendToToken()
          → Signs JWT (RS256)
          → Gets OAuth2 access token
          → POST https://fcm.googleapis.com/v1/projects/{id}/messages:send
```

### Device Token Management

```sql
device_tokens
├── id (PK)
├── user_id (FK → users)
├── token (string, unique)
├── platform (enum: 'android', 'ios')
├── last_used_at (timestamp)
└── created_at (timestamp)
```

### Token Lifecycle

1. **Registration:** Mobile app registers token after login
2. **Update:** `last_used_at` updated on each successful push
3. **Cleanup:** Dead tokens (NOT_FOUND, UNREGISTERED) automatically deleted
4. **Revocation:** Token deleted on logout

### FCM JWT Construction

```
Header: { "alg": "RS256", "typ": "JWT" }
Payload: {
  "iss": "service-account@project.iam.gserviceaccount.com",
  "scope": "https://www.googleapis.com/auth/firebase.messaging",
  "aud": "https://oauth2.googleapis.com/token",
  "iat": <now>,
  "exp": <now + 1 hour>
}
Signature: RS256 with service account private key
```

### Token Caching

```php
// OAuth2 access token cached for 50 minutes
Cache::remember('fcm:access_token', now()->addMinutes(50), function () {
    // Exchange JWT for access token
    return $this->getAccessToken();
});
```

## Notification Events

### Booking Lifecycle Notifications

| Event | Recipient | In-App Title | Email | Push |
|-------|-----------|-------------|-------|------|
| Booking submitted | Manager | "New booking received" | BookingReceivedMail | ✓ |
| Payment verified | Client | "Payment verified" | PaymentVerifiedMail | ✓ |
| Payment rejected | Client | "Payment rejected" | PaymentRejectedMail | ✓ |
| Staff assigned | Client | "Staff assigned" | — | ✓ |
| Service started | Client | "Service started" | — | ✓ |
| Service completed | Client | "Service completed" | — | ✓ |
| Appointment reminder | Client | "Appointment tomorrow" | AppointmentReminderMail | ✓ |

### Change Request Notifications

| Event | Recipient | In-App Title |
|-------|-----------|-------------|
| Change request submitted | Manager | "Change request received" |
| Change request approved | Client | "Request approved" |
| Change request rejected | Client | "Request rejected" |

### Leave Request Notifications

| Event | Recipient | In-App Title |
|-------|-----------|-------------|
| Leave request submitted | Manager | "Leave request received" |
| Leave request approved | Staff | "Leave approved" |
| Leave request rejected | Staff | "Leave rejected" |

## Messaging System

### Architecture

```
┌─────────────────────────────────────────────────┐
│                 Message Threads                   │
│                                                  │
│  Thread (user_a_id, user_b_id)                   │
│    ├── Message 1 (sender_id: user_a)             │
│    ├── Message 2 (sender_id: user_b)             │
│    └── Message 3 (sender_id: user_a)             │
└─────────────────────────────────────────────────┘
```

### Database Schema

```sql
message_threads
├── id (PK)
├── user_a_id (FK → users)
├── user_b_id (FK → users)
├── last_message_at (timestamp)
├── created_at (timestamp)
├── updated_at (timestamp)
└── deleted_at (timestamp, soft delete)

messages
├── id (PK)
├── thread_id (FK → message_threads)
├── sender_id (FK → users)
├── body (text)
├── read_at (timestamp, nullable)
├── deleted_at (timestamp, soft delete)
└── created_at (timestamp)
```

### Message Flow

```
1. Client opens messaging page
2. System finds or creates thread between client and manager
3. Client types message
4. POST /account/messages
   → Validates: body (required, max:1000)
   → Creates message record
   → Updates thread.last_message_at
   → Optionally sends push notification to recipient
5. Manager receives notification
6. Manager opens thread, sees message
7. Messages marked as read when viewed
```

### Thread Management

```php
// Find or create thread between two users
$thread = MessageThread::where(function ($q) use ($userId, $otherId) {
    $q->where('user_a_id', $userId)->where('user_b_id', $otherId);
})->orWhere(function ($q) use ($userId, $otherId) {
    $q->where('user_a_id', $otherId)->where('user_b_id', $userId);
})->first();

if (!$thread) {
    $thread = MessageThread::create([
        'user_a_id' => min($userId, $otherId),
        'user_b_id' => max($userId, $otherId),
    ]);
}
```

### Unread Message Count

```php
// Per thread
$unreadCount = $thread->messages()
    ->where('sender_id', '!=', $currentUserId)
    ->whereNull('read_at')
    ->count();

// Total unread across all threads
$totalUnread = Message::where('sender_id', '!=', $currentUserId)
    ->whereHas('thread', function ($q) use ($userId) {
        $q->where('user_a_id', $userId)->orWhere('user_b_id', $userId);
    })
    ->whereNull('read_at')
    ->count();
```

## Notification Preferences

Currently, all notifications are sent to all channels. Future enhancements could include:

- User-level notification preferences per channel
- Quiet hours (no push notifications)
- Notification frequency settings
- Email digest options

## Graceful Degradation

All notification channels degrade silently if not configured:

```php
// NotificationService
if ($mailable && $user->email) {
    Mail::to($user->email)->queue($mailable);
}

// FcmService
if (!$this->isConfigured()) {
    return; // Silently skip push
}
```

This ensures the application remains functional even if email or push services are unavailable.

## Scheduled Notifications

```php
// routes/console.php
Schedule::command('appointments:remind')->dailyAt('08:00');

// SendAppointmentReminders command
// → Queries tomorrow's booked appointments
// → Sends reminders via NotificationService
// → Handles all three channels (in-app, email, push)
// → Skips appointments with no user_id (guest bookings)
```
