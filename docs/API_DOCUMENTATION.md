# API Documentation — Perfect Nails Wellness & Aesthetics (NexFlio)

## Overview

- **Base URL:** `/api` (local: `http://localhost:8000/api`)
- **Authentication:** Laravel Sanctum (Bearer token)
- **Content-Type:** `application/json`
- **Rate Limiting:** Not currently configured (recommend adding in production)

## Authentication Endpoints

### POST `/api/login`

Login and receive a Sanctum token.

**Request:**
```json
{
  "email": "delacruz.sheila@nexflio.test",
  "password": "delacruz00000"
}
```

**Response (200):**
```json
{
  "message": "Login successful",
  "token": "1|abc123...",
  "user": {
    "id": 2,
    "first_name": "Sheila",
    "last_name": "Delacruz",
    "email": "delacruz.sheila@nexflio.test",
    "role_id": 2,
    "role": "Manager"
  },
  "role": "Manager"
}
```

**Response (422):**
```json
{
  "message": "Invalid credentials",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

### POST `/api/register`

Register a new client account.

**Request:**
```json
{
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "email": "juan@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response (201):**
```json
{
  "message": "Registration successful. Please check your email for verification code."
}
```

### POST `/api/verify-email`

Verify email with 6-digit code.

**Request:**
```json
{
  "email": "juan@example.com",
  "code": "123456"
}
```

**Response (200):**
```json
{
  "message": "Email verified successfully"
}
```

### POST `/api/logout`

Revoke current access token.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Logged out"
}
```

### POST `/api/device-token`

Register FCM device token for push notifications.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "token": "fcm_device_token_here",
  "platform": "android"
}
```

**Response (200):**
```json
{
  "message": "Device token registered"
}
```

### DELETE `/api/device-token/{token}`

Remove a device token.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Device token removed"
}
```

## Guest Endpoints (No Auth Required)

### GET `/api/guest/services`

List all active services.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Classic Facial",
      "category": "Facial",
      "price": 500.00,
      "duration_minutes": 60,
      "description": "Deep cleansing facial treatment",
      "image_url": "http://localhost:8000/storage/services/facial.jpg",
      "service_location_type": "both"
    }
  ]
}
```

### GET `/api/guest/nearby`

List nearby branches (placeholder — returns all branches for now).

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Perfect Nails - Main Branch",
      "address": "123 Main Street, Makati City",
      "latitude": 14.5547,
      "longitude": 121.0500
    }
  ]
}
```

## Authenticated Client Endpoints

### GET `/api/profile`

Get current user profile.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": {
    "id": 4,
    "first_name": "Juan",
    "last_name": "Dela Cruz",
    "email": "juan@example.com",
    "contact_number": "09171234567",
    "gender": "male",
    "role_id": 4,
    "email_verified_at": "2026-09-24T10:00:00.000000Z"
  }
}
```

### PUT `/api/profile`

Update current user profile.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "first_name": "Juan",
  "last_name": "Dela Cruz",
  "contact_number": "09171234567",
  "gender": "male"
}
```

**Response (200):**
```json
{
  "message": "Profile updated",
  "data": { ... }
}
```

### GET `/api/addresses`

List client saved addresses.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "street_address": "456 Oak Street",
      "barangay": "Barangay 123",
      "city_municipality": "Makati City",
      "province": "Metro Manila",
      "postal_code": "1234",
      "is_default": true
    }
  ]
}
```

### POST `/api/addresses`

Create a new saved address.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "street_address": "456 Oak Street",
  "barangay": "Barangay 123",
  "city_municipality": "Makati City",
  "province": "Metro Manila",
  "postal_code": "1234",
  "is_default": true
}
```

### GET `/api/notifications`

List user notifications.

**Headers:** `Authorization: Bearer {token}`

**Query:** `?per_page=20`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "title": "Payment verified",
      "body": "Your payment for Classic Facial has been verified.",
      "read_at": null,
      "created_at": "2026-09-24T10:00:00.000000Z"
    }
  ],
  "unread_count": 5
}
```

### PUT `/api/notifications/{id}/read`

Mark notification as read.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Notification marked as read"
}
```

### PUT `/api/notifications/read-all`

Mark all notifications as read.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "All notifications marked as read"
}
```

### GET `/api/messages`

List message threads.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "other_user": {
        "id": 2,
        "first_name": "Sheila",
        "last_name": "Delacruz",
        "role": "Manager"
      },
      "last_message": "Thank you for your inquiry.",
      "last_message_at": "2026-09-24T10:00:00.000000Z",
      "unread_count": 2
    }
  ]
}
```

### GET `/api/messages/{threadId}`

Get messages in a thread.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "sender_id": 4,
      "body": "Hi, I have a question about my appointment.",
      "read_at": "2026-09-24T10:00:00.000000Z",
      "created_at": "2026-09-24T09:00:00.000000Z"
    }
  ]
}
```

### POST `/api/messages`

