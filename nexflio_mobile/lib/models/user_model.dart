class UserModel {
  final int id;
  final String name;
  final String email;
  final int roleId;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    required this.roleId,
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as int,
      name: json['name'] as String,
      email: json['email'] as String,
      roleId: json['role_id'] as int,
    );
  }

  Map<String, dynamic> toJson() {
    return {'id': id, 'name': name, 'email': email, 'role_id': roleId};
  }
}
