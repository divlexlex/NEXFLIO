import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/notification_model.dart';
import '../../services/api_service.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<NotificationModel> _notifications = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchNotifications();
  }

  Future<void> _fetchNotifications() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/notifications');
      final notifications = (data as List)
          .map((e) => NotificationModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _notifications = notifications;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load notifications.';
        _isLoading = false;
      });
    }
  }

  Future<void> _markRead(NotificationModel notification) async {
    if (notification.isRead) return;
    try {
      await ApiService.patch('/notifications/${notification.id}/read', {});
      _fetchNotifications();
    } catch (_) {
      // Non-critical — the notification just stays marked unread.
    }
  }

  Future<void> _markAllRead() async {
    try {
      await ApiService.patch('/notifications/read-all', {});
      _fetchNotifications();
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: IconThemeData(color: kTextColor),
        title: Text(
          "Notifications",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
        actions: [
          if (_notifications.any((n) => !n.isRead))
            TextButton(
              onPressed: _markAllRead,
              child: const Text(
                "Mark all read",
                style: TextStyle(color: kPrimaryColor),
              ),
            ),
        ],
      ),
      body: SafeArea(child: _buildBody()),
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
              onPressed: _fetchNotifications,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    if (_notifications.isEmpty) {
      return Center(
        child: Text(
          "No notifications yet.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _fetchNotifications,
      child: ListView(
        padding: const EdgeInsets.all(kDefaultPadding),
        children: _notifications
            .map((n) => _buildNotificationTile(n))
            .toList(),
      ),
    );
  }

  Widget _buildNotificationTile(NotificationModel notification) {
    return GestureDetector(
      onTap: () => _markRead(notification),
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: notification.isRead ? kCardColor : kSecondaryColor.withOpacity(0.3),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: kSecondaryColor),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(
              notification.isRead
                  ? Icons.notifications_none
                  : Icons.notifications_active,
              color: kPrimaryColor,
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    notification.title,
                    style: TextStyle(
                      color: kTextColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    notification.body,
                    style: TextStyle(color: kTextColor.withOpacity(0.7), fontSize: 13),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
