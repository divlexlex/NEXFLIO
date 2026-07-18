import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/appointment_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../utils/page_transitions.dart';
import '../auth/login_screen.dart';
import 'book_appointment_screen.dart';

const List<String> _kMonthAbbrev = [
  'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
  'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC',
];

class BookingTab extends StatefulWidget {
  const BookingTab({super.key});

  @override
  State<BookingTab> createState() => _BookingTabState();
}

class _BookingTabState extends State<BookingTab> {
  int _selectedTabIndex = 0; // 0 for Upcoming, 1 for Past

  List<AppointmentModel> _appointments = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    AuthService.instance.addListener(_onAuthChanged);
    if (AuthService.instance.isLoggedIn) {
      _fetchAppointments();
    } else {
      _isLoading = false;
    }
  }

  @override
  void dispose() {
    AuthService.instance.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (!mounted) return;
    if (AuthService.instance.isLoggedIn) {
      _fetchAppointments();
    } else {
      setState(() {
        _appointments = [];
        _isLoading = false;
        _error = null;
      });
    }
  }

  Future<void> _fetchAppointments() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/appointments');
      final appointments = (data as List)
          .map((e) => AppointmentModel.fromJson(e as Map<String, dynamic>))
          .toList();
      appointments.sort((a, b) => b.appointmentDate.compareTo(a.appointmentDate));
      setState(() {
        _appointments = appointments;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load appointments.';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER SECTION
            Padding(
              padding: const EdgeInsets.all(kDefaultPadding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "My",
                    style: TextStyle(
                      fontSize: 24,
                      color: kTextColor,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  const Text(
                    "Appointments",
                    style: TextStyle(
                      fontSize: 28,
                      color: kAccentColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 25),

                  // TOGGLE BUTTONS (Upcoming | Past)
                  Row(
                    children: [
                      Expanded(
                        child: _buildToggleButton(
                          title: "Upcoming",
                          isSelected: _selectedTabIndex == 0,
                          onTap: () => setState(() => _selectedTabIndex = 0),
                        ),
                      ),
                      const SizedBox(width: 15),
                      Expanded(
                        child: _buildToggleButton(
                          title: "Past",
                          isSelected: _selectedTabIndex == 1,
                          onTap: () => setState(() => _selectedTabIndex = 1),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // APPOINTMENTS LIST
            Expanded(child: _buildBody()),
          ],
        ),
      ),

      // FLOATING "BOOK APPOINTMENT" BUTTON
      floatingActionButton: AuthService.instance.isLoggedIn
          ? FloatingActionButton.extended(
              onPressed: () async {
                final booked = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const BookAppointmentScreen(),
                  ),
                );
                if (booked == true) {
                  setState(() => _selectedTabIndex = 0);
                  _fetchAppointments();
                }
              },
              backgroundColor: kAccentColor,
              elevation: 4,
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text(
                "Book Appointment",
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
            )
          : null,
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }

  Widget _buildBody() {
    if (!AuthService.instance.isLoggedIn) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              "Sign in to view your appointments.",
              style: TextStyle(color: kTextColor, fontSize: 16),
            ),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () {
                Navigator.push(context, fadeSlideRoute(const LoginScreen()));
              },
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Sign In", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(color: kPrimaryColor),
      );
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, style: TextStyle(color: kTextColor)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _fetchAppointments,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    final list = _appointments
        .where((a) => a.isUpcoming == (_selectedTabIndex == 0))
        .toList();

    if (list.isEmpty) {
      return Center(
        child: Text(
          _selectedTabIndex == 0
              ? "No upcoming appointments."
              : "No past appointments.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        ...list.map((appointment) => _buildAppointmentCard(appointment)),
        const SizedBox(height: 80), // Padding for Floating Action Button
      ],
    );
  }

  // ===== HELPER WIDGETS =====

  Widget _buildToggleButton({
    required String title,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: isSelected ? kAccentColor : kCardColor,
          borderRadius: BorderRadius.circular(12),
          border: isSelected ? null : Border.all(color: kSecondaryColor),
        ),
        child: Center(
          child: Text(
            title,
            style: TextStyle(
              color: isSelected ? Colors.white : kTextColor,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildAppointmentCard(AppointmentModel appointment) {
    final isUpcoming = appointment.isUpcoming;
    final month = _kMonthAbbrev[appointment.appointmentDate.month - 1];
    final day = appointment.appointmentDate.day.toString().padLeft(2, '0');

    final statusColor = appointmentStatusColor(appointment.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kSecondaryColor),
        boxShadow: [
          BoxShadow(
            color: kTextColor.withOpacity(0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          // LEFT SIDE: DATE CALENDAR BLOCK
          Container(
            width: 80,
            padding: const EdgeInsets.symmetric(vertical: 20),
            decoration: BoxDecoration(
              color: isUpcoming
                  ? kPrimaryColor.withOpacity(0.1)
                  : kSecondaryColor.withOpacity(0.3),
              borderRadius: const BorderRadius.horizontal(
                left: Radius.circular(16),
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  month,
                  style: TextStyle(
                    color: isUpcoming
                        ? kAccentColor
                        : kTextColor.withOpacity(0.6),
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                Text(
                  day,
                  style: TextStyle(
                    color: isUpcoming
                        ? kPrimaryColor
                        : kTextColor.withOpacity(0.6),
                    fontWeight: FontWeight.bold,
                    fontSize: 28,
                  ),
                ),
              ],
            ),
          ),

          // RIGHT SIDE: DETAILS
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        appointment.startTime,
                        style: const TextStyle(
                          color: kAccentColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          appointment.statusLabel.toUpperCase(),
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    appointment.serviceName ?? 'Service #${appointment.serviceId}',
                    style: TextStyle(
                      color: kTextColor,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  if (appointment.personnelName != null)
                    Row(
                      children: [
                        const Icon(
                          Icons.person_outline,
                          size: 14,
                          color: kPrimaryColor,
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            "with ${appointment.personnelName}",
                            style: TextStyle(
                              color: kTextColor.withOpacity(0.7),
                              fontSize: 12,
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
