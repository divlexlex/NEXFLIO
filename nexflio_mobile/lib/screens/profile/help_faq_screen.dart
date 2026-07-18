import 'package:flutter/material.dart';
import '../../utils/constants.dart';

class HelpFaqScreen extends StatelessWidget {
  const HelpFaqScreen({super.key});

  static const List<Map<String, String>> _faqs = [
    {
      'q': 'How do I book an appointment?',
      'a': 'Go to Services, pick a service, tap "Book", choose a staff member, '
          'date and time, then attach proof of payment to confirm.',
    },
    {
      'q': 'How do I know if my booking is confirmed?',
      'a': 'New bookings start as "Pending" until a staff member reviews your '
          'proof of payment and approves it. You\'ll get a notification when '
          'that happens.',
    },
    {
      'q': 'Can I cancel or reschedule my appointment?',
      'a': 'Contact the branch directly for now — in-app cancellation isn\'t '
          'available yet.',
    },
    {
      'q': 'How do I save a service to my Wishlist?',
      'a': 'Tap the heart icon on any service card in the Services tab.',
    },
    {
      'q': 'Where can I see my past bookings?',
      'a': 'Go to Account > My Purchases (or Transaction History) to see all '
          'your past and current bookings.',
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: IconThemeData(color: kTextColor),
        title: Text(
          "Help & FAQ",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
      ),
      body: SafeArea(
        child: ListView.builder(
          padding: const EdgeInsets.all(kDefaultPadding),
          itemCount: _faqs.length,
          itemBuilder: (context, index) {
            final faq = _faqs[index];
            return Container(
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: kCardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kSecondaryColor),
              ),
              child: ExpansionTile(
                iconColor: kAccentColor,
                collapsedIconColor: kAccentColor,
                title: Text(
                  faq['q']!,
                  style: TextStyle(
                    color: kTextColor,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        faq['a']!,
                        style: TextStyle(color: kTextColor.withOpacity(0.7)),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
