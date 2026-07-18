import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/commission_model.dart';
import '../../services/api_service.dart';

/// Real-time pay view: fixed monthly base pay plus the month's commission
/// ledger (one entry per completed service).
class CommissionScreen extends StatefulWidget {
  const CommissionScreen({super.key});

  @override
  State<CommissionScreen> createState() => _CommissionScreenState();
}

class _CommissionScreenState extends State<CommissionScreen> {
  DateTime _month = DateTime(DateTime.now().year, DateTime.now().month);
  CommissionSummaryModel? _summary;
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetch();
  }

  String get _monthParam =>
      "${_month.year.toString().padLeft(4, '0')}-${_month.month.toString().padLeft(2, '0')}";

  Future<void> _fetch() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/my-commissions?month=$_monthParam');
      setState(() {
        _summary =
            CommissionSummaryModel.fromJson(data as Map<String, dynamic>);
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load commissions.';
        _isLoading = false;
      });
    }
  }

  void _changeMonth(int delta) {
    setState(() {
      _month = DateTime(_month.year, _month.month + delta);
    });
    _fetch();
  }

  static const _monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
  ];

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final isCurrentMonth =
        _month.year == now.year && _month.month == now.month;

    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: IconThemeData(color: kTextColor),
        title: Text(
          "My Commission",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: kDefaultPadding,
              vertical: 8,
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                IconButton(
                  onPressed: () => _changeMonth(-1),
                  icon: const Icon(Icons.chevron_left, color: kPrimaryColor),
                ),
                Text(
                  "${_monthNames[_month.month - 1]} ${_month.year}",
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
                IconButton(
                  onPressed: isCurrentMonth ? null : () => _changeMonth(1),
                  icon: Icon(
                    Icons.chevron_right,
                    color: isCurrentMonth
                        ? kSecondaryColor
                        : kPrimaryColor,
                  ),
                ),
              ],
            ),
          ),
          Expanded(child: _buildBody()),
        ],
      ),
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
            Text(_error!, style: TextStyle(color: kTextColor)),
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

    final summary = _summary!;

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView(
        padding: const EdgeInsets.all(kDefaultPadding),
        children: [
          _buildTotalsCard(summary),
          const SizedBox(height: 20),
          Text(
            "Earned this month (${summary.commissionRate}% per service)",
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.bold,
              color: kPrimaryColor,
            ),
          ),
          const SizedBox(height: 10),
          if (summary.entries.isEmpty)
            Padding(
              padding: const EdgeInsets.all(20),
              child: Center(
                child: Text(
                  "No completed services this month yet.",
                  style: TextStyle(color: kTextColor.withOpacity(0.6)),
                ),
              ),
            )
          else
            ...summary.entries.map(_buildEntry),
          const SizedBox(height: 20),
        ],
      ),
    );
  }

  Widget _buildTotalsCard(CommissionSummaryModel summary) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: kAccentColor,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            "Estimated Pay",
            style: TextStyle(color: Colors.white70, fontSize: 13),
          ),
          const SizedBox(height: 4),
          Text(
            "₱${summary.grandTotal}",
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: _buildTotalItem("Base Pay", "₱${summary.basePay}"),
              ),
              Expanded(
                child: _buildTotalItem(
                  "Commission",
                  "₱${summary.commissionTotal}",
                ),
              ),
              Expanded(
                child: _buildTotalItem(
                  "Services",
                  summary.entries.length.toString(),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildTotalItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(color: Colors.white70, fontSize: 11)),
        const SizedBox(height: 2),
        Text(
          value,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 15,
          ),
        ),
      ],
    );
  }

  Widget _buildEntry(CommissionEntryModel entry) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  entry.serviceName ?? "Service",
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  "${entry.earnedAt.toLocal().toString().split(' ').first} · ₱${entry.servicePrice} @ ${entry.rate}%",
                  style: TextStyle(
                    color: kTextColor.withOpacity(0.5),
                    fontSize: 11,
                  ),
                ),
              ],
            ),
          ),
          Text(
            "+₱${entry.amount}",
            style: const TextStyle(
              color: Colors.green,
              fontWeight: FontWeight.bold,
              fontSize: 15,
            ),
          ),
        ],
      ),
    );
  }
}
