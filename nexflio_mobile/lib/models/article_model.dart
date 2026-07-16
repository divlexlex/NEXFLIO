class ArticleModel {
  final int id;
  final String title;
  final String excerpt;
  final String category;

  const ArticleModel({
    required this.id,
    required this.title,
    required this.excerpt,
    required this.category,
  });

  factory ArticleModel.fromJson(Map<String, dynamic> json) {
    return ArticleModel(
      id: json['id'] as int,
      title: json['title'] as String,
      excerpt: json['excerpt'] as String,
      category: json['category'] as String,
    );
  }
}
