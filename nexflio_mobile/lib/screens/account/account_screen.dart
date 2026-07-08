import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../auth/login_screen.dart';

class AccountTab extends StatelessWidget {
  const AccountTab({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(kDefaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // HEADER
              const Text(
                "My",
                style: TextStyle(
                  fontSize: 24,
                  color: kTextColor,
                  fontWeight: FontWeight.w400,
                ),
              ),
              const Text(
                "Account",
                style: TextStyle(
                  fontSize: 28,
                  color: kAccentColor,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 25),

              // PROFILE CARD
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: kCardColor,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: kSecondaryColor),
                  boxShadow: [
                    BoxShadow(
                      color: kTextColor.withOpacity(0.05),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: Row(
                  children: [
                    const CircleAvatar(
                      radius: 35,
                      backgroundColor: kSecondaryColor,
                      child: Icon(Icons.person, size: 40, color: kAccentColor),
                    ),
                    const SizedBox(width: 20),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            "Guest User",
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                              color: kTextColor,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            "guest@example.com",
                            style: TextStyle(
                              fontSize: 14,
                              color: kTextColor.withOpacity(0.6),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: kPrimaryColor.withOpacity(0.2),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Text(
                              "Edit Profile",
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                                color: kPrimaryColor,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),

              // MENU ITEMS
              const Text(
                "General",
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: kPrimaryColor,
                ),
              ),
              const SizedBox(height: 10),
              _buildMenuItem(
                icon: Icons.history,
                title: "My Purchases",
                onTap: () {},
              ),
              _buildMenuItem(
                icon: Icons.inventory_2_outlined,
                title: "My Packages",
                onTap: () {},
              ),
              _buildMenuItem(
                icon: Icons.favorite_border,
                title: "Wishlist",
                onTap: () {},
              ),

              const SizedBox(height: 25),
              const Text(
                "Settings & Support",
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.bold,
                  color: kPrimaryColor,
                ),
              ),
              const SizedBox(height: 10),
              _buildMenuItem(
                icon: Icons.notifications_outlined,
                title: "Notifications",
                onTap: () {},
              ),
              _buildMenuItem(
                icon: Icons.help_outline,
                title: "Help & FAQ",
                onTap: () {},
              ),
              _buildMenuItem(
                icon: Icons.policy_outlined,
                title: "Privacy Policy",
                onTap: () {},
              ),

              const SizedBox(height: 40),

              // LOGOUT BUTTON
              SizedBox(
                width: double.infinity,
                height: 55,
                child: OutlinedButton.icon(
                  onPressed: () {
                    // Navigate to Login Screen
                    Navigator.pushReplacement(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const LoginScreen(),
                      ),
                    );
                  },
                  icon: const Icon(Icons.logout, color: Colors.redAccent),
                  label: const Text(
                    "Log Out",
                    style: TextStyle(
                      color: Colors.redAccent,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Colors.redAccent),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 40),
            ],
          ),
        ),
      ),
    );
  }

  // ===== HELPER WIDGET =====

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kSecondaryColor.withOpacity(0.5)),
      ),
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: kSecondaryColor.withOpacity(0.3),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: kAccentColor, size: 20),
        ),
        title: Text(
          title,
          style: const TextStyle(
            color: kTextColor,
            fontWeight: FontWeight.w600,
            fontSize: 15,
          ),
        ),
        trailing: const Icon(Icons.chevron_right, color: kPrimaryColor),
        onTap: onTap,
      ),
    );
  }
}
