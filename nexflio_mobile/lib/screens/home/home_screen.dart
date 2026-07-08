import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../auth/login_screen.dart'; // Import your login screen
import '../shop/shop_screen.dart'; // Add this line
import '../cards/cards_screen.dart';
import '../booking/booking_screen.dart';
import '../account/account_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      body: SafeArea(
        // IndexedStack keeps all pages in memory so they don't reload when switching tabs
        child: IndexedStack(
          index: _selectedIndex,
          children: [
            _buildHomeTab(context), // 0: Home Tab
            const ShopTab(), // 1: Shop (Replaced placeholder!)
            const Center(
              child: Text(
                "Booking Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ),
            const Center(
              child: Text(
                "Shop Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ), // 1: Shop
            const Center(
              child: Text(
                "Booking Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ), // 2: Book
            const Center(
              child: Text(
                "Cards Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ), // 3: Cards
            const Center(
              child: Text(
                "Account Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ), // 4: Account
            const BookingTab(),
            const CardsTab(),
            const AccountTab(), // 3: Cards (Replaced placeholder!)
            const Center(
              child: Text(
                "Account Module Coming Soon",
                style: TextStyle(color: kTextColor, fontSize: 20),
              ),
            ), // 4: Account
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        backgroundColor: Colors.white,
        selectedItemColor: kAccentColor,
        unselectedItemColor: kTextColor.withOpacity(0.4),
        currentIndex: _selectedIndex,
        onTap: (index) => setState(() => _selectedIndex = index),
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: "Home"),
          BottomNavigationBarItem(
            icon: Icon(Icons.shopping_bag),
            label: "Shop",
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.calendar_today),
            label: "Book",
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.credit_card),
            label: "Cards",
          ),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: "Account"),
        ],
      ),
    );
  }

  // ==========================================
  // ===== TAB 1: MAIN HOME UI CONTENT ========
  // ==========================================
  Widget _buildHomeTab(BuildContext context) {
    return SingleChildScrollView(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ===== HERO SECTION (header + tagline + search) =====
          // Light/cream na hero, hindi na dark gradient, para elegant spa
          // look ala "Perfect Spa" reference (cream bg, espresso text, thin
          // gold divider).
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(
              kDefaultPadding,
              kDefaultPadding,
              kDefaultPadding,
              30,
            ),
            decoration: const BoxDecoration(
              color: kBackgroundColor,
              border: Border(
                bottom: BorderSide(color: kSecondaryColor, width: 1.5),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // HEADER
                Row(
                  children: [
                    CircleAvatar(
                      radius: 25,
                      backgroundColor: kSecondaryColor,
                      child: const Icon(
                        Icons.person,
                        color: kAccentColor,
                        size: 30,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            "Guest Account",
                            style: TextStyle(
                              color: kTextColor,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          // ===== SIGN IN BUTTON ROUTING =====
                          GestureDetector(
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (context) => const LoginScreen(),
                                ),
                              );
                            },
                            child: const Text(
                              "Sign In",
                              style: TextStyle(
                                color: kPrimaryColor,
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                decoration: TextDecoration
                                    .underline, // Added underline so it looks clickable
                                decorationColor: kPrimaryColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    _circleIconButton(Icons.notifications_none, () {}),
                    const SizedBox(width: 10),
                    _circleIconButton(
                      Icons.shopping_bag_outlined,
                      () {},
                      badgeCount: 1,
                    ),
                  ],
                ),
                const SizedBox(height: 25),

                // TAGLINE
                const Text(
                  "Your best skin",
                  style: TextStyle(
                    color: kAccentColor,
                    fontSize: 32,
                    fontFamily: 'cursive',
                    fontStyle: FontStyle.italic,
                    height: 1.1,
                  ),
                ),
                const Text(
                  "CREATED BY SCIENCE",
                  style: TextStyle(
                    color: kTextColor,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 2,
                  ),
                ),
                const SizedBox(height: 10),

                // thin decorative divider (parang sa reference, may lotus/line accents)
                Row(
                  children: [
                    Container(width: 40, height: 1, color: kPrimaryColor),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 8),
                      child: Icon(
                        Icons.spa_outlined,
                        size: 16,
                        color: kPrimaryColor,
                      ),
                    ),
                    Container(width: 40, height: 1, color: kPrimaryColor),
                  ],
                ),
                const SizedBox(height: 20),

                // SEARCH BAR
                Container(
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
              ],
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(kDefaultPadding),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ===== RECOMMENDED =====
                _sectionHeader("Recommended"),
                const SizedBox(height: 15),
                SizedBox(
                  height: 200,
                  child: ListView(
                    scrollDirection: Axis.horizontal,
                    children: [
                      _buildRecommendedCard(
                        Icons.spa,
                        "Diode Underarm",
                        "₱800.00",
                      ),
                      _buildRecommendedCard(
                        Icons.face_retouching_natural,
                        "Hyal-C Facial",
                        "₱600.00",
                      ),
                      _buildRecommendedCard(
                        Icons.auto_awesome,
                        "4D QuattroWave",
                        "₱13,400.00",
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),

                // ===== PROMOS =====
                // Dark espresso banner block, gaya ng "PERFECT PACKAGE" strip
                // sa reference — white text sa dark brown background.
                _sectionHeader("Promos"),
                const SizedBox(height: 15),
                Container(
                  width: double.infinity,
                  height: 130,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: kAccentColor,
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: Stack(
                    children: [
                      Positioned(
                        right: -10,
                        top: -10,
                        child: Icon(
                          Icons.spa,
                          size: 90,
                          color: Colors.white.withOpacity(0.12),
                        ),
                      ),
                      const Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          "4D QuattroWave\n₱13,400.00",
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),

                // ===== BLOGS AND ARTICLES =====
                _sectionHeader("Blogs and Articles"),
                const SizedBox(height: 15),
                Column(
                  children: List.generate(3, (index) => _buildArticleCard()),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ==========================================
  // ===== HELPER WIDGETS =====================
  // ==========================================

  Widget _sectionHeader(String title) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.bold,
            color: kTextColor,
          ),
        ),
        const Text(
          "See all",
          style: TextStyle(color: kPrimaryColor, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }

  Widget _circleIconButton(
    IconData icon,
    VoidCallback onTap, {
    int badgeCount = 0,
  }) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        CircleAvatar(
          radius: 20,
          backgroundColor: kSecondaryColor,
          child: Icon(icon, color: kAccentColor, size: 20),
        ),
        if (badgeCount > 0)
          Positioned(
            right: -2,
            top: -2,
            child: Container(
              padding: const EdgeInsets.all(4),
              decoration: const BoxDecoration(
                color: kAccentColor,
                shape: BoxShape.circle,
              ),
              child: Text(
                "$badgeCount",
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildRecommendedCard(IconData icon, String title, String price) {
    return Container(
      width: 160,
      margin: const EdgeInsets.only(right: 15),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: kSecondaryColor),
        boxShadow: [
          BoxShadow(
            color: kTextColor.withOpacity(0.05),
            blurRadius: 8,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            height: 120,
            width: double.infinity,
            decoration: const BoxDecoration(
              color: kSecondaryColor,
              borderRadius: BorderRadius.vertical(top: Radius.circular(15)),
            ),
            child: Center(child: Icon(icon, color: kAccentColor, size: 40)),
          ),
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    color: kTextColor,
                  ),
                ),
                Text(
                  price,
                  style: const TextStyle(
                    color: kPrimaryColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildArticleCard() {
    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      height: 110,
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: kSecondaryColor),
      ),
      child: Row(
        children: [
          Container(
            width: 110,
            height: double.infinity,
            decoration: const BoxDecoration(
              color: kAccentColor,
              borderRadius: BorderRadius.horizontal(left: Radius.circular(15)),
            ),
            child: const Center(
              child: Icon(Icons.spa_outlined, color: Colors.white, size: 40),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(12.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: kSecondaryColor,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(Icons.menu_book, size: 12, color: kAccentColor),
                          SizedBox(width: 4),
                          Text(
                            "Articles",
                            style: TextStyle(
                              fontSize: 11,
                              color: kAccentColor,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const Text(
                    "Coming Soon!",
                    style: TextStyle(
                      color: kTextColor,
                      fontWeight: FontWeight.w600,
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
