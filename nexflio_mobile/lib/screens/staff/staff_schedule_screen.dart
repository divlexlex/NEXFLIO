import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/appointment_model.dart';
import '../../services/api_service.dart';
import 'complete_service_sheet.dart';

/// The staff member's own assigned appointments, grouped by day, with the
/// booked -> in-service -> completed actions.
class StaffScheduleScreen extends StatefulWidget {
  const StaffScheduleScreen({super.key});

  @override
  State<StaffScheduleScreen> createState() => _StaffScheduleScreenState();
}

class _StaffScheduleScreenState extends State<StaffScheduleScreen> {
  List<AppointmentModel> _appointments = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  Future<void> _fetch() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/appointments');
      final appointments = (data as List)
          .map((e) => AppointmentModel.fromJson(e as Map<String, dynamic>))
          .toList();
      appointments
          .sort((a, b) => a.appointmentDate.compareTo(b.appointmentDate));
      setState(() {
        _appointments = appointments;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load schedule.';
        _isLoading = false;
      });
    }
  }

  Future<void> _startService(AppointmentModel appointment) async {
    try {
      await ApiService.post('/appointments/${appointment.id}/start', {});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Service started.')),
      );
      _fetch();
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Failed to start.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _completeService(AppointmentModel appointment) async {
    final completed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => CompleteServiceSheet(appointmentId: appointment.id),
    );
    if (completed == true) _fetch();
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
          "My Schedule",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
          child: CircularProgressIndicator(color: kPrimaryColor));
    }

    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!, style: const TextStyle(color: kTextColor)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _fetch,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    // Only work that needs attention: verified bookings and running services.
    final active = _appointments
        .where((a) =>
            a.status == kStatusBooked || a.status == kStatusInService)
        .toList();
    final rest = _appointments
        .where(
            (a) => a.status != kStatusBooked && a.status != kStatusInService)
        .toList()
        .reversed
        .toList();

    if (_appointments.isEmpty) {
      return const Center(
        child: Text(
          "No appointments assigned to you yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView(
        padding: const EdgeInsets.all(kDefaultPadding),
        children: [
          if (active.isNotEmpty) ...[
            const Text(
              "Up Next",
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.bold,
                color: kPrimaryColor,
              ),
            ),
            const SizedBox(height: 10),
            ...active.map(_buildCard),
            const SizedBox(height: 20),
          ],
          if (rest.isNotEmpty) ...[
            const Text(
              "History & Pending Verification",
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.bold,
                color: kPrimaryColor,
              ),
            ),
            const SizedBox(height: 10),
            ...rest.map(_buildCard),
          ],
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildCard(AppointmentModel appointment) {
    final statusColor = appointmentStatusColor(appointment.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Expanded(
                child: Text(
                  appointment.clientName,
                  style: const TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
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
          const SizedBox(height: 6),
          Text(
            appointment.serviceName ?? 'Service #${appointment.serviceId}',
            style:
                const TextStyle(color: kTextColor, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          Text(
            "${appointment.appointmentDate.toLocal().toString().split(' ').first} · ${appointment.startTime}",
            style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
          ),
          if (appointment.notes != null && appointment.notes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              appointment.notes!,
              style:
                  TextStyle(color: kTextColor.withOpacity(0.7), fontSize: 12),
            ),
          ],
          if (appointment.status == kStatusBooked) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _startService(appointment),
                icon: const Icon(Icons.play_arrow, color: Colors.white),
                label: const Text("Start Service",
                    style: TextStyle(color: Colors.white)),
                style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              ),
            ),
          ],
          if (appointment.status == kStatusInService) ...[
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () => _completeService(appointment),
                icon: const Icon(Icons.check_circle, color: Colors.white),
                label: const Text("Complete Service",
                    style: TextStyle(color: Colors.white)),
                style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
