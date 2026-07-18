import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/inventory_option_model.dart';
import '../../services/api_service.dart';

/// Bottom sheet shown when completing a service: pick the consumables used
/// (whole units only) so stock is deducted FIFO on the backend.
class CompleteServiceSheet extends StatefulWidget {
  final int appointmentId;

  const CompleteServiceSheet({super.key, required this.appointmentId});

  @override
  State<CompleteServiceSheet> createState() => _CompleteServiceSheetState();
}

class _CompleteServiceSheetState extends State<CompleteServiceSheet> {
  List<InventoryOptionModel> _options = [];
  final Map<int, int> _selected = {}; // inventory_id -> quantity
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  Future<void> _loadOptions() async {
    try {
      final data = await ApiService.get('/inventory/options');
      setState(() {
        _options = (data as List)
            .map((e) =>
                InventoryOptionModel.fromJson(e as Map<String, dynamic>))
            .toList();
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load inventory.';
        _isLoading = false;
      });
    }
  }

  void _changeQuantity(InventoryOptionModel option, int delta) {
    setState(() {
      final current = _selected[option.id] ?? 0;
      final next = (current + delta).clamp(0, option.quantity);
      if (next == 0) {
        _selected.remove(option.id);
      } else {
        _selected[option.id] = next;
      }
    });
  }

  Future<void> _submit() async {
    setState(() => _isSubmitting = true);
    try {
      await ApiService.post('/appointments/${widget.appointmentId}/complete', {
        'items': _selected.entries
            .map((e) => {'inventory_id': e.key, 'quantity': e.value})
            .toList(),
      });
      if (!mounted) return;
      Navigator.pop(context, true);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Service completed.')),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      final message = e is ApiException ? e.message : 'Failed to complete.';
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
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
            "Complete Service",
            style: TextStyle(
              color: kAccentColor,
              fontSize: 20,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            "Log the products used (per whole unit/bottle). Leave empty if none.",
            style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
          ),
          const SizedBox(height: 16),
          Flexible(child: _buildList()),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
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
                      "Mark as Completed",
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

  Widget _buildList() {
    if (_isLoading) {
      return const Padding(
        padding: EdgeInsets.all(30),
        child: Center(child: CircularProgressIndicator(color: kPrimaryColor)),
      );
    }

    if (_error != null) {
      return Padding(
        padding: const EdgeInsets.all(20),
        child: Text(_error!, style: TextStyle(color: kTextColor)),
      );
    }

    if (_options.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(20),
        child: Text(
          "No stock available — you can still complete the service.",
          style: TextStyle(color: kTextColor.withOpacity(0.6)),
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      itemCount: _options.length,
      itemBuilder: (context, index) {
        final option = _options[index];
        final quantity = _selected[option.id] ?? 0;

        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          decoration: BoxDecoration(
            color: kCardColor,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: quantity > 0 ? kPrimaryColor : kSecondaryColor,
            ),
          ),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      option.itemName,
                      style: TextStyle(
                        color: kTextColor,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Text(
                      "${option.quantity} ${option.unit}(s) in stock",
                      style: TextStyle(
                        color: kTextColor.withOpacity(0.5),
                        fontSize: 11,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                onPressed:
                    quantity > 0 ? () => _changeQuantity(option, -1) : null,
                icon: const Icon(Icons.remove_circle_outline),
                color: kAccentColor,
              ),
              Text(
                '$quantity',
                style: TextStyle(
                  color: kTextColor,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              IconButton(
                onPressed: quantity < option.quantity
                    ? () => _changeQuantity(option, 1)
                    : null,
                icon: const Icon(Icons.add_circle_outline),
                color: kAccentColor,
              ),
            ],
          ),
        );
      },
    );
  }
}
