import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/appointment_model.dart';
import '../../services/api_service.dart';

/// Doubles as both "My Purchases" and "Transaction History" — there's no
/// separate purchase/transaction concept in the backend yet, so both menu
/// items show the same real booking history.
class TransactionHistoryScreen extends StatefulWidget {
  const TransactionHistoryScreen({super.key});

  @override
  State<TransactionHistoryScreen> createState() =>
      _TransactionHistoryScreenState();
}

class _TransactionHistoryScreenState extends State<TransactionHistoryScreen> {
  List<AppointmentModel> _appointments = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchHistory();
  }

  Future<void> _fetchHistory() async {
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
        _error = e is ApiException ? e.message : 'Failed to load history.';
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
          "Transaction History",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(child: _buildBody()),
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
              onPressed: _fetchHistory,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    if (_appointments.isEmpty) {
      return Center(
        child: Text(
          "No transactions yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(kDefaultPadding),
      children: _appointments.map(_buildTile).toList(),
    );
  }

  Widget _buildTile(AppointmentModel appointment) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: kSecondaryColor.withOpacity(0.3),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.receipt_long, color: kPrimaryColor),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  appointment.serviceName ?? 'Service #${appointment.serviceId}',
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                Text(
                  appointment.appointmentDate.toLocal().toString().split(' ').first,
                  style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
                ),
              ],
            ),
          ),
          Text(
            appointment.statusLabel.toUpperCase(),
            style: const TextStyle(
              color: kPrimaryColor,
              fontSize: 12,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }
}
