class ServiceModel {
  final int id;
  final String name;
  final String category;
  final String description;
  final double price;
  final int durationMinutes;
  final String status;
  final String? imageUrl;

  const ServiceModel({
    required this.id,
    required this.name,
    required this.category,
    required this.description,
    required this.price,
    required this.durationMinutes,
    required this.status,
    this.imageUrl,
  });

  factory ServiceModel.fromJson(Map<String, dynamic> json) {
    return ServiceModel(
      id: json['id'] as int,
      name: json['name'] as String,
      category: json['category'] as String,
      description: (json['description'] ?? '') as String,
      price: double.parse(json['price'].toString()),
      durationMinutes: json['duration_minutes'] as int,
      status: json['status'] as String,
      imageUrl: json['image_url'] as String?,
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
}
