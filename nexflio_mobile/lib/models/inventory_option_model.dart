class InventoryOptionModel {
  final int id;
  final String itemName;
  final String unit;
  final int quantity;

  const InventoryOptionModel({
    required this.id,
    required this.itemName,
    required this.unit,
    required this.quantity,
  });

  factory InventoryOptionModel.fromJson(Map<String, dynamic> json) {
    return InventoryOptionModel(
      id: json['id'] as int,
      itemName: json['item_name'] as String,
      unit: json['unit'] as String,
      quantity: json['quantity'] as int,
    );
  }
}
