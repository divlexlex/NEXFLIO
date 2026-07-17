class LeaveModel {
  final int id;
  final DateTime startDate;
  final DateTime endDate;
  final String type;
  final String reason;
  final String status; // pending | approved | denied
  final String? reviewNotes;

  const LeaveModel({
    required this.id,
    required this.startDate,
    required this.endDate,
    required this.type,
    required this.reason,
    required this.status,
    this.reviewNotes,
  });

  factory LeaveModel.fromJson(Map<String, dynamic> json) {
    return LeaveModel(
      id: json['id'] as int,
      startDate: DateTime.parse(json['start_date'] as String),
      endDate: DateTime.parse(json['end_date'] as String),
      type: json['type'] as String,
      reason: json['reason'] as String,
      status: json['status'] as String,
      reviewNotes: json['review_notes'] as String?,
    );
  }

  bool get isPending => status == 'pending';
}
