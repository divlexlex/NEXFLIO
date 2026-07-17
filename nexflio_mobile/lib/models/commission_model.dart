class CommissionEntryModel {
  final int id;
  final String servicePrice;
  final String rate;
  final String amount;
  final DateTime earnedAt;
  final String? serviceName;

  const CommissionEntryModel({
    required this.id,
    required this.servicePrice,
    required this.rate,
    required this.amount,
    required this.earnedAt,
    this.serviceName,
  });

  factory CommissionEntryModel.fromJson(Map<String, dynamic> json) {
    final appointment = json['appointment'] as Map<String, dynamic>?;
    final service = appointment?['service'] as Map<String, dynamic>?;

    return CommissionEntryModel(
      id: json['id'] as int,
      servicePrice: json['service_price'].toString(),
      rate: json['rate'].toString(),
      amount: json['amount'].toString(),
      earnedAt: DateTime.parse(json['earned_at'] as String),
      serviceName: service?['name'] as String?,
    );
  }
}

/// Mirrors the /my-commissions payload: fixed base pay + the month's
/// commission ledger.
class CommissionSummaryModel {
  final String month;
  final String basePay;
  final String commissionRate;
  final String commissionTotal;
  final String grandTotal;
  final List<CommissionEntryModel> entries;

  const CommissionSummaryModel({
    required this.month,
    required this.basePay,
    required this.commissionRate,
    required this.commissionTotal,
    required this.grandTotal,
    required this.entries,
  });

  factory CommissionSummaryModel.fromJson(Map<String, dynamic> json) {
    return CommissionSummaryModel(
      month: json['month'] as String,
      basePay: json['base_pay'].toString(),
      commissionRate: json['commission_rate'].toString(),
      commissionTotal: json['commission_total'].toString(),
      grandTotal: json['grand_total'].toString(),
      entries: (json['entries'] as List)
          .map((e) => CommissionEntryModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
