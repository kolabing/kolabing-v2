# Accept Application API - Mobile Implementation Guide

## Overview

When a community user applies to a business user's opportunity, the business owner can **accept** the application. Accepting an application creates a **Collaboration** record and transitions the application status from `pending` to `accepted`.

**Flow:**
```
Application (pending) → Accept → Application (accepted) + Collaboration (scheduled)
```

---

## Endpoint

### POST /api/v1/applications/{application_id}/accept

**Auth:** Bearer token required (must be the opportunity owner)

---

## Request

```
POST /api/v1/applications/019c0faf-f4fb-727c-bd35-b95280c49209/accept
Authorization: Bearer {token}
Content-Type: application/json
```

### Request Body

```json
{
  "scheduled_date": "2026-03-15",
  "contact_methods": {
    "whatsapp": "+34612345678",
    "email": "contact@mybusiness.com",
    "instagram": "@mybusiness"
  }
}
```

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| scheduled_date | string (date) | Yes | Collaboration date. Must be a future date (after today). Format: `YYYY-MM-DD` |
| contact_methods | object | Yes | At least one contact method must be provided |
| contact_methods.whatsapp | string | No | WhatsApp phone number |
| contact_methods.email | string | No | Contact email (must be valid email format) |
| contact_methods.instagram | string | No | Instagram handle |

### Validation Rules
- `scheduled_date` is required, must be a valid date, must be after today
- `contact_methods` is required, must be an object
- At least one contact method (whatsapp, email, or instagram) must be non-empty
- `contact_methods.email` must be a valid email if provided

---

## Responses

### Success (200 OK)

```json
{
  "success": true,
  "data": {
    "application": {
      "id": "019c0faf-f4fb-727c-bd35-b95280c49209",
      "collab_opportunity_id": "019c1689-e4eb-72cd-8a48-cc3439d044d9",
      "applicant_profile": {
        "id": "019c0f79-261c-7248-a26c-ff4fc303329c",
        "name": "Yoga Flow Barcelona",
        "user_type": "community",
        "avatar_url": null
      },
      "message": "We'd love to host a yoga session at your venue!",
      "availability": "Weekends preferred",
      "status": "accepted",
      "created_at": "2026-02-01T10:30:00+00:00",
      "updated_at": "2026-02-01T14:00:00+00:00"
    },
    "collaboration": {
      "id": "collab-uuid-here",
      "collab_opportunity": {
        "id": "019c1689-e4eb-72cd-8a48-cc3439d044d9",
        "title": "Weekend Brunch & Content Creation"
      },
      "creator_profile": {
        "id": "019c0f73-e8e7-72ca-aa8f-88802484f175",
        "name": "kollabBusiness",
        "user_type": "business",
        "avatar_url": null
      },
      "applicant_profile": {
        "id": "019c0f79-261c-7248-a26c-ff4fc303329c",
        "name": "Yoga Flow Barcelona",
        "user_type": "community",
        "avatar_url": null
      },
      "status": "scheduled",
      "scheduled_date": "2026-03-15",
      "contact_methods": {
        "whatsapp": "+34612345678",
        "email": "contact@mybusiness.com",
        "instagram": "@mybusiness"
      },
      "completed_at": null,
      "my_role": "creator",
      "created_at": "2026-02-01T14:00:00+00:00",
      "updated_at": "2026-02-01T14:00:00+00:00"
    }
  },
  "message": "Application accepted and collaboration created"
}
```

### Validation Error (422)

