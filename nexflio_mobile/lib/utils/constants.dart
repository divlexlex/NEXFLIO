import 'package:flutter/material.dart';

const String kAppName = 'Perfect Nails';

// ===== API CONFIGURATION =====
// The Laravel backend runs on the dev machine via `php artisan serve
// --host=0.0.0.0` (default port 8000) and is reached over the LAN by its IP
// address — this works for any device (Android or iOS) connected to the same
// WiFi network as the dev machine. Update this if the dev machine's IP
// changes (check with `ipconfig` / `Get-NetIPAddress`).
const String kApiOrigin = String.fromEnvironment(
  'API_ORIGIN',
  defaultValue: 'http://127.0.0.1:8000',
);
const String kApiBaseUrl = '$kApiOrigin/api';

// Base URL for files stored via `Storage::disk('public')` (served through the
// `storage` symlink), e.g. uploaded proof-of-payment photos.
const String kStorageBaseUrl = '$kApiOrigin/storage';

// ===== LUXURY NAIL-SPA PALETTE (latte / caramel / espresso + gold) =====
// Keeps the warm brown identity but elevated. The four surface/text tokens
// below are **theme-driven**: `applyThemePalette()` swaps them to the dark set
// whenever the active theme changes, so every screen that references them
// (kBackgroundColor / kCardColor / kSecondaryColor / kTextColor) reads as a
// polished light OR dark surface without per-screen changes.

// -- Light values --
const kLightBackground = Color(0xFFFBF7F2); // warm ivory / latte
const kLightCard = Color(0xFFFFFFFF); // glossy white
const kLightSecondary = Color(0xFFEAD9C7); // latte tint (borders/placeholders)
const kLightText = Color(0xFF2C1E14); // deep espresso text

// -- Dark values (professional warm-charcoal, not pure black) --
const kDarkBackground = Color(0xFF14100D);
const kDarkSurface = Color(0xFF201812);
const kDarkBorder = Color(0xFF3A2E24);
const kDarkText = Color(0xFFF0E7DD);
const kDarkPrimary = Color(0xFFD9B070); // lighter caramel for dark contrast

// -- Theme-aware (mutable) tokens. Default to light; flipped at runtime. --
Color kBackgroundColor = kLightBackground;
Color kCardColor = kLightCard;
Color kSecondaryColor = kLightSecondary;
Color kTextColor = kLightText;

/// Swaps the mutable surface/text tokens to match the active brightness.
/// Called from the app root before each frame so all screens stay in sync.
void applyThemePalette(bool isDark) {
  kBackgroundColor = isDark ? kDarkBackground : kLightBackground;
  kCardColor = isDark ? kDarkSurface : kLightCard;
  kSecondaryColor = isDark ? kDarkBorder : kLightSecondary;
  kTextColor = isDark ? kDarkText : kLightText;
}

// -- Accents (constant; read well on both light and dark) --
const kPrimaryColor = Color(0xFFA97C50); // caramel-bronze (prices, links)
const kAccentColor = Color(0xFF3A2317); // deep espresso (banners, dark buttons)
const kBlushAccent = Color(0xFFD9A7A0); // dusty rose — sparing CTA pop
const kSageAccent = Color(0xFF8FA68A); // muted sage — sparing accent
const kMetallicGold = Color(0xFFC9A24B); // metallic gold accent / dividers
const kLatte = Color(0xFFB5895A); // caramel (ombré gradient start)

/// Caramel → espresso ombré, used for hero blocks and premium promo banners.
const LinearGradient kOmbreGradient = LinearGradient(
  begin: Alignment.topLeft,
  end: Alignment.bottomRight,
  colors: [kLatte, kAccentColor],
);

/// Elegant serif family for headings (platform serif — no bundled asset
/// needed). Replaces the old ad-hoc 'cursive' styling.
const String kHeadingFont = 'serif';

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
