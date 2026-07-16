import '../utils/constants.dart';

class AppointmentModel {
  final int id;
  final int userId;
  final int serviceId;
  final int personnelId;
  final DateTime appointmentDate;
  final String startTime;
  final String status;
  final String? notes;
  final String? paymentProofPath;
  final String? userName;
  final String? serviceName;
  final String? personnelName;

  const AppointmentModel({
    required this.id,
    required this.userId,
    required this.serviceId,
    required this.personnelId,
    required this.appointmentDate,
    required this.startTime,
    required this.status,
    this.notes,
    this.paymentProofPath,
    this.userName,
    this.serviceName,
    this.personnelName,
  });

  factory AppointmentModel.fromJson(Map<String, dynamic> json) {
    return AppointmentModel(
      id: json['id'] as int,
      userId: json['user_id'] as int,
      serviceId: json['service_id'] as int,
      personnelId: json['personnel_id'] as int,
      appointmentDate: DateTime.parse(json['appointment_date'] as String),
      startTime: json['start_time'] as String,
      status: json['status'] as String,
      notes: json['notes'] as String?,
      paymentProofPath: json['payment_proof_path'] as String?,
      userName: (json['user'] as Map<String, dynamic>?)?['name'] as String?,
      serviceName:
          (json['service'] as Map<String, dynamic>?)?['name'] as String?,
      personnelName:
          (json['personnel'] as Map<String, dynamic>?)?['name'] as String?,
    );
  }

  String? get paymentProofUrl =>
      paymentProofPath == null ? null : '$kStorageBaseUrl/$paymentProofPath';

  bool get isUpcoming =>
      !appointmentDate.isBefore(DateTime.now().subtract(const Duration(days: 1))) &&
      status != 'cancelled' &&
      status != 'served' &&
      status != 'no-show';
}
