import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../services/auth_service.dart';
import '../../utils/page_transitions.dart';
import '../../widgets/auth_transition_screen.dart';
import '../auth/login_screen.dart';
import '../staff/manage_appointments_screen.dart';
import '../staff/staff_dashboard_screen.dart';
import '../home/notifications_screen.dart';
import '../profile/profile_settings_screen.dart';
import '../profile/transaction_history_screen.dart';
import '../profile/my_packages_screen.dart';
import '../profile/help_faq_screen.dart';
import '../profile/privacy_policy_screen.dart';
import 'wishlist_screen.dart';

class AccountTab extends StatelessWidget {
  const AccountTab({super.key});

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: AuthService.instance,
      builder: (context, _) => _buildScaffold(context),
    );
  }

  Widget _buildScaffold(BuildContext context) {
    final user = AuthService.instance.currentUser;

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
                          Text(
                            user?.name ?? "Guest User",
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                              color: kTextColor,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            user?.email ?? "Not signed in",
                            style: TextStyle(
                              fontSize: 14,
                              color: kTextColor.withOpacity(0.6),
                            ),
                          ),
                          const SizedBox(height: 10),
                          GestureDetector(
                            onTap: user == null
                                ? null
                                : () {
                                    Navigator.push(
                                      context,
                                      fadeSlideRoute(const ProfileSettingsScreen()),
                                    );
                                  },
                            child: Container(
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
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),

              // STAFF TOOLS (role 3): personal hub — schedule, attendance,
              // break, leaves, commission.
              if (user != null && user.roleId == kStaffRoleId) ...[
                const Text(
                  "Staff Tools",
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: kPrimaryColor,
                  ),
                ),
                const SizedBox(height: 10),
                _buildMenuItem(
                  icon: Icons.badge_outlined,
                  title: "Staff Dashboard",
                  onTap: () {
                    Navigator.push(
                      context,
                      fadeSlideRoute(const StaffDashboardScreen()),
                    );
                  },
                ),
                const SizedBox(height: 25),
              ],

              // MANAGEMENT TOOLS (roles 1-2): booking verification queue.
              if (user != null &&
                  (user.roleId == kManagerRoleId ||
                      user.roleId == kSuperAdminRoleId)) ...[
                const Text(
                  "Management Tools",
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.bold,
                    color: kPrimaryColor,
                  ),
                ),
                const SizedBox(height: 10),
                _buildMenuItem(
                  icon: Icons.event_available,
                  title: "Manage Appointments",
                  onTap: () {
                    Navigator.push(
                      context,
                      fadeSlideRoute(const ManageAppointmentsScreen()),
                    );
                  },
                ),
                const SizedBox(height: 25),
              ],

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
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const TransactionHistoryScreen()),
                  );
                },
              ),
              _buildMenuItem(
                icon: Icons.inventory_2_outlined,
                title: "My Packages",
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const MyPackagesScreen()),
                  );
                },
              ),
              _buildMenuItem(
                icon: Icons.favorite_border,
                title: "Wishlist",
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const WishlistScreen()),
                  );
                },
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
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const NotificationsScreen()),
                  );
                },
              ),
              _buildMenuItem(
                icon: Icons.help_outline,
                title: "Help & FAQ",
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const HelpFaqScreen()),
                  );
                },
              ),
              _buildMenuItem(
                icon: Icons.policy_outlined,
                title: "Privacy Policy",
                onTap: () {
                  Navigator.push(
                    context,
                    fadeSlideRoute(const PrivacyPolicyScreen()),
                  );
                },
              ),

              const SizedBox(height: 40),

              // LOGOUT / SIGN IN BUTTON
              SizedBox(
                width: double.infinity,
                height: 55,
                child: user != null
                    ? OutlinedButton.icon(
                        onPressed: () async {
                          await AuthService.instance.logout();
                          if (!context.mounted) return;
                          Navigator.pushReplacement(
                            context,
                            fadeSlideRoute(
                              AuthTransitionScreen(
                                message: 'Logged out',
                                icon: Icons.logout,
                                nextScreen: const LoginScreen(),
                              ),
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
                      )
                    : ElevatedButton.icon(
                        onPressed: () {
                          Navigator.push(
                            context,
                            fadeSlideRoute(const LoginScreen()),
                          );
                        },
                        icon: const Icon(Icons.login, color: Colors.white),
                        label: const Text(
                          "Sign In",
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kAccentColor,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          elevation: 0,
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
