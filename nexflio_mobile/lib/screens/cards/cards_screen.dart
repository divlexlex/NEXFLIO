import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/membership_plan_model.dart';
import '../../models/gift_card_model.dart';
import '../../services/api_service.dart';

class CardsTab extends StatefulWidget {
  const CardsTab({super.key});

  @override
  State<CardsTab> createState() => _CardsTabState();
}

class _CardsTabState extends State<CardsTab> {
  int _selectedTabIndex = 0; // 0 for Membership, 1 for Gift Cards

  List<MembershipPlanModel> _plans = [];
  List<GiftCardModel> _giftCards = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchData();
  }

  Future<void> _fetchData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final results = await Future.wait([
        ApiService.get('/membership-plans'),
        ApiService.get('/gift-cards'),
      ]);
      final plans = (results[0] as List)
          .map((e) => MembershipPlanModel.fromJson(e as Map<String, dynamic>))
          .toList();
      final giftCards = (results[1] as List)
          .map((e) => GiftCardModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _plans = plans;
        _giftCards = giftCards;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load cards.';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HEADER SECTION
            Padding(
              padding: const EdgeInsets.all(kDefaultPadding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    "Purchase a",
                    style: TextStyle(
                      fontSize: 24,
                      color: Theme.of(context).colorScheme.onSurface,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  Text(
                    "NEXFLIO Cards",
                    style: TextStyle(
                      fontSize: 28,
                      color: Theme.of(context).colorScheme.onSurface,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 25),

                  // TOGGLE BUTTONS (Membership | Gift Cards)
                  Row(
                    children: [
                      Expanded(
                        child: _buildToggleButton(
                          title: "Membership",
                          isSelected: _selectedTabIndex == 0,
                          onTap: () => setState(() => _selectedTabIndex = 0),
                        ),
                      ),
                      const SizedBox(width: 15),
                      Expanded(
                        child: _buildToggleButton(
                          title: "Gift Cards",
                          isSelected: _selectedTabIndex == 1,
                          onTap: () => setState(() => _selectedTabIndex = 1),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // CARD LIST CONTENT
            Expanded(
              child: RefreshIndicator(
                onRefresh: _fetchData,
                color: kPrimaryColor,
                child: _buildBody(),
              ),
            ),
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
              onPressed: _fetchData,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    return _selectedTabIndex == 0 ? _buildMembershipList() : _buildGiftCardsList();
  }

  // ===== HELPER WIDGETS =====

  Widget _buildToggleButton({
    required String title,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          color: isSelected ? kAccentColor : kCardColor,
          borderRadius: BorderRadius.circular(12),
          border: isSelected ? null : Border.all(color: kSecondaryColor),
        ),
        child: Center(
          child: Text(
            title,
            style: TextStyle(
              color: isSelected ? Colors.white : kTextColor,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildMembershipList() {
    if (_plans.isEmpty) {
      return Center(
        child: Text(
          "No membership plans available right now.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        ...List.generate(_plans.length, (index) {
          final plan = _plans[index];
          return _buildVipCard(
            title: plan.name.toUpperCase(),
            subtitle: "${plan.description ?? plan.name} — ${plan.formattedPrice}",
            badgeText: plan.badgeText,
            gradientColors: index.isEven
                ? [kAccentColor, const Color(0xFF6F4A2F)]
                : [kPrimaryColor, kAccentColor],
          );
        }),
        const SizedBox(height: 20),
      ],
    );
  }

  Widget _buildGiftCardsList() {
    if (_giftCards.isEmpty) {
      return Center(
        child: Text(
          "Gift Cards coming soon...",
          style: TextStyle(
            color: kTextColor,
            fontSize: 16,
            fontStyle: FontStyle.italic,
          ),
        ),
      );
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        ..._giftCards.map(
          (card) => Container(
            margin: const EdgeInsets.only(bottom: 15),
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: kCardColor,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: kSecondaryColor),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: kSecondaryColor.withOpacity(0.3),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.card_giftcard, color: kPrimaryColor),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        card.name,
                        style: TextStyle(
                          color: kTextColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                      if (card.description != null)
                        Text(
                          card.description!,
                          style: TextStyle(
                            color: kTextColor.withOpacity(0.6),
                            fontSize: 12,
                          ),
                        ),
                    ],
                  ),
                ),
                Text(
                  card.formattedAmount,
                  style: const TextStyle(
                    color: kPrimaryColor,
                    fontWeight: FontWeight.bold,
                    fontSize: 16,
                  ),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 20),
      ],
    );
  }

  Widget _buildVipCard({
    required String title,
    required String subtitle,
    required String badgeText,
    required List<Color> gradientColors,
  }) {
    final cleanTitle = title.replaceAll(' ', '');
    final midpoint = (cleanTitle.length / 2).ceil().clamp(1, cleanTitle.length - 1);

    return Container(
      margin: const EdgeInsets.only(bottom: 20),
      height: 220, // Taller size to match the premium feel in the reference
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: gradientColors,
        ),
        boxShadow: [
          BoxShadow(
            color: gradientColors.last.withOpacity(0.3),
            blurRadius: 12,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Background decorative icon (simulating the DNA strand from your reference)
          Positioned(
            right: -20,
            bottom: -20,
            child: Icon(
              Icons.all_inclusive,
              size: 150,
              color: Colors.white.withOpacity(0.05),
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // MAIN TITLE / LOGO
                Expanded(
                  child: Center(
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          cleanTitle.substring(0, midpoint),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 40,
                            fontWeight: FontWeight.w300,
                            letterSpacing: 8,
                          ),
                        ),
                        const Padding(
                          padding: EdgeInsets.symmetric(horizontal: 12.0),
                          child: Icon(
                            Icons.spa,
                            color: Colors.white,
                            size: 45,
                          ), // Replaces the DNA icon in the center
                        ),
                        Text(
                          cleanTitle.substring(midpoint),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 40,
                            fontWeight: FontWeight.w300,
                            letterSpacing: 8,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

                // BADGE
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 12,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: kSecondaryColor.withOpacity(0.3),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(color: Colors.white24),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.person_outline,
                        color: Colors.white,
                        size: 14,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        badgeText,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),

                // SUBTITLE
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
