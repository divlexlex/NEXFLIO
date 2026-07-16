class GiftCardModel {
  final int id;
  final String name;
  final double amount;
  final String? description;

  const GiftCardModel({
    required this.id,
    required this.name,
    required this.amount,
    this.description,
  });

  factory GiftCardModel.fromJson(Map<String, dynamic> json) {
    return GiftCardModel(
      id: json['id'] as int,
      name: json['name'] as String,
      amount: double.parse(json['amount'].toString()),
      description: json['description'] as String?,
    );
  }

  String get formattedAmount => '₱${amount.toStringAsFixed(2)}';
}
