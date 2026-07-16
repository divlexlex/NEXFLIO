class PromoModel {
  final int id;
  final String title;
  final double price;

  const PromoModel({required this.id, required this.title, required this.price});

  factory PromoModel.fromJson(Map<String, dynamic> json) {
    return PromoModel(
      id: json['id'] as int,
      title: json['title'] as String,
      price: double.parse(json['price'].toString()),
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
}
