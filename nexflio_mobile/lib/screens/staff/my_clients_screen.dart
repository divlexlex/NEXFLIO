import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/appointment_model.dart';
import '../../services/api_service.dart';

/// Staff view of the clients assigned to them. Built entirely from the
/// staff-scoped `GET /appointments` feed (the backend already filters this to
/// `personnel_id = current staff`), grouped per client.
class MyClientsScreen extends StatefulWidget {
  const MyClientsScreen({super.key});

  @override
  State<MyClientsScreen> createState() => _MyClientsScreenState();
}

class _ClientGroup {
  final String name;
  final List<AppointmentModel> appointments;

  _ClientGroup(this.name, this.appointments);

  DateTime get lastVisit => appointments
      .map((a) => a.appointmentDate)
      .reduce((a, b) => a.isAfter(b) ? a : b);

  int get upcomingCount => appointments.where((a) => a.isUpcoming).length;
}

class _MyClientsScreenState extends State<MyClientsScreen> {
  List<_ClientGroup> _clients = [];
  bool _isLoading = true;
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
      final data = await ApiService.get('/appointments');
      final appointments = (data as List)
          .map((e) => AppointmentModel.fromJson(e as Map<String, dynamic>))
          .toList();

      // Group by client (user_id when present, otherwise a walk-in name key).
      final Map<String, List<AppointmentModel>> grouped = {};
      for (final appt in appointments) {
        final key = appt.userId?.toString() ?? 'walkin:${appt.clientName}';
        grouped.putIfAbsent(key, () => []).add(appt);
      }

      final clients = grouped.entries.map((entry) {
        final list = entry.value
          ..sort((a, b) => b.appointmentDate.compareTo(a.appointmentDate));
        return _ClientGroup(list.first.clientName, list);
      }).toList()
        ..sort((a, b) => b.lastVisit.compareTo(a.lastVisit));

      setState(() {
        _clients = clients;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load clients.';
        _isLoading = false;
      });
    }
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
          "My Clients",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: kPrimaryColor))
          : _error != null
              ? _buildError()
              : _clients.isEmpty
                  ? _buildEmpty()
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(kDefaultPadding),
                        itemCount: _clients.length,
                        itemBuilder: (context, i) => _buildClientCard(_clients[i]),
                      ),
                    ),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(_error!, style: TextStyle(color: kTextColor)),
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

  Widget _buildEmpty() {
    return ListView(
      children: [
        const SizedBox(height: 120),
        Icon(Icons.people_outline, size: 64, color: kPrimaryColor.withOpacity(0.5)),
        const SizedBox(height: 12),
        Center(
          child: Text(
            "No clients assigned to you yet.",
            style: TextStyle(color: kTextColor),
          ),
        ),
      ],
    );
  }

  Widget _buildClientCard(_ClientGroup client) {
    final initials = client.name.trim().isEmpty
        ? '?'
        : client.name.trim()[0].toUpperCase();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Theme(
        // Removes the default ExpansionTile divider lines.
        data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
        child: ExpansionTile(
          tilePadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          leading: CircleAvatar(
            backgroundColor: kSecondaryColor,
            child: Text(
              initials,
              style: const TextStyle(
                color: kAccentColor,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
          title: Text(
            client.name,
            style: TextStyle(
              color: kTextColor,
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
          subtitle: Text(
            "${client.appointments.length} appointment(s) · "
            "${client.upcomingCount} upcoming",
            style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
          ),
          children:
              client.appointments.map((a) => _buildAppointmentRow(a)).toList(),
        ),
      ),
    );
  }

  Widget _buildAppointmentRow(AppointmentModel appt) {
    final date = appt.appointmentDate;
    final dateLabel = "${date.year}-${date.month.toString().padLeft(2, '0')}-"
        "${date.day.toString().padLeft(2, '0')}";

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          const Icon(Icons.spa_outlined, size: 18, color: kPrimaryColor),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  appt.serviceName ?? 'Service',
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                Text(
                  "$dateLabel · ${appt.startTime}",
                  style: TextStyle(
                    color: kTextColor.withOpacity(0.6),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(
              color: appointmentStatusColor(appt.status).withOpacity(0.15),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text(
              appt.statusLabel,
              style: TextStyle(
                color: appointmentStatusColor(appt.status),
                fontSize: 11,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
