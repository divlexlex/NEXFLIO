class ProductModel {
  final int id;
  final String name;
  final String? category;
  final String? description;
  final double price;
  final int stock;
  final String status;
  final String? imageUrl;

  const ProductModel({
    required this.id,
    required this.name,
    required this.category,
    required this.description,
    required this.price,
    required this.stock,
    required this.status,
    this.imageUrl,
  });

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    return ProductModel(
      id: json['id'] as int,
      name: json['name'] as String,
      category: json['category'] as String?,
      description: json['description'] as String?,
      price: double.parse(json['price'].toString()),
      stock: (json['stock'] ?? 0) as int,
      status: (json['status'] ?? 'active') as String,
      imageUrl: json['image_url'] as String?,
    );
  }

  String get formattedPrice => '₱${price.toStringAsFixed(2)}';
  bool get inStock => stock > 0;
}
