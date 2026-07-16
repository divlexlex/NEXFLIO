import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/service_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../utils/page_transitions.dart';
import '../auth/login_screen.dart';
import '../booking/book_appointment_screen.dart';

class ServiceMenuScreen extends StatefulWidget {
  const ServiceMenuScreen({super.key});

  @override
  State<ServiceMenuScreen> createState() => _ServiceMenuScreen();
}

class _ServiceMenuScreen extends State<ServiceMenuScreen> {
  int _selectedCategoryIndex = 0;
  final List<String> _categories = ["Services", "Packages"];
  final TextEditingController _searchController = TextEditingController();

  List<ServiceModel> _services = [];
  Set<int> _wishlistedIds = {};
  bool _isLoading = true;
  String? _error;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _fetchServices();
    _fetchWishlist();
    _searchController.addListener(() {
      setState(() => _searchQuery = _searchController.text.trim().toLowerCase());
    });
  }

  Future<void> _fetchWishlist() async {
    if (!AuthService.instance.isLoggedIn) return;
    try {
      final data = await ApiService.get('/wishlist');
      final ids = (data as List)
          .map((e) => (e as Map<String, dynamic>)['service_id'] as int)
          .toSet();
      if (mounted) setState(() => _wishlistedIds = ids);
    } catch (_) {
      // Non-critical — hearts just default to unfilled.
    }
  }

  Future<void> _toggleWishlist(ServiceModel service) async {
    if (!AuthService.instance.isLoggedIn) {
      Navigator.push(context, fadeSlideRoute(const LoginScreen()));
      return;
    }
    final isWishlisted = _wishlistedIds.contains(service.id);
    setState(() {
      if (isWishlisted) {
        _wishlistedIds.remove(service.id);
      } else {
        _wishlistedIds.add(service.id);
      }
    });
    try {
      if (isWishlisted) {
        await ApiService.delete('/wishlist/${service.id}');
      } else {
        await ApiService.post('/wishlist/${service.id}', {});
      }
    } catch (_) {
      // Revert on failure.
      if (mounted) {
        setState(() {
          if (isWishlisted) {
            _wishlistedIds.add(service.id);
          } else {
            _wishlistedIds.remove(service.id);
          }
        });
      }
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchServices() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/services');
      final services = (data as List)
          .map((e) => ServiceModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _services = services;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load services.';
        _isLoading = false;
      });
    }
  }

  List<ServiceModel> get _visibleServices {
    final selectedCategory = _categories[_selectedCategoryIndex];
    Iterable<ServiceModel> filtered = _services;

    // "Services" shows everything; "Packages" narrows down by category
    // keyword, since the backend's `category` field is free-form text.
    if (selectedCategory == "Packages") {
      filtered = filtered.where(
        (s) => s.category.toLowerCase().contains("package"),
      );
    }

    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where(
        (s) =>
            s.name.toLowerCase().contains(_searchQuery) ||
            s.category.toLowerCase().contains(_searchQuery),
      );
    }

    return filtered.toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      body: Column(
        children: [
          const SizedBox(height: 10), // Padding from the top
          // SEARCH BAR
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 15),
              decoration: BoxDecoration(
                color: kCardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kSecondaryColor),
              ),
              child: TextField(
                controller: _searchController,
                decoration: const InputDecoration(
                  icon: Icon(Icons.search, color: kPrimaryColor),
                  hintText: "I'm Looking for...",
                  hintStyle: TextStyle(color: kTextColor),
                  border: InputBorder.none,
                ),
              ),
            ),
          ),
          const SizedBox(height: 15),

          // CATEGORY TOGGLES
          SizedBox(
            height: 40,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
              itemCount: _categories.length,
              itemBuilder: (context, index) {
                bool isSelected = _selectedCategoryIndex == index;
                return GestureDetector(
                  onTap: () {
                    setState(() {
                      _selectedCategoryIndex = index;
                    });
                  },
                  child: Container(
                    margin: const EdgeInsets.only(right: 10),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 20,
                      vertical: 10,
                    ),
                    decoration: BoxDecoration(
                      color: isSelected
                          ? kAccentColor
                          : kCardColor, // Espresso if selected, white if not
                      borderRadius: BorderRadius.circular(12),
                      border: isSelected
                          ? null
                          : Border.all(color: kSecondaryColor),
                    ),
                    child: Center(
                      child: Text(
                        _categories[index],
                        style: TextStyle(
                          color: isSelected ? Colors.white : kTextColor,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 20),

          // LIST OF SHOP ITEMS
          Expanded(child: _buildBody()),
        ],
      ),
    );
  }

  void _handleBook(ServiceModel service) {
    if (!AuthService.instance.isLoggedIn) {
      Navigator.push(context, fadeSlideRoute(const LoginScreen()));
      return;
    }
    Navigator.push(
      context,
      fadeSlideRoute(BookAppointmentScreen(preselectedService: service)),
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
              onPressed: _fetchServices,
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

    final visible = _visibleServices;
    if (visible.isEmpty) {
      return const Center(
        child: Text(
          "No items found.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        ...visible.map(
          (service) => _buildShopCard(
            title: service.name,
            subtitle: service.description,
            price: service.formattedPrice,
            icon: Icons.spa,
            isWishlisted: _wishlistedIds.contains(service.id),
            onBook: () => _handleBook(service),
            onToggleWishlist: () => _toggleWishlist(service),
          ),
        ),
        const SizedBox(
          height: 90,
        ), // Extra padding at the bottom so the floating basket doesn't block the last item
      ],
    );
  }

  // HELPER WIDGET FOR SHOP CARDS
  Widget _buildShopCard({
    required String title,
    required String subtitle,
    required String price,
    required IconData icon,
    required bool isWishlisted,
    required VoidCallback onBook,
    required VoidCallback onToggleWishlist,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      height: 140,
      decoration: BoxDecoration(
        color: kAccentColor, // Espresso background for the card
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        children: [
          Expanded(
            flex: 3,
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.8),
                      fontSize: 12,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const Spacer(),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: kPrimaryColor.withOpacity(0.3),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          price,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: onBook,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            "Book",
                            style: TextStyle(
                              color: kAccentColor,
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
          // RIGHT SIDE IMAGE PLACEHOLDER (Curved edge)
          Expanded(
            flex: 2,
            child: Container(
              decoration: const BoxDecoration(
                color: kSecondaryColor,
                borderRadius: BorderRadius.only(
                  topRight: Radius.circular(15),
                  bottomRight: Radius.circular(15),
                  bottomLeft: Radius.circular(
                    70,
                  ), // This creates that distinct sweeping curve from your reference
                ),
              ),
              child: Stack(
                children: [
                  Center(
                    child: Icon(
                      icon,
                      size: 60,
                      color: kAccentColor.withOpacity(0.3),
                    ),
                  ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: GestureDetector(
                      onTap: onToggleWishlist,
                      child: Icon(
                        isWishlisted ? Icons.favorite : Icons.favorite_border,
                        color: isWishlisted ? Colors.redAccent : kAccentColor,
                        size: 22,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