Send a message.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "recipient_id": 2,
  "body": "Thank you for your help!"
}
```

**Response (201):**
```json
{
  "message": "Message sent",
  "data": {
    "id": 2,
    "sender_id": 4,
    "body": "Thank you for your help!",
    "created_at": "2026-09-24T11:00:00.000000Z"
  }
}
```

### GET `/api/appointments`

List user's appointments.

**Headers:** `Authorization: Bearer {token}`

**Query:** `?status=booked&per_page=20`

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "service": {
        "name": "Classic Facial",
        "category": "Facial",
        "price": 500.00
      },
      "personnel": {
        "id": 5,
        "first_name": "Maria",
        "last_name": "Santos"
      },
      "appointment_date": "2026-09-25",
      "start_time": "10:00",
      "end_time": "11:00",
      "status": "booked",
      "notes": "First-time visit"
    }
  ]
}
```

### POST `/api/appointments`

Create a new appointment (mobile booking).

**Headers:** `Authorization: Bearer {token}`

**Request (multipart/form-data):**
```
service_id: 1
personnel_id: 5 (or omit for "no preference")
appointment_date: 2026-09-25
start_time: 10:00
notes: First-time visit
payment_proof: [image file]
method: gcash
street_address: 456 Oak Street (for home service)
barangay: Barangay 123
city_municipality: Makati City
province: Metro Manila
```

**Response (201):**
```json
{
  "message": "Appointment booked successfully",
  "data": {
    "id": 1,
    "status": "unverified",
    "appointment_date": "2026-09-25",
    "start_time": "10:00"
  }
}
```

### GET `/api/appointments/{id}`

Get appointment details.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "service": { ... },
    "personnel": { ... },
    "payment": {
      "status": "pending",
      "method": "gcash",
      "proof_url": "http://localhost:8000/storage/payments/proof.jpg"
    },
    "appointment_date": "2026-09-25",
    "start_time": "10:00",
    "end_time": "11:00",
    "status": "unverified",
    "notes": "First-time visit"
  }
}
```

### POST `/api/appointments/{id}/change-request`

Request appointment reschedule or cancellation.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "type": "reschedule",
  "reason": "Conflict with work schedule",
  "requested_date": "2026-09-26",
  "requested_start_time": "14:00"
}
```

**Response (201):**
```json
{
  "message": "Change request submitted"
}
```

## Staff Endpoints (role:3)

### GET `/api/staff/dashboard`

Get staff dashboard data.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "data": {
    "today_appointments": [
      {
        "id": 1,
        "client": {
          "first_name": "Juan",
          "last_name": "Dela Cruz"
        },
        "service": {
          "name": "Classic Facial"
        },
        "appointment_date": "2026-09-24",
        "start_time": "10:00",
        "end_time": "11:00",
        "status": "booked"
      }
    ],
    "is_on_leave": false,
    "is_time_in": true,
    "attendance_status": "Time In: 08:55 AM"
  }
}
```

### POST `/api/staff/time-in`

Staff time-in for the day.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Time in recorded",
  "time_in": "08:55 AM"
}
```

### POST `/api/staff/time-out`

Staff time-out for the day.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Time out recorded",
  "time_out": "05:05 PM"
}
```

### PATCH `/api/staff/appointments/{id}/start-service`

Start an in-service appointment.

**Headers:** `Authorization: Bearer {token}`

**Response (200):**
```json
{
  "message": "Service started"
}
```

### PATCH `/api/staff/appointments/{id}/complete`

Complete an in-service appointment.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "items": [
    {
      "inventory_id": 1,
      "quantity": 2
    }
  ]
}
```

**Response (200):**
```json
{
  "message": "Service completed"
}
```

### POST `/api/staff/leave`

Submit a leave request.

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "start_date": "2026-10-01",
  "end_date": "2026-10-03",
  "type": "vacation",
  "reason": "Family vacation"
}
```

**Response (201):**
```json
{
  "message": "Leave request submitted"
}
```

## Error Responses

### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
```json
{
  "message": "Forbidden."
}
```

### 404 Not Found
```json
{
  "message": "Not found."
}
```

### 422 Validation Error
```json
{
  "message": "Validation failed",
  "errors": {
    "field": ["Error message"]
  }
}
```

### 500 Server Error
```json
{
  "message": "Server error"
}
```

## API Response Format

All responses follow Laravel's resource format:

```json
// Single resource
{
  "data": { ... }
}

// Collection
{
  "data": [ ... ]
}

// Paginated
{
  "data": [ ... ],
  "links": {
    "first": "/api/resource?page=1",
    "last": "/api/resource?page=5",
    "prev": null,
    "next": "/api/resource?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "per_page": 20,
    "to": 20,
    "total": 100
  }
}
```

## Rate Limiting

Rate limiting is not currently configured on API endpoints. Recommended for production:

```php
// routes/api.php
Route::middleware('throttle:60,1')->group(function () {
    // Auth routes
});

Route::middleware('throttle:120,1')->group(function () {
    // Authenticated routes
});
```

## Dialogflow Webhook Integration

The Dialogflow chatbot uses webhook fulfillment:

**Endpoint:** `POST /api/dialogflow/webhook`
**Authentication:** `DIALOGFLOW_WEBHOOK_TOKEN` header

**Supported Intents:**
- List services by category
- Check availability for a service
- Initiate booking (creates pending appointment)
- Get appointment status

**Request:**
```json
{
  "queryResult": {
    "intent": "booking.initiate",
    "parameters": {
      "service_category": "Facial",
      "appointment_date": "2026-09-25",
      "start_time": "10:00"
    }
  },
  "originalDetectIntentRequest": {
    "source": "telegram",
    "payload": {
      "data": {
        "from": { "id": 12345 }
      }
    }
  }
}
```
