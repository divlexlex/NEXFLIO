import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/service_model.dart';
import '../../models/promo_model.dart';
import '../../models/article_model.dart';
import '../../services/api_service.dart';
import '../../services/auth_service.dart';
import '../../utils/page_transitions.dart';
import '../auth/login_screen.dart'; // Import your login screen
import '../shop/shop_screen.dart'; // Add this line
import '../cards/cards_screen.dart';
import '../booking/booking_screen.dart';
import '../account/account_screen.dart';
import '../account/wishlist_screen.dart';
import 'notifications_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;
  List<ServiceModel> _recommendedServices = [];
  List<PromoModel> _promos = [];
  List<ArticleModel> _articles = [];
  bool _isLoadingHomeContent = true;
  int _wishlistCount = 0;
  int _unreadNotificationCount = 0;

  @override
  void initState() {
    super.initState();
    AuthService.instance.addListener(_onAuthChanged);
    _fetchHomeContent();
    _fetchBadgeCounts();
  }

  Future<void> _fetchBadgeCounts() async {
    if (!AuthService.instance.isLoggedIn) {
      setState(() {
        _wishlistCount = 0;
        _unreadNotificationCount = 0;
      });
      return;
    }
    try {
      final results = await Future.wait([
        ApiService.get('/wishlist'),
        ApiService.get('/notifications'),
      ]);
      final wishlist = results[0] as List;
      final notifications = results[1] as List;
      final unread = notifications
          .where((n) => (n as Map<String, dynamic>)['read_at'] == null)
          .length;
      if (mounted) {
        setState(() {
          _wishlistCount = wishlist.length;
          _unreadNotificationCount = unread;
        });
      }
    } catch (_) {
      // Non-critical — badges just stay at their previous values.
    }
  }

  Future<void> _fetchHomeContent() async {
    setState(() => _isLoadingHomeContent = true);
    try {
      final results = await Future.wait([
        ApiService.get('/services'),
        ApiService.get('/promos'),
        ApiService.get('/articles'),
      ]);
      final services = (results[0] as List)
          .map((e) => ServiceModel.fromJson(e as Map<String, dynamic>))
          .toList();
      final promos = (results[1] as List)
          .map((e) => PromoModel.fromJson(e as Map<String, dynamic>))
          .toList();
      final articles = (results[2] as List)
          .map((e) => ArticleModel.fromJson(e as Map<String, dynamic>))
          .toList();
      if (mounted) {
        setState(() {
          _recommendedServices = services.take(3).toList();
          _promos = promos;
          _articles = articles;
          _isLoadingHomeContent = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _isLoadingHomeContent = false);
    }
  }

  @override
  void dispose() {
    AuthService.instance.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    if (mounted) setState(() {});
    _fetchBadgeCounts();
  }

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
            const ShopTab(), // 1: Shop
            const BookingTab(), // 2: Book
            const CardsTab(), // 3: Cards
            const AccountTab(), // 4: Account
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,
        backgroundColor: Colors.white,
        selectedItemColor: kAccentColor,
        unselectedItemColor: kTextColor.withOpacity(0.4),
        currentIndex: _selectedIndex,
        onTap: (index) {
          // "Book" (2) and "Account" (4) require a signed-in user; send
          // guests straight to Login instead of the tab's own sign-in prompt.
          final requiresAuth = index == 2 || index == 4;
          if (requiresAuth && !AuthService.instance.isLoggedIn) {
            Navigator.push(context, fadeSlideRoute(const LoginScreen()));
            return;
          }
          setState(() => _selectedIndex = index);
        },
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: "Home"),
          BottomNavigationBarItem(
            icon: Icon(Icons.shopping_bag),
            label: "service",
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
                    GestureDetector(
                      onTap: () {
                        if (AuthService.instance.currentUser == null) {
                          Navigator.push(
                            context,
                            fadeSlideRoute(const LoginScreen()),
                          );
                        } else {
                          setState(() => _selectedIndex = 4); // Account tab
                        }
                      },
                      child: CircleAvatar(
                        radius: 25,
                        backgroundColor: kSecondaryColor,
                        child: const Icon(
                          Icons.person,
                          color: kAccentColor,
                          size: 30,
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            AuthService.instance.currentUser?.name ??
                                "Guest Account",
                            style: const TextStyle(
                              color: kTextColor,
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          // ===== SIGN IN BUTTON ROUTING =====
                          if (AuthService.instance.currentUser == null)
                            GestureDetector(
                              onTap: () {
                                Navigator.push(
                                  context,
                                  fadeSlideRoute(const LoginScreen()),
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
                            )
                          else
                            Text(
                              AuthService.instance.currentUser!.email,
                              style: TextStyle(
                                color: kTextColor.withOpacity(0.6),
                                fontSize: 13,
                              ),
                            ),
                        ],
                      ),
                    ),
                    _circleIconButton(
                      Icons.notifications_none,
                      () async {
                        if (!AuthService.instance.isLoggedIn) {
                          Navigator.push(context, fadeSlideRoute(const LoginScreen()));
                          return;
                        }
                        await Navigator.push(
                          context,
                          fadeSlideRoute(const NotificationsScreen()),
                        );
                        _fetchBadgeCounts();
                      },
                      badgeCount: _unreadNotificationCount,
                    ),
                    const SizedBox(width: 10),
                    _circleIconButton(
                      Icons.favorite_border,
                      () async {
                        if (!AuthService.instance.isLoggedIn) {
                          Navigator.push(context, fadeSlideRoute(const LoginScreen()));
                          return;
                        }
                        await Navigator.push(
                          context,
                          fadeSlideRoute(const WishlistScreen()),
                        );
                        _fetchBadgeCounts();
                      },
                      badgeCount: _wishlistCount,
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
                  child: _isLoadingHomeContent
                      ? const Center(
                          child: CircularProgressIndicator(color: kPrimaryColor),
                        )
                      : _recommendedServices.isEmpty
                          ? const Center(
                              child: Text(
                                "No recommendations yet.",
                                style: TextStyle(color: kTextColor),
                              ),
                            )
                          : ListView(
                              scrollDirection: Axis.horizontal,
                              children: _recommendedServices
                                  .map(
                                    (service) => _buildRecommendedCard(
                                      Icons.spa,
                                      service.name,
                                      service.formattedPrice,
                                    ),
                                  )
                                  .toList(),
                            ),
                ),
                const SizedBox(height: 30),

                // ===== PROMOS =====
                // Dark espresso banner block, gaya ng "PERFECT PACKAGE" strip
                // sa reference — white text sa dark brown background.
                if (!_isLoadingHomeContent && _promos.isNotEmpty) ...[
                  _sectionHeader("Promos"),
                  const SizedBox(height: 15),
                  ..._promos.map(
                    (promo) => Container(
                      width: double.infinity,
                      height: 130,
                      margin: const EdgeInsets.only(bottom: 15),
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
                          Align(
                            alignment: Alignment.centerLeft,
                            child: Text(
                              "${promo.title}\n${promo.formattedPrice}",
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 15),
                ],

                // ===== BLOGS AND ARTICLES =====
                _sectionHeader("Blogs and Articles"),
                const SizedBox(height: 15),
                if (_isLoadingHomeContent)
                  const Center(
                    child: CircularProgressIndicator(color: kPrimaryColor),
                  )
                else if (_articles.isEmpty)
                  const Text(
                    "No articles yet.",
                    style: TextStyle(color: kTextColor),
                  )
                else
                  Column(
                    children: _articles
                        .map((article) => _buildArticleCard(article))
                        .toList(),
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

  Widget _buildArticleCard(ArticleModel article) {
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
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.menu_book, size: 12, color: kAccentColor),
                          const SizedBox(width: 4),
                          Text(
                            article.category,
                            style: const TextStyle(
                              fontSize: 11,
                              color: kAccentColor,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  Text(
                    article.title,
                    style: const TextStyle(
                      color: kTextColor,
                      fontWeight: FontWeight.w600,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Text(
                    article.excerpt,
                    style: TextStyle(
                      color: kTextColor.withOpacity(0.6),
                      fontSize: 12,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
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
