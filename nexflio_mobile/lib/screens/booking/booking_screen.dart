import 'package:flutter/material.dart';
import '../../utils/constants.dart';

class BookingTab extends StatefulWidget {
  const BookingTab({super.key});

  @override
  State<BookingTab> createState() => _BookingTabState();
}

class _BookingTabState extends State<BookingTab> {
  int _selectedTabIndex = 0; // 0 for Upcoming, 1 for Past

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
                    "My",
                    style: TextStyle(
                      fontSize: 24,
                      color: kTextColor,
                      fontWeight: FontWeight.w400,
                    ),
                  ),
                  const Text(
                    "Appointments",
                    style: TextStyle(
                      fontSize: 28,
                      color: kAccentColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 25),

                  // TOGGLE BUTTONS (Upcoming | Past)
                  Row(
                    children: [
                      Expanded(
                        child: _buildToggleButton(
                          title: "Upcoming",
                          isSelected: _selectedTabIndex == 0,
                          onTap: () => setState(() => _selectedTabIndex = 0),
                        ),
                      ),
                      const SizedBox(width: 15),
                      Expanded(
                        child: _buildToggleButton(
                          title: "Past",
                          isSelected: _selectedTabIndex == 1,
                          onTap: () => setState(() => _selectedTabIndex = 1),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            // APPOINTMENTS LIST
            Expanded(
              child: _selectedTabIndex == 0
                  ? _buildUpcomingList()
                  : _buildPastList(),
            ),
          ],
        ),
      ),

      // FLOATING "BOOK APPOINTMENT" BUTTON
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () {
          // TODO: Open Date/Branch selection flow
        },
        backgroundColor: kAccentColor,
        elevation: 4,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text(
          "Book Appointment",
          style: TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerFloat,
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

  Widget _buildUpcomingList() {
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        _buildAppointmentCard(
          month: "AUG",
          day: "15",
          time: "10:00 AM",
          serviceTitle: "Diode Underarm Laser",
          branch: "NEXFLIO Clinic - SM North",
          status: "Confirmed",
          isUpcoming: true,
        ),
        _buildAppointmentCard(
          month: "SEP",
          day: "02",
          time: "02:30 PM",
          serviceTitle: "Hyal-C Facial",
          branch: "NEXFLIO Clinic - Megamall",
          status: "Pending",
          isUpcoming: true,
        ),
        const SizedBox(height: 80), // Padding for Floating Action Button
      ],
    );
  }

  Widget _buildPastList() {
    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: kDefaultPadding),
      children: [
        _buildAppointmentCard(
          month: "JUL",
          day: "08",
          time: "11:00 AM",
          serviceTitle: "4D QuattroWave",
          branch: "NEXFLIO Clinic - SM North",
          status: "Completed",
          isUpcoming: false,
        ),
      ],
    );
  }

  Widget _buildAppointmentCard({
    required String month,
    required String day,
    required String time,
    required String serviceTitle,
    required String branch,
    required String status,
    required bool isUpcoming,
  }) {
    Color statusColor = status == "Confirmed"
        ? Colors.green
        : (status == "Completed" ? kPrimaryColor : Colors.orange);

    return Container(
      margin: const EdgeInsets.only(bottom: 15),
      decoration: BoxDecoration(
        color: kCardColor,
        borderRadius: BorderRadius.circular(16),
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
          // LEFT SIDE: DATE CALENDAR BLOCK
          Container(
            width: 80,
            padding: const EdgeInsets.symmetric(vertical: 20),
            decoration: BoxDecoration(
              color: isUpcoming
                  ? kPrimaryColor.withOpacity(0.1)
                  : kSecondaryColor.withOpacity(0.3),
              borderRadius: const BorderRadius.horizontal(
                left: Radius.circular(16),
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  month,
                  style: TextStyle(
                    color: isUpcoming
                        ? kAccentColor
                        : kTextColor.withOpacity(0.6),
                    fontWeight: FontWeight.bold,
                    fontSize: 14,
                  ),
                ),
                Text(
                  day,
                  style: TextStyle(
                    color: isUpcoming
                        ? kPrimaryColor
                        : kTextColor.withOpacity(0.6),
                    fontWeight: FontWeight.bold,
                    fontSize: 28,
                  ),
                ),
              ],
            ),
          ),

          // RIGHT SIDE: DETAILS
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        time,
                        style: const TextStyle(
                          color: kAccentColor,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: statusColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(
                          status,
                          style: TextStyle(
                            color: statusColor,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    serviceTitle,
                    style: const TextStyle(
                      color: kTextColor,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      const Icon(
                        Icons.location_on_outlined,
                        size: 14,
                        color: kPrimaryColor,
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          branch,
                          style: TextStyle(
                            color: kTextColor.withOpacity(0.7),
                            fontSize: 12,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
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
