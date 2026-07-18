class PromoModel {
  final int id;
  final String title;
  final double price;
  final String? imageUrl;
  final String? serviceName;

  const PromoModel({
    required this.id,
    required this.title,
    required this.price,
    this.imageUrl,
    this.serviceName,
  });

  factory PromoModel.fromJson(Map<String, dynamic> json) {
    return PromoModel(
      id: json['id'] as int,
      title: json['title'] as String,
      price: double.parse(json['price'].toString()),
      imageUrl: json['image_url'] as String?,
      serviceName: (json['service'] as Map<String, dynamic>?)?['name'] as String?,
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
}
