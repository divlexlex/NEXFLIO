class MembershipPlanModel {
  final int id;
  final String name;
  final String type;
  final double price;
  final String? description;

  const MembershipPlanModel({
    required this.id,
    required this.name,
    required this.type,
    required this.price,
    this.description,
  });

  factory MembershipPlanModel.fromJson(Map<String, dynamic> json) {
    return MembershipPlanModel(
      id: json['id'] as int,
      name: json['name'] as String,
      type: json['type'] as String,
      price: double.parse(json['price'].toString()),
      description: json['description'] as String?,
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
  String get badgeText => type == 'renewal' ? 'Renewal' : 'New Member';
}
