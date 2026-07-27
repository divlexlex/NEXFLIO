import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../utils/theme.dart';
import '../../utils/page_transitions.dart';
import '../../models/service_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../widgets/app_network_image.dart';
import '../auth/login_screen.dart';
import '../booking/book_appointment_screen.dart';

/// Detail page for a service or promo. Fetches the full record by
/// type + id; a preloaded title/price/image paints instantly while loading.
class CatalogDetailScreen extends StatefulWidget {
  final String type; // 'service' | 'promo'
  final int id;
  final String? initialTitle;
  final String? initialImageUrl;
  final double? initialPrice;
  final String? initialSubtitle;

  const CatalogDetailScreen({
    super.key,
    required this.type,
    required this.id,
    this.initialTitle,
    this.initialImageUrl,
    this.initialPrice,
    this.initialSubtitle,
  });

  @override
  State<CatalogDetailScreen> createState() => _CatalogDetailScreenState();
}

class _CatalogDetailScreenState extends State<CatalogDetailScreen> {
  Map<String, dynamic>? _data;
  bool _isLoading = true;
  String? _error;

  String get _endpoint => '/${widget.type}s/${widget.id}';

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
      final data = await ApiService.get(_endpoint);
      setState(() {
        _data = data as Map<String, dynamic>;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load details.';
        _isLoading = false;
      });
    }
  }

  String get _title =>
      (_data?['name'] ?? _data?['title'] ?? widget.initialTitle ?? '') as String;

  String? get _imageUrl =>
      (_data?['image_url'] as String?) ?? widget.initialImageUrl;

  double get _price => _data != null
      ? double.tryParse(_data!['price'].toString()) ?? (widget.initialPrice ?? 0)
      : (widget.initialPrice ?? 0);

  String? get _category {
    if (widget.type == 'promo') return 'Limited offer';
    return (_data?['category'] as String?) ?? widget.initialSubtitle;
  }

  String? get _description => _data?['description'] as String?;

  String? get _meta {
    if (_data == null) return null;
    switch (widget.type) {
      case 'service':
        return _data!['duration_minutes'] != null
            ? '${_data!['duration_minutes']} mins'
            : null;
      case 'promo':
        final svc = _data!['service'] as Map<String, dynamic>?;
        return svc != null ? 'Includes: ${svc['name']}' : null;
    }
    return null;
  }

  /// The bookable service for this item, if any (services directly, or a
  /// promo's linked service).
  ServiceModel? get _bookableService {
    if (_data == null) return null;
    if (widget.type == 'service') {
      return ServiceModel.fromJson(_data!);
    }
    if (widget.type == 'promo' && _data!['service'] is Map) {
      return ServiceModel.fromJson(_data!['service'] as Map<String, dynamic>);
    }
    return null;
  }

  void _book() {
    final service = _bookableService;
    if (service == null) return;
    if (!AuthService.instance.isLoggedIn) {
      Navigator.push(context, fadeSlideRoute(const LoginScreen()));
      return;
    }
    Navigator.push(
      context,
      fadeSlideRoute(BookAppointmentScreen(preselectedService: service)),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: context.appBg,
      appBar: AppBar(title: Text(_title.isEmpty ? 'Details' : _title)),
      body: _error != null ? _buildError() : _buildContent(),
      bottomNavigationBar: _buildCta(),
    );
  }

  Widget _buildError() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(_error!, style: TextStyle(color: context.appText)),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: _load, child: const Text('Retry')),
        ],
      ),
    );
  }

  Widget _buildContent() {
    return RefreshIndicator(
      onRefresh: _load,
      color: kPrimaryColor,
      child: ListView(
        children: [
          // HERO IMAGE
          AspectRatio(
            aspectRatio: 16 / 10,
            child: AppNetworkImage(url: _imageUrl, fit: BoxFit.cover),
          ),
          Padding(
            padding: const EdgeInsets.all(kDefaultPadding),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (_category != null && _category!.isNotEmpty)
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: kBlushAccent.withOpacity(0.3),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      _category!,
                      style: const TextStyle(
                        color: kAccentColor,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                const SizedBox(height: 12),
                Text(
                  _title,
                  style: TextStyle(
                    color: context.appText,
                    fontSize: 24,
                    fontFamily: kHeadingFont,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  '₱${_price.toStringAsFixed(2)}',
                  style: const TextStyle(
                    color: kPrimaryColor,
                    fontSize: 22,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                if (_meta != null) ...[
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Icon(Icons.info_outline, size: 16, color: context.appSubtle),
                      const SizedBox(width: 6),
                      Text(_meta!, style: TextStyle(color: context.appSubtle)),
                    ],
                  ),
                ],
                if (_isLoading) ...[
                  const SizedBox(height: 20),
                  const Center(
                    child: CircularProgressIndicator(color: kPrimaryColor),
                  ),
                ],
                if (_description != null && _description!.isNotEmpty) ...[
                  const SizedBox(height: 18),
                  Text(
                    _description!,
                    style: TextStyle(
                      color: context.appText.withOpacity(0.85),
                      height: 1.5,
                    ),
                  ),
                ],
                const SizedBox(height: 20),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget? _buildCta() {
    if (_isLoading || _bookableService == null) return null;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(kDefaultPadding),
        child: SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton.icon(
            onPressed: _book,
            icon: const Icon(Icons.calendar_today, size: 18),
            label: const Text('Book Now'),
          ),
        ),
      ),
    );
  }
}
