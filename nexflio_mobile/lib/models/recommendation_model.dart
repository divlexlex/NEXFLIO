/// A single item from the dynamic `/recommendations` feed — a normalized
/// service, product, or promo. `type` decides which detail page to open.
class RecommendationModel {
  final String type; // 'service' | 'product' | 'promo'
  final int id;
  final String title;
  final String? subtitle;
  final double price;
  final String? imageUrl;

  const RecommendationModel({
    required this.type,
    required this.id,
    required this.title,
    required this.subtitle,
    required this.price,
    required this.imageUrl,
  });

  factory RecommendationModel.fromJson(Map<String, dynamic> json) {
    return RecommendationModel(
      type: json['type'] as String,
      id: json['id'] as int,
      title: json['title'] as String,
      subtitle: json['subtitle'] as String?,
      price: double.parse((json['price'] ?? 0).toString()),
      imageUrl: json['image_url'] as String?,
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
}
