import 'package:flutter/material.dart';
import '../../utils/constants.dart';
import '../../models/membership_plan_model.dart';
import '../../services/api_service.dart';

class MyPackagesScreen extends StatefulWidget {
  const MyPackagesScreen({super.key});

  @override
  State<MyPackagesScreen> createState() => _MyPackagesScreenState();
}

class _MyPackagesScreenState extends State<MyPackagesScreen> {
  List<MembershipPlanModel> _plans = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchPlans();
  }

  Future<void> _fetchPlans() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final data = await ApiService.get('/membership-plans');
      final plans = (data as List)
          .map((e) => MembershipPlanModel.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _plans = plans;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e is ApiException ? e.message : 'Failed to load packages.';
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kBackgroundColor,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: kTextColor),
        title: const Text(
          "My Packages",
          style: TextStyle(color: kTextColor, fontWeight: FontWeight.bold),
        ),
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
            Text(_error!, style: const TextStyle(color: kTextColor)),
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: _fetchPlans,
              style: ElevatedButton.styleFrom(backgroundColor: kAccentColor),
              child: const Text("Retry", style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      );
    }

    if (_plans.isEmpty) {
      return const Center(
        child: Text(
          "No packages available right now.",
          style: TextStyle(color: kTextColor, fontSize: 16),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.all(kDefaultPadding),
      children: _plans
          .map(
            (plan) => Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: kCardColor,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kSecondaryColor),
              ),
              child: Row(
                children: [
                  const Icon(Icons.card_membership, color: kAccentColor),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          "${plan.name} — ${plan.badgeText}",
                          style: const TextStyle(
                            color: kTextColor,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (plan.description != null)
                          Text(
                            plan.description!,
                            style: TextStyle(
                              color: kTextColor.withOpacity(0.6),
                              fontSize: 12,
                            ),
                          ),
                      ],
                    ),
                  ),
                  Text(
                    plan.formattedPrice,
                    style: const TextStyle(
                      color: kPrimaryColor,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          )
          .toList(),
    );
  }
}
