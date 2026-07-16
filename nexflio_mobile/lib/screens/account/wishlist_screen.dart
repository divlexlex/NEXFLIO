import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/service_model.dart';
import '../../services/api_service.dart';

class WishlistScreen extends StatefulWidget {
  const WishlistScreen({super.key});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  List<ServiceModel> _services = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchWishlist();
  }

  Future<void> _fetchWishlist() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/wishlist');
      final services = (data as List)
          .map(
            (e) => ServiceModel.fromJson(
              (e as Map<String, dynamic>)['service'] as Map<String, dynamic>,
            ),
          )
          .toList();
      setState(() {
        _services = services;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load wishlist.';
        _isLoading = false;
      });
    }
  }

  Future<void> _remove(ServiceModel service) async {
    try {
      await ApiService.delete('/wishlist/${service.id}');
      setState(() => _services.removeWhere((s) => s.id == service.id));
    } catch (e) {
      if (!mounted) return;
      final message = e is ApiException ? e.message : 'Failed to remove.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
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
          "Wishlist",
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
            Text(_error!, style: const TextStyle(color: kTextColor)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _fetchWishlist,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    if (_services.isEmpty) {
      return const Center(
        child: Text(
          "No services saved yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(kDefaultPadding),
      children: _services
          .map(
            (service) => Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: kCardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kSecondaryColor),
              ),
              child: Row(
                children: [
                  const Icon(Icons.spa, color: kAccentColor),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          service.name,
                          style: const TextStyle(
                            color: kTextColor,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          service.formattedPrice,
                          style: const TextStyle(color: kPrimaryColor),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.favorite, color: Colors.redAccent),
                    onPressed: () => _remove(service),
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }
}
