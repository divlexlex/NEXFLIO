import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../utils/page_transitions.dart';
import '../../models/attendance_model.dart';
import '../../services/api_service.dart';
import 'staff_schedule_screen.dart';
import 'leave_requests_screen.dart';
import 'commission_screen.dart';

/// Hub for staff: attendance (time-in/out), break toggle, and links to
/// schedule, leaves, and commission.
class StaffDashboardScreen extends StatefulWidget {
  const StaffDashboardScreen({super.key});

  @override
  State<StaffDashboardScreen> createState() => _StaffDashboardScreenState();
}

class _StaffDashboardScreenState extends State<StaffDashboardScreen> {
  AttendanceModel? _todayAttendance;
  bool _isOnBreak = false;
  bool _isLoading = true;
  bool _isWorking = false; // guards buttons during requests
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ApiService.get('/attendance/me'),
        ApiService.get('/user'),
      ]);

      final today = DateTime.now();
      AttendanceModel? todays;
      for (final row in (results[0] as List)) {
        final attendance =
            AttendanceModel.fromJson(row as Map<String, dynamic>);
        if (attendance.workDate.year == today.year &&
            attendance.workDate.month == today.month &&
            attendance.workDate.day == today.day) {
          todays = attendance;
          break;
        }
      }

      final profile =
          (results[1] as Map<String, dynamic>)['staff_profile']
              as Map<String, dynamic>?;

      setState(() {
        _todayAttendance = todays;
        _isOnBreak = profile?['is_on_break'] == true ||
            profile?['is_on_break'] == 1;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load dashboard.';
        _isLoading = false;
      });
    }
  }

  Future<void> _timeIn() => _attendanceAction(
        () => ApiService.post('/attendance/time-in', {}),
      );

  Future<void> _timeOut() => _attendanceAction(
        () => ApiService.patch('/attendance/time-out', {}),
      );

  Future<void> _attendanceAction(Future<dynamic> Function() action) async {
    setState(() => _isWorking = true);
    try {
      await action();
      await _load();
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Request failed.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _isWorking = false);
    }
  }

  Future<void> _toggleBreak(bool value) async {
    setState(() => _isWorking = true);
    try {
      final response =
          await ApiService.patch('/staff/break', {'on_break': value});
      if (!mounted) return;
      setState(() =>
          _isOnBreak = (response as Map<String, dynamic>)['is_on_break'] == true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response['message'] as String? ?? 'Updated.')),
      );
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Request failed.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _isWorking = false);
    }
  }

  String _formatTime(DateTime time) {
    final local = TimeOfDay.fromDateTime(time.toLocal());
    final hour = local.hourOfPeriod == 0 ? 12 : local.hourOfPeriod;
    final minute = local.minute.toString().padLeft(2, '0');
    final period = local.period == DayPeriod.am ? 'AM' : 'PM';
    return '$hour:$minute $period';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: kTextColor),
        title: const Text(
          "Staff Dashboard",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: kPrimaryColor))
          : _error != null
              ? _buildError()
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(kDefaultPadding),
                    children: [
                      _buildAttendanceCard(),
                      const SizedBox(height: 15),
                      _buildBreakCard(),
                      const SizedBox(height: 25),
                      _buildNavTile(
                        icon: Icons.calendar_month,
                        title: "My Schedule",
                        subtitle: "Assigned appointments and status updates",
                        screen: const StaffScheduleScreen(),
                      ),
                      _buildNavTile(
                        icon: Icons.beach_access,
                        title: "Leave Requests",
                        subtitle: "Request and track your leaves",
                        screen: const LeaveRequestsScreen(),
                      ),
                      _buildNavTile(
                        icon: Icons.payments_outlined,
                        title: "My Commission",
                        subtitle: "Base pay + 10% per completed service",
                        screen: const CommissionScreen(),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(_error!, style: const TextStyle(color: kTextColor)),
          const SizedBox(height: 12),
          ElevatedButton(
            onPressed: _load,
            style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
            child: const Text("Retry", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }

  Widget _buildAttendanceCard() {
    final attendance = _todayAttendance;

    final String statusText;
    if (attendance == null) {
      statusText = "Not timed in yet";
    } else if (attendance.isOpen) {
      statusText = "Timed in at ${_formatTime(attendance.timeIn)}";
    } else {
      statusText =
          "${_formatTime(attendance.timeIn)} - ${_formatTime(attendance.timeOut!)}";
    }

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            "Today's Attendance",
            style: TextStyle(
              color: kPrimaryColor,
              fontWeight: FontWeight.bold,
              fontSize: 14,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            statusText,
            style: const TextStyle(
              color: kTextColor,
              fontWeight: FontWeight.w600,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: attendance == null
                ? ElevatedButton.icon(
                    onPressed: _isWorking ? null : _timeIn,
                    icon: const Icon(Icons.login, color: Colors.white),
                    label: const Text("Time In",
                        style: TextStyle(color: Colors.white)),
                    style:
                        ElevatedButton.styleFrom(backgroundColor: kAccentColor),
                  )
                : attendance.isOpen
                    ? OutlinedButton.icon(
                        onPressed: _isWorking ? null : _timeOut,
                        icon: const Icon(Icons.logout, color: kAccentColor),
                        label: const Text("Time Out",
                            style: TextStyle(color: kAccentColor)),
                        style: OutlinedButton.styleFrom(
                          side: const BorderSide(color: kAccentColor),
                        ),
                      )
                    : const Center(
                        child: Text(
                          "Shift complete — see you tomorrow!",
                          style: TextStyle(color: kPrimaryColor),
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildBreakCard() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 6),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kSecondaryColor),
      ),
      child: SwitchListTile(
        contentPadding: EdgeInsets.zero,
        value: _isOnBreak,
        onChanged: _isWorking ? null : _toggleBreak,
        activeThumbColor: kAccentColor,
        title: const Text(
          "On Break",
          style: TextStyle(
            color: kTextColor,
            fontWeight: FontWeight.w600,
          ),
        ),
        subtitle: Text(
          _isOnBreak
              ? "You are hidden from the booking pool."
              : "Clients can book you.",
          style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
        ),
      ),
    );
  }

  Widget _buildNavTile({
    required IconData icon,
    required String title,
    required String subtitle,
    required Widget screen,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kSecondaryColor.withOpacity(0.5)),
      ),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: kSecondaryColor.withOpacity(0.3),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: kAccentColor, size: 20),
        ),
        title: Text(
          title,
          style: const TextStyle(
            color: kTextColor,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
        ),
        subtitle: Text(
          subtitle,
          style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
        ),
        trailing: const Icon(Icons.chevron_right, color: kPrimaryColor),
        onTap: () => Navigator.push(context, fadeSlideRoute(screen)),
      ),
    );
  }
}
