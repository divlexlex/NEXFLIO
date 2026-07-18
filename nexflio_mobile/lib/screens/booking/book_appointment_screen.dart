import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import '../../utils/constants.dart';
import '../../models/service_model.dart';
import '../../models/user_model.dart';
import '../../services/api_service.dart';

class BookAppointmentScreen extends StatefulWidget {
  final ServiceModel? preselectedService;

  const BookAppointmentScreen({super.key, this.preselectedService});

  @override
  State<BookAppointmentScreen> createState() => _BookAppointmentScreenState();
}

class _BookAppointmentScreenState extends State<BookAppointmentScreen> {
  bool _isLoadingOptions = true;
  String? _loadError;

  List<ServiceModel> _services = [];
  List<UserModel> _personnel = [];

  ServiceModel? _selectedService;
  UserModel? _selectedPersonnel;
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;
  File? _paymentProofImage;
  final TextEditingController _notesController = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();

  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _loadOptions();
  }

  @override
  void dispose() {
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _loadOptions() async {
    setState(() {
      _isLoadingOptions = true;
      _loadError = null;
    });
    try {
      final results = await Future.wait([
        ApiService.get('/services'),
        ApiService.get('/personnel'),
      ]);
      final services = (results[0] as List)
          .map((e) => ServiceModel.fromJson(e as Map<String, dynamic>))
          .where((s) => s.status == 'active')
          .toList();
      final personnel = (results[1] as List)
          .map((e) => UserModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _services = services;
        _personnel = personnel;
        _isLoadingOptions = false;
        if (widget.preselectedService != null) {
          final matches = services.where(
            (s) => s.id == widget.preselectedService!.id,
          );
          _selectedService = matches.isEmpty ? null : matches.first;
        }
      });
      // Skip straight to the date/time calendar when arriving with a service
      // already chosen (e.g. tapping "Book" on a service card).
      if (_selectedService != null && mounted) {
        _pickDate();
      }
    } catch (e) {
      setState(() {
        _loadError = e is ApiException
            ? e.message
            : 'Failed to load booking options.';
        _isLoadingOptions = false;
      });
    }
  }

  Future<void> _pickDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate ?? now,
      firstDate: now,
      lastDate: now.add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _selectedDate = picked);
  }

  Future<void> _pickTime() async {
    final picked = await showTimePicker(
      context: context,
      initialTime: _selectedTime ?? TimeOfDay.now(),
    );
    if (picked != null) setState(() => _selectedTime = picked);
  }

  Future<void> _pickPaymentProof() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      backgroundColor: kCardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library, color: kPrimaryColor),
              title: Text(
                "Choose from Gallery",
                style: TextStyle(color: kTextColor),
              ),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: kPrimaryColor),
              title: Text(
                "Take a Photo",
                style: TextStyle(color: kTextColor),
              ),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
          ],
        ),
      ),
    );

    if (source == null) return;

    final picked = await _imagePicker.pickImage(source: source, imageQuality: 85);
    if (picked != null) {
      setState(() => _paymentProofImage = File(picked.path));
    }
  }

  Future<void> _submit() async {
    if (_selectedService == null ||
        _selectedPersonnel == null ||
        _selectedDate == null ||
        _selectedTime == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all fields.')),
      );
      return;
    }

    if (_paymentProofImage == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please attach proof of payment.')),
      );
      return;
    }

    setState(() => _isSubmitting = true);
    try {
      final date = _selectedDate!;
      final dateStr = '${date.year.toString().padLeft(4, '0')}-'
          '${date.month.toString().padLeft(2, '0')}-'
          '${date.day.toString().padLeft(2, '0')}';
      final time = _selectedTime!;
      final timeStr = '${time.hour.toString().padLeft(2, '0')}:'
          '${time.minute.toString().padLeft(2, '0')}:00';

      await ApiService.postMultipart(
        '/appointments',
        {
          'service_id': _selectedService!.id.toString(),
          'personnel_id': _selectedPersonnel!.id.toString(),
          'appointment_date': dateStr,
          'start_time': timeStr,
          if (_notesController.text.trim().isNotEmpty)
            'notes': _notesController.text.trim(),
        },
        filePath: _paymentProofImage!.path,
        fileFieldName: 'payment_proof',
      );

      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException
          ? e.message
          : 'Failed to book appointment.';
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
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
          "Book Appointment",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(child: _buildBody()),
    );
  }

  Widget _buildBody() {
    if (_isLoadingOptions) {
      return const Center(
        child: CircularProgressIndicator(color: kPrimaryColor),
      );
    }

    if (_loadError != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_loadError!, style: TextStyle(color: kTextColor)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _loadOptions,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text(
                "Retry",
                style: TextStyle(color: Colors.white),
              ),
            ),
          ],
        ),
      );
    }

    if (_services.isEmpty || _personnel.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(kDefaultPadding),
          child: Text(
            _services.isEmpty
                ? "No services are available to book right now."
                : "No staff are available to book right now.",
            textAlign: TextAlign.center,
            style: TextStyle(color: kTextColor, fontSize: 16),
          ),
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(kDefaultPadding),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildLabel("Service"),
          _buildDropdown<ServiceModel>(
            value: _selectedService,
            items: _services,
            itemLabel: (s) => "${s.name} — ${s.formattedPrice}",
            onChanged: (v) => setState(() => _selectedService = v),
            hint: "Select a service",
          ),
          const SizedBox(height: 20),

          _buildLabel("Staff"),
          _buildDropdown<UserModel>(
            value: _selectedPersonnel,
            items: _personnel,
            itemLabel: (p) => p.name,
            onChanged: (v) => setState(() => _selectedPersonnel = v),
            hint: "Select a staff member",
          ),
          const SizedBox(height: 20),

          _buildLabel("Date"),
          _buildPickerTile(
            text: _selectedDate == null
                ? "Select a date"
                : "${_selectedDate!.year}-"
                      "${_selectedDate!.month.toString().padLeft(2, '0')}-"
                      "${_selectedDate!.day.toString().padLeft(2, '0')}",
            icon: Icons.calendar_today,
            onTap: _pickDate,
          ),
          const SizedBox(height: 20),

          _buildLabel("Time"),
          _buildPickerTile(
            text: _selectedTime == null
                ? "Select a time"
                : _selectedTime!.format(context),
            icon: Icons.access_time,
            onTap: _pickTime,
          ),
          const SizedBox(height: 20),

          _buildLabel("Notes (optional)"),
          TextField(
            controller: _notesController,
            maxLines: 3,
            decoration: InputDecoration(
              filled: true,
              fillColor: kCardColor,
              hintText: "Anything the staff should know?",
              hintStyle: TextStyle(color: kTextColor.withOpacity(0.5)),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: kSecondaryColor),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: kSecondaryColor),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: kPrimaryColor, width: 2),
              ),
            ),
          ),
          const SizedBox(height: 20),

          _buildLabel("Proof of Payment"),
          GestureDetector(
            onTap: _pickPaymentProof,
            child: Container(
              width: double.infinity,
              height: _paymentProofImage == null ? 100 : 180,
              decoration: BoxDecoration(
                color: kCardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kSecondaryColor),
              ),
              clipBehavior: Clip.antiAlias,
              child: _paymentProofImage == null
                  ? Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.upload_file, color: kPrimaryColor, size: 28),
                        const SizedBox(height: 8),
                        Text(
                          "Attach a screenshot or photo of your payment",
                          style: TextStyle(color: kTextColor.withOpacity(0.6), fontSize: 12),
                        ),
                      ],
                    )
                  : Stack(
                      fit: StackFit.expand,
                      children: [
                        Image.file(_paymentProofImage!, fit: BoxFit.cover),
                        Positioned(
                          top: 8,
                          right: 8,
                          child: GestureDetector(
                            onTap: () => setState(() => _paymentProofImage = null),
                            child: Container(
                              padding: const EdgeInsets.all(4),
                              decoration: const BoxDecoration(
                                color: Colors.black54,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.close, color: Colors.white, size: 18),
                            ),
                          ),
                        ),
                      ],
                    ),
            ),
          ),
          const SizedBox(height: 30),

          SizedBox(
            width: double.infinity,
            height: 55,
            child: ElevatedButton(
              onPressed: _isSubmitting ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: kAccentColor,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                elevation: 0,
              ),
              child: _isSubmitting
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        color: Colors.white,
                        strokeWidth: 2.5,
                      ),
                    )
                  : const Text(
                      "Confirm Booking",
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        text,
        style: TextStyle(color: kTextColor, fontWeight: FontWeight.w500),
      ),
    );
  }

  Widget _buildDropdown<T>({
    required T? value,
    required List<T> items,
    required String Function(T) itemLabel,
    required ValueChanged<T?> onChanged,
    required String hint,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kSecondaryColor),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<T>(
          value: value,
          isExpanded: true,
          hint: Text(hint, style: TextStyle(color: kTextColor.withOpacity(0.5))),
          items: items
              .map(
                (item) => DropdownMenuItem<T>(
                  value: item,
                  child: Text(
                    itemLabel(item),
                    style: TextStyle(color: kTextColor),
                  ),
                ),
              )
              .toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }

  Widget _buildPickerTile({
    required String text,
    required IconData icon,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        decoration: BoxDecoration(
          color: kCardColor,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: kSecondaryColor),
        ),
        child: Row(
          children: [
            Icon(icon, color: kPrimaryColor, size: 20),
            const SizedBox(width: 12),
            Text(text, style: TextStyle(color: kTextColor)),
          ],
        ),
      ),
    );
  }
}
