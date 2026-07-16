import 'package:flutter/material.dart';
import '../../utils/constants.dart';

class PrivacyPolicyScreen extends StatelessWidget {
  const PrivacyPolicyScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: kTextColor),
        title: const Text(
          "Privacy Policy",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(kDefaultPadding),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: Colors.orange),
                ),
                child: const Text(
                  "⚠ Placeholder text — this has not been reviewed by "
                  "$kAppName's management or a lawyer. Replace this with your "
                  "actual privacy policy before releasing the app publicly.",
                  style: TextStyle(color: kTextColor, fontSize: 12),
                ),
              ),
              const SizedBox(height: 20),
              _buildSection(
                "Information We Collect",
                "We collect the account details you provide (name, email), "
                    "booking details (service, staff, date/time), and any "
                    "photos you upload as proof of payment.",
              ),
              _buildSection(
                "How We Use Your Information",
                "Your information is used to create and manage your bookings, "
                    "verify payments, and communicate booking status updates "
                    "to you.",
              ),
              _buildSection(
                "Data Storage",
                "Your data is stored on our servers and is not shared with "
                    "third parties except as required to operate the "
                    "service.",
              ),
              _buildSection(
                "Your Rights",
                "You may request access to, correction of, or deletion of "
                    "your personal data by contacting the salon directly.",
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSection(String title, String body) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              color: kTextColor,
              fontWeight: FontWeight.bold,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            body,
            style: TextStyle(color: kTextColor.withOpacity(0.7), height: 1.4),
          ),
        ],
      ),
    );
  }
}
