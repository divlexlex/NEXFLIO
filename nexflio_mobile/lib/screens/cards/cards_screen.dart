import 'package:flutter/material.dart';
import '../../utils/constants.dart';

class CardsTab extends StatefulWidget {
  const CardsTab({super.key});

  @override
  State<CardsTab> createState() => _CardsTabState();
}

class _CardsTabState extends State<CardsTab> {
  int _selectedTabIndex = 0; // 0 for Membership, 1 for Gift Cards

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
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
                  const Text(
                    "Purchase a",
                    style: TextStyle(
                      fontSize: 24,
                      color: kTextColor,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  const Text(
                    "NEXFLIO Cards",
                    style: TextStyle(
                      fontSize: 28,
                      color: kAccentColor,
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
              child: _selectedTabIndex == 0
                  ? _buildMembershipList()
                  : _buildGiftCardsList(),
            ),
          ],
        ),
      ),
    );
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
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        _buildVipCard(
          title: "E L I T E",
          subtitle: "Elite Version 2.0 (New Member)",
          badgeText: "New Member",
          gradientColors: [kAccentColor, kTextColor], // Dark espresso gradient
        ),
        _buildVipCard(
          title: "E L I T E",
          subtitle: "Elite Version 2.0 (Renewal)",
          badgeText: "Renewal",
          gradientColors: [
            kPrimaryColor,
            kAccentColor,
          ], // Gold to espresso gradient
        ),
        const SizedBox(height: 20),
      ],
    );
  }

  Widget _buildGiftCardsList() {
    return const Center(
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

  Widget _buildVipCard({
    required String title,
    required String subtitle,
    required String badgeText,
    required List<Color> gradientColors,
  }) {
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
                          title.substring(0, 3), // "E L "
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
                          title.substring(3), // "I T E"
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
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
