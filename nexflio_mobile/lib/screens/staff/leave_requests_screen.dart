import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/leave_model.dart';
import '../../services/api_service.dart';

class LeaveRequestsScreen extends StatefulWidget {
  const LeaveRequestsScreen({super.key});

  @override
  State<LeaveRequestsScreen> createState() => _LeaveRequestsScreenState();
}

class _LeaveRequestsScreenState extends State<LeaveRequestsScreen> {
  List<LeaveModel> _leaves = [];
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
      final data = await ApiService.get('/leaves');
      setState(() {
        _leaves = (data as List)
            .map((e) => LeaveModel.fromJson(e as Map<String, dynamic>))
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load leaves.';
        _isLoading = false;
      });
    }
  }

  Future<void> _openNewRequestForm() async {
    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => const _NewLeaveSheet(),
    );
    if (submitted == true) _fetch();
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'approved':
        return Colors.green;
      case 'denied':
        return Colors.redAccent;
      default:
        return Colors.orange;
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
          "Leave Requests",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _openNewRequestForm,
        backgroundColor: kAccentColor,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text(
          "Request Leave",
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
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

    if (_leaves.isEmpty) {
      return Center(
        child: Text(
          "No leave requests yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetch,
      child: ListView(
        padding: const EdgeInsets.all(kDefaultPadding),
        children: [
          ..._leaves.map(_buildCard),
          const SizedBox(height: 80),
        ],
      ),
    );
  }

  Widget _buildCard(LeaveModel leave) {
    final statusColor = _statusColor(leave.status);
    final start = leave.startDate.toLocal().toString().split(' ').first;
    final end = leave.endDate.toLocal().toString().split(' ').first;

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
              Text(
                leave.type.toUpperCase(),
                style: TextStyle(
                  color: kTextColor,
                  fontWeight: FontWeight.bold,
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
                  leave.status.toUpperCase(),
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
            start == end ? start : "$start → $end",
            style:
                TextStyle(color: kTextColor, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 4),
          Text(
            leave.reason,
            style: TextStyle(color: kTextColor.withOpacity(0.7), fontSize: 12),
          ),
          if (leave.reviewNotes != null && leave.reviewNotes!.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(
              "Manager: ${leave.reviewNotes!}",
              style: TextStyle(
                color: kPrimaryColor.withOpacity(0.9),
                fontSize: 12,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _NewLeaveSheet extends StatefulWidget {
  const _NewLeaveSheet();

  @override
  State<_NewLeaveSheet> createState() => _NewLeaveSheetState();
}

class _NewLeaveSheetState extends State<_NewLeaveSheet> {
  static const _types = ['sick', 'vacation', 'emergency', 'other'];

  DateTime? _startDate;
  DateTime? _endDate;
  String _type = 'vacation';
  final _reasonController = TextEditingController();
  bool _isSubmitting = false;

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  Future<void> _pickDate({required bool isStart}) async {
    final now = DateTime.now();
    final initial = isStart ? (_startDate ?? now) : (_endDate ?? _startDate ?? now);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (isStart) {
        _startDate = picked;
        if (_endDate != null && _endDate!.isBefore(picked)) _endDate = picked;
      } else {
        _endDate = picked;
      }
    });
  }

  Future<void> _submit() async {
    if (_startDate == null || _endDate == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please pick start and end dates.')),
      );
      return;
    }
    if (_reasonController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please provide a reason.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      await ApiService.post('/leaves', {
        'start_date': _startDate!.toIso8601String().split('T').first,
        'end_date': _endDate!.toIso8601String().split('T').first,
        'type': _type,
        'reason': _reasonController.text.trim(),
      });
      if (!mounted) return;
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Leave request submitted.')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      final message = e is ApiException ? e.message : 'Failed to submit.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final dateStyle = TextStyle(color: kTextColor.withOpacity(0.8));

    return Container(
      padding: EdgeInsets.only(
        left: kDefaultPadding,
        right: kDefaultPadding,
        top: kDefaultPadding,
        bottom: MediaQuery.of(context).viewInsets.bottom + kDefaultPadding,
      ),
      decoration: BoxDecoration(
        color: kBackgroundColor,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            "Request Leave",
            style: TextStyle(
              color: kAccentColor,
              fontSize: 20,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(isStart: true),
                  icon: const Icon(Icons.calendar_today,
                      size: 16, color: kPrimaryColor),
                  label: Text(
                    _startDate == null
                        ? "Start date"
                        : _startDate!.toIso8601String().split('T').first,
                    style: dateStyle,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickDate(isStart: false),
                  icon: const Icon(Icons.calendar_today,
                      size: 16, color: kPrimaryColor),
                  label: Text(
                    _endDate == null
                        ? "End date"
                        : _endDate!.toIso8601String().split('T').first,
                    style: dateStyle,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _type,
            decoration: const InputDecoration(labelText: "Type"),
            items: _types
                .map((t) => DropdownMenuItem(value: t, child: Text(t)))
                .toList(),
            onChanged: (value) => setState(() => _type = value ?? 'vacation'),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reasonController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: "Reason",
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 20),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2,
                      ),
                    )
                  : const Text(
                      "Submit Request",
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }
}