Missing or invalid fields:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "scheduled_date": ["The scheduled date field is required."],
    "contact_methods": ["At least one contact method must be provided"]
  }
}
```

### Unauthorized (403)

Not the opportunity owner:

```json
{
  "success": false,
  "message": "You are not authorized to accept this application"
}
```

Subscription required (business users):

```json
{
  "success": false,
  "message": "An active subscription is required to accept applications."
}
```

### Bad Request (400)

Application not in pending status:

```json
{
  "success": false,
  "message": "Application cannot be accepted. Current status: accepted"
}
```

### Not Found (404)

Invalid application ID returns standard 404.

---

## Response Fields

### Application Object

| Field | Type | Description |
|-------|------|-------------|
| id | string (UUID) | Application ID |
| collab_opportunity_id | string (UUID) | Related opportunity ID |
| collab_opportunity | object | Opportunity summary (when loaded) |
| applicant_profile | object | Applicant's profile summary |
| message | string | Applicant's cover message |
| availability | string | Applicant's availability note |
| status | string | `accepted` after this call |
| created_at | datetime | When application was submitted |
| updated_at | datetime | When application was accepted |

### Collaboration Object

| Field | Type | Description |
|-------|------|-------------|
| id | string (UUID) | Collaboration ID |
| collab_opportunity | object | Opportunity summary |
| creator_profile | object | Opportunity owner profile |
| applicant_profile | object | Accepted applicant profile |
| business_profile | object | Business extended profile |
| community_profile | object | Community extended profile |
| status | string | `scheduled` (initial status) |
| scheduled_date | string | Date in `YYYY-MM-DD` format |
| contact_methods | object | Contact methods provided during accept |
| completed_at | datetime | null (not completed yet) |
| my_role | string | `creator` or `applicant` based on auth user |
| created_at | datetime | When collaboration was created |
| updated_at | datetime | When collaboration was last updated |

---

## Flutter/Dart Implementation

### Accept Application Service

```dart
class ApplicationService {
  final Dio _dio;

  ApplicationService(this._dio);

  /// Accept an application and create a collaboration
  Future<AcceptApplicationResponse> acceptApplication({
    required String applicationId,
    required String scheduledDate,
    required Map<String, String?> contactMethods,
  }) async {
    final response = await _dio.post(
      '/api/v1/applications/$applicationId/accept',
      data: {
        'scheduled_date': scheduledDate,
        'contact_methods': contactMethods,
      },
    );

    return AcceptApplicationResponse.fromJson(response.data);
  }
}
```

### Models

```dart
class AcceptApplicationResponse {
  final bool success;
  final String? message;
  final ApplicationModel? application;
  final CollaborationModel? collaboration;

  AcceptApplicationResponse({
    required this.success,
    this.message,
    this.application,
    this.collaboration,
  });

  factory AcceptApplicationResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>?;
    return AcceptApplicationResponse(
      success: json['success'],
      message: json['message'],
      application: data?['application'] != null
          ? ApplicationModel.fromJson(data!['application'])
          : null,
      collaboration: data?['collaboration'] != null
          ? CollaborationModel.fromJson(data!['collaboration'])
          : null,
    );
  }
}

class CollaborationModel {
  final String id;
  final String status;
  final String? scheduledDate;
  final Map<String, dynamic>? contactMethods;
  final String? myRole;
  final String? completedAt;
  final String createdAt;

  CollaborationModel({
    required this.id,
    required this.status,
    this.scheduledDate,
    this.contactMethods,
    this.myRole,
    this.completedAt,
    required this.createdAt,
  });

  factory CollaborationModel.fromJson(Map<String, dynamic> json) {
    return CollaborationModel(
      id: json['id'],
      status: json['status'],
      scheduledDate: json['scheduled_date'],
      contactMethods: json['contact_methods'],
      myRole: json['my_role'],
      completedAt: json['completed_at'],
      createdAt: json['created_at'],
    );
  }
}
```

### Accept Application Screen Widget

```dart
class AcceptApplicationSheet extends StatefulWidget {
  final String applicationId;
  final String applicantName;

  const AcceptApplicationSheet({
    super.key,
    required this.applicationId,
    required this.applicantName,
  });

  @override
  State<AcceptApplicationSheet> createState() => _AcceptApplicationSheetState();
}

