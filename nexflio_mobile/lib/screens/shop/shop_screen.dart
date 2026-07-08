import 'package:flutter/material.dart';
import '../../utils/constants.dart';

class ShopTab extends StatefulWidget {
  const ShopTab({super.key});

  @override
  State<ShopTab> createState() => _ShopTabState();
}

class _ShopTabState extends State<ShopTab> {
  int _selectedCategoryIndex = 0;
  final List<String> _categories = ["Services", "Packages", "Products"];

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
              child: const TextField(
                decoration: InputDecoration(
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
          Expanded(
            child: ListView(
              padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
              children: [
                _buildShopCard(
                  title: "4D QuattroWave",
                  subtitle: "Experience the ultimate\nin laser hair removal...",
                  icon: Icons.auto_awesome,
                ),
                _buildShopCard(
                  title: "Diode Laser",
                  subtitle: "Experience the Gold\nStandard in laser hair...",
                  icon: Icons.spa,
                ),
                _buildShopCard(
                  title: "Facials and Peels",
                  subtitle: "Elevate your skincare\nwith personalized tre...",
                  icon: Icons.face_retouching_natural,
                ),
                const SizedBox(
                  height: 90,
                ), // Extra padding at the bottom so the floating basket doesn't block the last item
              ],
            ),
          ),
        ],
      ),

      // FLOATING "MY BASKET" BUTTON
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {},
        backgroundColor: kSecondaryColor,
        elevation: 0,
        label: Row(
          children: [
            const Text(
              "My Basket",
              style: TextStyle(
                color: kAccentColor,
                fontWeight: FontWeight.bold,
                fontSize: 18,
              ),
            ),
            const SizedBox(width: 8),
            const Icon(Icons.arrow_outward, color: kAccentColor, size: 20),
            const SizedBox(width: 20),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: kAccentColor,
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                "1",
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
    );
  }

  // HELPER WIDGET FOR SHOP CARDS
  Widget _buildShopCard({
    required String title,
    required String subtitle,
    required IconData icon,
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
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: kPrimaryColor.withOpacity(0.3),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Text(
                      "View Products",
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
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
              child: Center(
                child: Icon(
                  icon,
                  size: 60,
                  color: kAccentColor.withOpacity(0.3),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
