class AttendanceModel {
  final int id;
  final DateTime workDate;
  final DateTime timeIn;
  final DateTime? timeOut;
  final String? notes;

  const AttendanceModel({
    required this.id,
    required this.workDate,
    required this.timeIn,
    this.timeOut,
    this.notes,
  });

  factory AttendanceModel.fromJson(Map<String, dynamic> json) {
    return AttendanceModel(
      id: json['id'] as int,
      workDate: DateTime.parse(json['work_date'] as String),
      timeIn: DateTime.parse(json['time_in'] as String),
      timeOut: json['time_out'] == null
          ? null
          : DateTime.parse(json['time_out'] as String),
      notes: json['notes'] as String?,
    );
  }

  bool get isOpen => timeOut == null;
}