class _AcceptApplicationSheetState extends State<AcceptApplicationSheet> {
  final _formKey = GlobalKey<FormState>();
  DateTime? _scheduledDate;
  final _whatsappController = TextEditingController();
  final _emailController = TextEditingController();
  final _instagramController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_scheduledDate == null) {
      setState(() => _errorMessage = 'Please select a date');
      return;
    }

    // At least one contact method
    if (_whatsappController.text.isEmpty &&
        _emailController.text.isEmpty &&
        _instagramController.text.isEmpty) {
      setState(() => _errorMessage = 'Provide at least one contact method');
      return;
    }

    setState(() { _isLoading = true; _errorMessage = null; });

    try {
      final service = context.read<ApplicationService>();
      final response = await service.acceptApplication(
        applicationId: widget.applicationId,
        scheduledDate: _scheduledDate!.toIso8601String().split('T').first,
        contactMethods: {
          if (_whatsappController.text.isNotEmpty)
            'whatsapp': _whatsappController.text,
          if (_emailController.text.isNotEmpty)
            'email': _emailController.text,
          if (_instagramController.text.isNotEmpty)
            'instagram': _instagramController.text,
        },
      );

      if (response.success && mounted) {
        Navigator.pop(context, response.collaboration);
      } else {
        setState(() => _errorMessage = response.message);
      }
    } catch (e) {
      setState(() => _errorMessage = 'An error occurred. Please try again.');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Accept ${widget.applicantName}',
              style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 16),

            // Date picker
            ListTile(
              title: Text(_scheduledDate != null
                ? 'Date: ${_scheduledDate!.toIso8601String().split('T').first}'
                : 'Select collaboration date'),
              trailing: const Icon(Icons.calendar_today),
              onTap: () async {
                final date = await showDatePicker(
                  context: context,
                  firstDate: DateTime.now().add(const Duration(days: 1)),
                  lastDate: DateTime.now().add(const Duration(days: 365)),
                );
                if (date != null) setState(() => _scheduledDate = date);
              },
            ),
            const SizedBox(height: 16),

            // Contact methods
            const Text('Contact Methods (at least one):'),
            const SizedBox(height: 8),
            TextFormField(
              controller: _whatsappController,
              decoration: const InputDecoration(
                labelText: 'WhatsApp',
                hintText: '+34612345678',
              ),
            ),
            TextFormField(
              controller: _emailController,
              decoration: const InputDecoration(
                labelText: 'Email',
                hintText: 'contact@business.com',
              ),
            ),
            TextFormField(
              controller: _instagramController,
              decoration: const InputDecoration(
                labelText: 'Instagram',
                hintText: '@yourbusiness',
              ),
            ),

            if (_errorMessage != null) ...[
              const SizedBox(height: 12),
              Text(_errorMessage!,
                style: const TextStyle(color: Colors.red)),
            ],

            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: _isLoading ? null : _submit,
              child: _isLoading
                ? const CircularProgressIndicator()
                : const Text('Accept Application'),
            ),
          ],
        ),
      ),
    );
  }
}
```

---

## Swift Implementation

### Accept Application Service

```swift
struct AcceptApplicationRequest: Encodable {
    let scheduledDate: String
    let contactMethods: ContactMethods

    enum CodingKeys: String, CodingKey {
        case scheduledDate = "scheduled_date"
        case contactMethods = "contact_methods"
    }

    struct ContactMethods: Encodable {
        var whatsapp: String?
        var email: String?
        var instagram: String?
    }
}

struct AcceptApplicationResponse: Decodable {
    let success: Bool
    let message: String?
    let data: AcceptData?

    struct AcceptData: Decodable {
        let application: ApplicationModel
        let collaboration: CollaborationModel
    }
}

struct CollaborationModel: Decodable {
    let id: String
    let status: String
    let scheduledDate: String?
    let contactMethods: [String: String]?
    let myRole: String?
    let completedAt: String?
    let createdAt: String

    enum CodingKeys: String, CodingKey {
        case id, status
        case scheduledDate = "scheduled_date"
        case contactMethods = "contact_methods"
        case myRole = "my_role"
        case completedAt = "completed_at"
        case createdAt = "created_at"
    }
}

class ApplicationService {
    let baseURL: String
    let token: String

    init(baseURL: String, token: String) {
        self.baseURL = baseURL
        self.token = token
    }

    func acceptApplication(
        applicationId: String,
        scheduledDate: String,
        contactMethods: AcceptApplicationRequest.ContactMethods
    ) async throws -> AcceptApplicationResponse {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/accept")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

        let body = AcceptApplicationRequest(
            scheduledDate: scheduledDate,
            contactMethods: contactMethods
        )
        request.httpBody = try JSONEncoder().encode(body)

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(AcceptApplicationResponse.self, from: data)
    }
}
```

---

## Important Notes

1. **Authorization**: Only the opportunity owner (creator) can accept applications
2. **Subscription**: Business users must have an active subscription to accept
3. **Status check**: Only `pending` applications can be accepted
4. **Side effects**: Accepting creates a `Collaboration` with status `scheduled`
5. **Notification**: The applicant receives an `application_accepted` notification automatically
6. **Scheduled date**: Must be a future date (after today)
7. **Contact methods**: At least one method is required so both parties can communicate

### Collaboration Status Flow After Accept
```
scheduled → active → completed
                  → cancelled
```
