import 'package:flutter/material.dart';

const String kAppName = 'Perfect Nails';

// ===== API CONFIGURATION =====
// The Laravel backend is expected to be running locally via `php artisan serve`
// (default port 8000). Every platform reaches it via localhost — for Android
// (emulator or a real USB-connected device), that requires
// `adb reverse tcp:8000 tcp:8000` so the device's "localhost" tunnels back to
// this machine over ADB.
const String kApiBaseUrl = 'http://localhost:8000/api';

// Base URL for files stored via `Storage::disk('public')` (served through the
// `storage` symlink), e.g. uploaded proof-of-payment photos.
const String kStorageBaseUrl = 'http://localhost:8000/storage';

// ===== ELEGANT SPA PALETTE (cream / blush / dark espresso) =====
// Base sa "Perfect Spa" reference — light at airy, hindi coffee-shop dark.

const kBackgroundColor = Color(0xFFF7ECE1); // soft cream/blush background
const kCardColor = Color(0xFFFFFFFF); // white cards
const kSecondaryColor = Color(
  0xFFE8D5C4,
); // light tan/beige (borders, tints, image placeholders)
const kPrimaryColor = Color(
  0xFF9C7A54,
); // muted gold-brown (prices, links, "See all")
const kAccentColor = Color(
  0xFF3D2817,
); // deep espresso brown (banners, dark buttons, icons)
const kTextColor = Color(0xFF3D2817); // dark espresso brown text
const kBlushAccent = Color(
  0xFFE8B4B8,
); // soft cherry-blossom pink (optional decorative accent)

const double kDefaultPadding = 20.0;

// ===== APPOINTMENT STATUSES =====
// Must match the backend's App\Enums\AppointmentStatus values.
// Flow: unverified -> booked -> in-service -> completed
//       (cancelled / no-show are terminal side exits)
const String kStatusUnverified = 'unverified';
const String kStatusBooked = 'booked';
const String kStatusInService = 'in-service';
const String kStatusCompleted = 'completed';
const String kStatusCancelled = 'cancelled';
const String kStatusNoShow = 'no-show';

const Map<String, String> kAppointmentStatusLabels = {
  kStatusUnverified: 'Pending verification',
  kStatusBooked: 'Booked',
  kStatusInService: 'In service',
  kStatusCompleted: 'Completed',
  kStatusCancelled: 'Cancelled',
  kStatusNoShow: 'No-show',
};

String appointmentStatusLabel(String status) =>
    kAppointmentStatusLabels[status] ?? status;

Color appointmentStatusColor(String status) {
  switch (status) {
    case kStatusBooked:
    case kStatusInService:
      return Colors.green;
    case kStatusCompleted:
      return kPrimaryColor;
    case kStatusCancelled:
    case kStatusNoShow:
      return Colors.redAccent;
    case kStatusUnverified:
    default:
      return Colors.orange;
  }
}
