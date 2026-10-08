# Authentication & Authorization Flow — Perfect Nails Wellness & Aesthetics

## Overview

- **Web:** Session-based authentication with Laravel's built-in auth
- **API:** Token-based authentication via Laravel Sanctum
- **Roles:** 4 roles managed by `spatie/laravel-permission`
- **Email Verification:** 6-digit OTP code (not email link)

## Roles & Permissions

| Role ID | Role Name | Access Level |
|---------|-----------|-------------|
| 1 | Owner | Full system access including Activity Log and Financial Reports |
| 2 | Manager | Admin access except Activity Log and Financial Reports |
| 3 | Staff | Staff portal only (dashboard, leave, availability, time tracking) |
| 4 | Client | Client portal (booking, appointments, profile, messages) |

## Login Flow

### Web Login

```
1. User visits /login
2. Enters email/username + password
3. AuthController::login() processes request
   → Validates: email/username (required), password (required)
   → Finds user by email OR username (dual lookup)
   → Validates password via Hash::check()
   → Sets session via Auth::attempt()
4. On success:
   → Redirects to role-based dashboard:
     - Role 1 (Owner) → /admin
     - Role 2 (Manager) → /admin
     - Role 3 (Staff) → /staff/dashboard
     - Role 4 (Client) → /account/dashboard
5. On failure:
   → Returns with error message
```

### API Login (Mobile App)

```
1. POST /api/login
   → email + password
2. AuthController::apiLogin()
   → Validates credentials
   → Creates Sanctum personal access token
   → Returns token + user data + role info
3. Client stores token for subsequent API calls
4. Subsequent requests: Authorization: Bearer {token}
```

### Device Token Registration (Mobile Push)

```
1. After login, mobile app registers FCM device token
   → POST /api/device-token
   → token + platform (android/ios)
2. FCMService stores token for push notification delivery
3. Token updated on each successful push (last_used_at)
4. Dead tokens automatically cleaned up
```

## Registration Flow

```
1. User visits /register (web) or POST /api/register (mobile)
2. Fills: first_name, last_name, email, password, password_confirmation
3. AuthController::register()
   → Creates User with role_id = 4 (Client)
   → Hashes password via Hash::make()
   → Issues verification code via EmailVerificationService
   → Returns success message
4. EmailVerificationService::issue()
   → Generates 6-digit code via random_int(100000, 999999)
   → Hashes code with bcrypt (never stores plaintext)
   → Stores in verification_codes table
   → Sends VerificationCodeMail (synchronous, not queued)
   → Sets 10-minute expiry
5. User enters code on verification screen
6. EmailVerificationService::attempt()
   → Validates code against latest unconsumed record
   → Checks expiry (10 minutes)
   → Checks attempts (max 5)
   → On success: marks code as consumed, sets email_verified_at
   → On failure: increments attempts counter
```

### Verification Code States

| Return Value | Meaning |
|-------------|---------|
| `verified` | Code valid, email verified |
| `invalid` | Code does not match |
| `expired` | Code older than 10 minutes |
| `too_many_attempts` | More than 5 failed attempts |
| `no_pending_code` | No unconsumed code exists |

### Rate Limiting

- 5 attempts maximum per code
- Previous codes invalidated when new code issued
- Only latest unconsumed code is valid
- Codes hashed with bcrypt (not reversible)

## Role-Based Access Control

### Middleware Stack

```
Route group:
  → auth (session must exist)
    → verified (email_verified_at must be set)
      → role:4 (spatie role middleware)
```

### Route Protection by Role

| Route Group | Middleware | Access |
|-------------|-----------|--------|
| Guest routes (`/`) | `guest` | Unauthenticated users only |
| Client portal (`/account/*`) | `auth`, `role:4` | Clients only |
| Staff portal (`/staff/*`) | `auth`, `role:3` | Staff only |
| Admin panel (`/admin/*`) | `auth`, `role:1,2` | Owner + Manager |
| Owner-only (Activity Log, Financial Reports) | `auth`, `role:1` | Owner only |
| API (`/api/*`) | `sanctum:auth` | Authenticated token holders |
| API guest (`/api/guest/*`) | None | Public access |

### Authorization Checks in Controllers

Beyond middleware, controllers perform additional authorization:

```php
// Appointment status updates
// Only managers+ can verify/reject bookings
// After verification, only assigned staff or managers can update
if (!$actor->hasRole(['Owner', 'Manager'])) {
    // Only assigned personnel can update their own appointments
    if ($appointment->personnel_id !== $actor->id) {
        abort(403);
    }
}

// Client appointment changes
// Only the appointment owner can request changes
if ($appointment->user_id !== $this->user()->id) {
    abort(403);
}

// Employee management
// Only Owner can delete employees
// Owner + Manager can create/edit employees
```

## Password Security

- **Hashing:** `Hash::make()` (bcrypt, 12 rounds in production)
- **Verification:** `Hash::check()`
- **Reset:** Laravel's built-in password reset via `PasswordResetLinkRequestController`
- **Temp Passwords:** `lastname00000` format for new employees (should be changed on first login)

## Session Management

- **Driver:** Database (production)
- **Expiration:** Configured via `config/session.php`
- **Encryption:** Enabled by default
- **Secure Cookies:** Required in production (HTTPS)

## API Authentication (Sanctum)

### Token Types

| Type | Purpose | Lifetime |
|------|---------|----------|
| Personal Access Token | Mobile app API access | Until revoked |
| SPA Cookie | Web browser API access | Session-based |

### Token Scopes

Tokens are created without specific scopes (full access within the user's role).

### Token Usage

```php
// Creating token (login response)
$token = $user->createToken('mobile-app')->plainTextToken;

// Using token (API request)
Authorization: Bearer {token}

// Checking token
auth()->user() // Returns authenticated user
```

### Device Token Management

```
POST /api/device-token
  → Stores FCM token for push notifications
  → Linked to user_id + platform

DELETE /api/device-token/{token}
  → Removes device token on logout
```

## Email Verification Flow

```
Registration
  → EmailVerificationService::issue()
    → Generate 6-digit code
    → Hash with bcrypt
    → Store in verification_codes (channel: 'email')
    → Send VerificationCodeMail (synchronous)
    → Set expires_at (now + 10 minutes)

Verification Screen
  → User enters 6-digit code
  → POST /api/verify-email or /verify-email
    → EmailVerificationService::attempt()
      → Find latest unconsumed code for user
      → Check: attempts < 5
      → Check: now() < expires_at
      → Check: Hash::check($code, $code_hash)
      → If valid: consumed_at = now(), email_verified_at = now()
      → If invalid: increment attempts
      → Return status string
```

## Security Measures

### Implemented

- CSRF protection (Laravel default)
- XSS protection (Blade auto-escaping)
- SQL injection prevention (Eloquent parameterized queries)
- Password hashing (bcrypt)
- Verification code hashing (bcrypt)
- Rate limiting on verification attempts (5 max)
- Audit logging on sensitive operations
- Soft deletes for data preservation
- Row-level locking for inventory concurrency
- Dead FCM token cleanup
- Cache serialization disabled (prevents gadget chain attacks)

### Production Recommendations

- `APP_DEBUG=false`
- HTTPS enforced
- Secure cookie flags
- Rate limiting on login attempts
- IP-based brute force protection
- Regular secret rotation
- Database connection encryption
- File upload validation (size, type)
