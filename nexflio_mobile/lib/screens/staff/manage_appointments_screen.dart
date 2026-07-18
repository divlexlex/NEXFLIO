import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/appointment_model.dart';
import '../../services/api_service.dart';

class ManageAppointmentsScreen extends StatefulWidget {
  const ManageAppointmentsScreen({super.key});

  @override
  State<ManageAppointmentsScreen> createState() =>
      _ManageAppointmentsScreenState();
}

class _ManageAppointmentsScreenState extends State<ManageAppointmentsScreen> {
  List<AppointmentModel> _appointments = [];
  bool _isLoading = true;
  String? _error;
  bool _pendingOnly = true;

  @override
  void initState() {
    super.initState();
    _fetchAppointments();
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
      appointments.sort(
        (a, b) => b.appointmentDate.compareTo(a.appointmentDate),
      );
      setState(() {
        _appointments = appointments;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException
            ? e.message
            : 'Failed to load appointments.';
        _isLoading = false;
      });
    }
  }

  Future<void> _updateStatus(
    AppointmentModel appointment,
    String status, {
    String? rejectionReason,
  }) async {
    try {
      await ApiService.patch('/appointments/${appointment.id}/status', {
        'status': status,
        'rejection_reason': ?rejectionReason,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Marked as ${appointmentStatusLabel(status)}.')),
      );
      _fetchAppointments();
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Failed to update status.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  Future<void> _rejectWithReason(AppointmentModel appointment) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: kCardColor,
        title: Text(
          "Reject booking",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
        content: TextField(
          controller: controller,
          maxLines: 3,
          decoration: const InputDecoration(
            hintText: "Reason (shown to the client)",
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text("Cancel", style: TextStyle(color: kTextColor)),
          ),
          ElevatedButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            child: const Text("Reject", style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );

    if (reason == null) return;
    if (reason.isEmpty) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('A rejection reason is required.')),
      );
      return;
    }
    await _updateStatus(appointment, kStatusCancelled, rejectionReason: reason);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: IconThemeData(color: kTextColor),
        title: Text(
          "Manage Appointments",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(
                horizontal: kDefaultPadding,
                vertical: 10,
              ),
              child: Row(
                children: [
                  ChoiceChip(
                    label: const Text("Pending"),
                    selected: _pendingOnly,
                    onSelected: (v) => setState(() => _pendingOnly = true),
                    selectedColor: kAccentColor,
                    labelStyle: TextStyle(
                      color: _pendingOnly ? Colors.white : kTextColor,
                    ),
                  ),
                  const SizedBox(width: 10),
                  ChoiceChip(
                    label: const Text("All"),
                    selected: !_pendingOnly,
                    onSelected: (v) => setState(() => _pendingOnly = false),
                    selectedColor: kAccentColor,
                    labelStyle: TextStyle(
                      color: !_pendingOnly ? Colors.white : kTextColor,
                    ),
                  ),
                ],
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
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

    final list = _pendingOnly
        ? _appointments.where((a) => a.status == kStatusUnverified).toList()
        : _appointments;

    if (list.isEmpty) {
      return Center(
        child: Text(
          _pendingOnly ? "No pending appointments." : "No appointments yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchAppointments,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
        children: [
          ...list.map((appointment) => _buildAppointmentCard(appointment)),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildAppointmentCard(AppointmentModel appointment) {
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
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: appointmentStatusColor(appointment.status)
                      .withOpacity(0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  appointment.statusLabel.toUpperCase(),
                  style: TextStyle(
                    color: appointmentStatusColor(appointment.status),
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
            style: TextStyle(color: kTextColor, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          Text(
            "${appointment.appointmentDate.toLocal().toString().split(' ').first} · "
            "${appointment.startTime} · with ${appointment.personnelName ?? 'Staff #${appointment.personnelId}'}",
            style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
          ),
          if (appointment.notes != null && appointment.notes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              appointment.notes!,
              style: TextStyle(color: kTextColor.withOpacity(0.7), fontSize: 12),
            ),
          ],
          if (appointment.paymentProofUrl != null) ...[
            const SizedBox(height: 10),
            GestureDetector(
              onTap: () => _showPaymentProof(appointment.paymentProofUrl!),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  appointment.paymentProofUrl!,
                  height: 120,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) => Container(
                    height: 60,
                    alignment: Alignment.center,
                    color: kSecondaryColor.withOpacity(0.3),
                    child: Text(
                      "Proof of payment unavailable",
                      style: TextStyle(color: kTextColor, fontSize: 12),
                    ),
                  ),
                ),
              ),
            ),
          ],
          if (appointment.status == kStatusUnverified) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: () => _rejectWithReason(appointment),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: Colors.redAccent),
                    ),
                    child: const Text(
                      "Reject",
                      style: TextStyle(color: Colors.redAccent),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton(
                    onPressed: () => _updateStatus(appointment, kStatusBooked),
                    style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
                    child: const Text(
                      "Approve",
                      style: TextStyle(color: Colors.white),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  void _showPaymentProof(String url) {
    showDialog(
      context: context,
      builder: (context) => Dialog(
        backgroundColor: Colors.transparent,
        child: InteractiveViewer(
          child: Image.network(url),
        ),
      ),
    );
  }
}
