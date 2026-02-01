# Mobile Password Reset API

This document explains the password reset API integration for the mobile application.

---

## 1. Overview

Password reset is a 2-step process:

1. **Forgot Password** - The user enters their email address, and a `POST /api/v1/auth/forgot-password` request is sent to the system. The system sends a password reset link to the user's email address.
2. **Reset Password** - The user clicks the link in the email, and the mobile app opens (deep link). The user enters their new password and a `POST /api/v1/auth/reset-password` request is sent.

The token is valid for **60 minutes**. A password reset cannot be performed with an expired token.

---

## 2. API Endpoints

### 2.1. Forgot Password (Send Password Reset Link)

**Endpoint:** `POST /api/v1/auth/forgot-password`

**Authentication:** Not required (public endpoint)

#### Request

```json
{
  "email": "user@example.com"
}
```

#### Successful Response (200 OK)

```json
{
  "success": true,
  "message": "Password reset link sent to your email."
}
```

#### Error Responses

**Validation Error (422)** - Email field is missing or invalid:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "The email field is required"
    ]
  }
}
```

**Validation Error (422)** - Email not found in the database:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": [
      "No account found with this email address"
    ]
  }
}
```

**Rate Limit / Throttle (400)** - Repeated request for the same email in too short a time:

```json
{
  "success": false,
  "message": "Please wait before retrying."
}
```

> Note: By default, Laravel prevents generating a new token for the same email within 60 seconds (throttle).

---

### 2.2. Reset Password

**Endpoint:** `POST /api/v1/auth/reset-password`

**Authentication:** Not required (public endpoint)

#### Request

```json
{
  "token": "abc123def456...",
  "email": "user@example.com",
  "password": "newPassword123",
  "password_confirmation": "newPassword123"
}
```

| Field                   | Type   | Required | Description                      |
|-------------------------|--------|----------|----------------------------------|
| token                   | string | Yes      | Reset token received via email   |
| email                   | string | Yes      | The user's email address         |
| password                | string | Yes      | New password (min 8 characters)  |
| password_confirmation   | string | Yes      | New password confirmation        |

#### Successful Response (200 OK)

```json
{
  "success": true,
  "message": "Password has been reset successfully."
}
```

#### Error Responses

**Validation Error (422)** - Missing fields:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "token": ["The reset token is required"],
    "email": ["The email field is required"],
    "password": ["The password field is required"]
  }
}
```

**Validation Error (422)** - Password too short:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": ["The password must be at least 8 characters"]
  }
}
```

**Validation Error (422)** - Password confirmation does not match:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "password": ["The password confirmation does not match"]
  }
}
```

**Invalid Token (422)** - Invalid or expired token:

```json
{
  "success": false,
  "message": "This password reset token is invalid."
}
```

**Wrong Email (422)** - Token and email mismatch:

```json
{
  "success": false,
  "message": "We can't find a user with that email address."
}
```

---

## 3. Flow Diagram

```
┌──────────────────────────────────────────────────────────────────────────┐
│                     PASSWORD RESET FLOW                                  │
├──────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────┐                                                     │
│  │  LOGIN SCREEN   │                                                     │
│  │                 │                                                     │
│  │  "Forgot        │                                                     │
│  │   Password" link│                                                     │
│  └────────┬────────┘                                                     │
│           │                                                              │
│           ▼                                                              │
│  ┌─────────────────┐     ┌─────────────────┐                             │
│  │ FORGOT PASSWORD │     │   API CALL      │                             │
│  │    SCREEN       │────▶│   POST          │                             │
│  │                 │     │   /forgot-pwd   │                             │
│  │  [Email Input]  │     └────────┬────────┘                             │
│  │  [Send Btn]     │              │                                      │
│  └─────────────────┘              ▼                                      │
│                          ┌─────────────────┐                             │
│                          │  EMAIL SENT     │                             │
│                          │    SCREEN       │                             │
│                          │                 │                             │
│                          │  "Check your    │                             │
│                          │   email"        │                             │
│                          └─────────────────┘                             │
│                                                                          │
│  ═══════════════════════════════════════════════                          │
│  User opens their email application                                      │
│  ═══════════════════════════════════════════════                          │
│                                                                          │
│  ┌─────────────────┐     ┌─────────────────┐                             │
│  │  RESET LINK     │     │  DEEP LINK      │                            │
│  │  IN EMAIL       │────▶│  kolabing://    │                            │
│  │                 │     │  reset-password  │                            │
│  └─────────────────┘     └────────┬────────┘                             │
│                                   │                                      │
│                                   ▼                                      │
│  ┌─────────────────┐     ┌─────────────────┐                             │
│  │ RESET PASSWORD  │     │   API CALL      │                             │
│  │    SCREEN       │────▶│   POST          │                             │
│  │                 │     │   /reset-pwd    │                             │
│  │  [New Password] │     └────────┬────────┘                             │
│  │  [Confirm Pwd]  │              │                                      │
│  │  [Reset Btn]    │              ▼                                      │
│  └─────────────────┘     ┌─────────────────┐                             │
│                          │    SUCCESS      │                             │
│                          │                 │────▶ LOGIN SCREEN           │
│                          │  "Your password │                             │
│                          │   has been reset"│                            │
│                          └─────────────────┘                             │
│                                                                          │
└──────────────────────────────────────────────────────────────────────────┘
```

---

## 4. Deep Link Structure

The password reset link in the email must have a deep link structure that triggers the mobile app to open.

### Recommended Deep Link Format

```
kolabing://reset-password?token=TOKEN_VALUE&email=USER_EMAIL
```

### Example

```
kolabing://reset-password?token=0fb9f1a2c3d4e5f6a7b8c9d0e1f2a3b4c5d6e7f8&email=user%40example.com
```

### Universal Links / App Links

In a production environment, the link in the email should be in web URL format and redirected to the mobile app via the universal link/app link mechanism:

```
https://kolabing.com/reset-password?token=TOKEN_VALUE&email=USER_EMAIL
```

### Configuration

**iOS (Universal Links) - `apple-app-site-association`:**

```json
{
  "applinks": {
    "apps": [],
    "details": [
      {
        "appID": "TEAM_ID.com.kolabing.app",
        "paths": ["/reset-password*"]
      }
    ]
  }
}
```

**Android (App Links) - `assetlinks.json`:**

```json
[
  {
    "relation": ["delegate_permission/common.handle_all_urls"],
    "target": {
      "namespace": "android_app",
      "package_name": "com.kolabing.app",
      "sha256_cert_fingerprints": ["SHA256_HASH"]
    }
  }
]
```

### Laravel Notification Customization

To customize the URL in Laravel's `ResetPassword` notification, the following method can be added to the `App\Models\Profile` model:

```php
use Illuminate\Auth\Notifications\ResetPassword;

// Inside the Profile model:
protected function sendPasswordResetNotification(string $token): void
{
    $url = "kolabing://reset-password?token={$token}&email=" . urlencode($this->email);

    $this->notify(new ResetPassword($token));
}
```

Or using the `ResetPassword::createUrlUsing()` callback:

```php
// Inside AppServiceProvider::boot():
ResetPassword::createUrlUsing(function (Profile $profile, string $token) {
    return "https://kolabing.com/reset-password?token={$token}&email=" . urlencode($profile->email);
});
```

---

## 5. Flutter/Dart Implementation Example

### ForgotPasswordService

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;

class PasswordResetService {
  final String baseUrl;

  PasswordResetService({required this.baseUrl});

  /// Sends the password reset link
  Future<ApiResponse> forgotPassword(String email) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/v1/auth/forgot-password'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({'email': email}),
    );

    final data = jsonDecode(response.body);

    return ApiResponse(
      success: data['success'],
      message: data['message'],
      statusCode: response.statusCode,
      errors: data['errors'],
    );
  }

  /// Resets the password with a new password
  Future<ApiResponse> resetPassword({
    required String token,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/api/v1/auth/reset-password'),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: jsonEncode({
        'token': token,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
      }),
    );

    final data = jsonDecode(response.body);

    return ApiResponse(
      success: data['success'],
      message: data['message'],
      statusCode: response.statusCode,
      errors: data['errors'],
    );
  }
}

class ApiResponse {
  final bool success;
  final String message;
  final int statusCode;
  final Map<String, dynamic>? errors;

  ApiResponse({
    required this.success,
    required this.message,
    required this.statusCode,
    this.errors,
  });
}
```

### ForgotPasswordScreen Widget

```dart
import 'package:flutter/material.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;
  bool _emailSent = false;

  Future<void> _submitForgotPassword() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final service = PasswordResetService(baseUrl: 'https://api.kolabing.com');
      final response = await service.forgotPassword(_emailController.text);

      if (response.success) {
        setState(() => _emailSent = true);
      } else {
        setState(() => _errorMessage = response.message);
      }
    } catch (e) {
      setState(() => _errorMessage = 'An error occurred. Please try again.');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_emailSent) {
      return Scaffold(
        appBar: AppBar(title: const Text('Email Sent')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24.0),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.email_outlined, size: 64, color: Colors.green),
                const SizedBox(height: 16),
                const Text(
                  'A password reset link has been sent to your email address.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 16),
                ),
                const SizedBox(height: 24),
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('Back to Login'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Forgot Password')),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Enter your email address and we will send you a password reset link.',
                style: TextStyle(fontSize: 14, color: Colors.grey),
              ),
              const SizedBox(height: 24),
              TextFormField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Email',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Email field is required';
                  }
                  if (!value.contains('@')) {
                    return 'Please enter a valid email address';
                  }
                  return null;
                },
              ),
              if (_errorMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  style: const TextStyle(color: Colors.red),
                ),
              ],
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _isLoading ? null : _submitForgotPassword,
                child: _isLoading
                    ? const CircularProgressIndicator()
                    : const Text('Send Reset Link'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

### ResetPasswordScreen Widget

```dart
import 'package:flutter/material.dart';

class ResetPasswordScreen extends StatefulWidget {
  final String token;
  final String email;

  const ResetPasswordScreen({
    super.key,
    required this.token,
    required this.email,
  });

  @override
  State<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends State<ResetPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  Future<void> _submitResetPassword() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final service = PasswordResetService(baseUrl: 'https://api.kolabing.com');
      final response = await service.resetPassword(
        token: widget.token,
        email: widget.email,
        password: _passwordController.text,
        passwordConfirmation: _confirmController.text,
      );

      if (response.success) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Your password has been changed successfully!')),
          );
          Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
        }
      } else {
        setState(() => _errorMessage = response.message);
      }
    } catch (e) {
      setState(() => _errorMessage = 'An error occurred. Please try again.');
    } finally {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Set New Password')),
      body: Padding(
        padding: const EdgeInsets.all(24.0),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Email: ${widget.email}',
                style: const TextStyle(fontSize: 14, color: Colors.grey),
              ),
              const SizedBox(height: 24),
              TextFormField(
                controller: _passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'New Password',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Password field is required';
                  }
                  if (value.length < 8) {
                    return 'Password must be at least 8 characters';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: _confirmController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm Password',
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value != _passwordController.text) {
                    return 'Passwords do not match';
                  }
                  return null;
                },
              ),
              if (_errorMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  style: const TextStyle(color: Colors.red),
                ),
              ],
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: _isLoading ? null : _submitResetPassword,
                child: _isLoading
                    ? const CircularProgressIndicator()
                    : const Text('Reset Password'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

### Deep Link Routing (Flutter)

```dart
// In main.dart or your router file
import 'package:uni_links/uni_links.dart';

void initDeepLinks() {
  // Deep links received while the app is open
  linkStream.listen((String? link) {
    if (link != null) {
      _handleDeepLink(link);
    }
  });

  // Deep link received while the app was closed
  getInitialLink().then((String? link) {
    if (link != null) {
      _handleDeepLink(link);
    }
  });
}

void _handleDeepLink(String link) {
  final uri = Uri.parse(link);

  if (uri.host == 'reset-password' || uri.path == '/reset-password') {
    final token = uri.queryParameters['token'];
    final email = uri.queryParameters['email'];

    if (token != null && email != null) {
      // Navigate to ResetPasswordScreen
      navigatorKey.currentState?.push(
        MaterialPageRoute(
          builder: (_) => ResetPasswordScreen(
            token: token,
            email: email,
          ),
        ),
      );
    }
  }
}
```

---

## 6. Swift (iOS) Implementation Example

### PasswordResetService

```swift
import Foundation

struct ApiResponse: Codable {
    let success: Bool
    let message: String
    let errors: [String: [String]]?
}

class PasswordResetService {
    let baseURL: String

    init(baseURL: String = "https://api.kolabing.com") {
        self.baseURL = baseURL
    }

    /// Sends the password reset link
    func forgotPassword(email: String) async throws -> ApiResponse {
        let url = URL(string: "\(baseURL)/api/v1/auth/forgot-password")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let body = ["email": email]
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(ApiResponse.self, from: data)
    }

    /// Resets the password
    func resetPassword(
        token: String,
        email: String,
        password: String,
        passwordConfirmation: String
    ) async throws -> ApiResponse {
        let url = URL(string: "\(baseURL)/api/v1/auth/reset-password")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let body: [String: String] = [
            "token": token,
            "email": email,
            "password": password,
            "password_confirmation": passwordConfirmation,
        ]
        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(ApiResponse.self, from: data)
    }
}
```

### ForgotPasswordView (SwiftUI)

```swift
import SwiftUI

struct ForgotPasswordView: View {
    @State private var email = ""
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var emailSent = false
    @Environment(\.dismiss) var dismiss

    private let service = PasswordResetService()

    var body: some View {
        NavigationStack {
            if emailSent {
                VStack(spacing: 16) {
                    Image(systemName: "envelope.badge.fill")
                        .font(.system(size: 64))
                        .foregroundColor(.green)

                    Text("A password reset link has been sent to your email address.")
                        .multilineTextAlignment(.center)
                        .font(.body)

                    Button("Back to Login") {
                        dismiss()
                    }
                    .padding(.top, 16)
                }
                .padding(24)
            } else {
                Form {
                    Section {
                        Text("Enter your email address and we will send you a password reset link.")
                            .font(.footnote)
                            .foregroundColor(.secondary)

                        TextField("Email", text: $email)
                            .textContentType(.emailAddress)
                            .keyboardType(.emailAddress)
                            .autocapitalization(.none)
                    }

                    if let error = errorMessage {
                        Section {
                            Text(error)
                                .foregroundColor(.red)
                                .font(.footnote)
                        }
                    }

                    Section {
                        Button(action: submitForgotPassword) {
                            if isLoading {
                                ProgressView()
                                    .frame(maxWidth: .infinity)
                            } else {
                                Text("Send Reset Link")
                                    .frame(maxWidth: .infinity)
                            }
                        }
                        .disabled(isLoading || email.isEmpty)
                    }
                }
                .navigationTitle("Forgot Password")
            }
        }
    }

    private func submitForgotPassword() {
        isLoading = true
        errorMessage = nil

        Task {
            do {
                let response = try await service.forgotPassword(email: email)
                await MainActor.run {
                    if response.success {
                        emailSent = true
                    } else {
                        errorMessage = response.message
                    }
                    isLoading = false
                }
            } catch {
                await MainActor.run {
                    errorMessage = "An error occurred. Please try again."
                    isLoading = false
                }
            }
        }
    }
}
```

### ResetPasswordView (SwiftUI)

```swift
import SwiftUI

struct ResetPasswordView: View {
    let token: String
    let email: String

    @State private var password = ""
    @State private var passwordConfirmation = ""
    @State private var isLoading = false
    @State private var errorMessage: String?
    @State private var resetSuccess = false
    @Environment(\.dismiss) var dismiss

    private let service = PasswordResetService()

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    Text("Email: \(email)")
                        .font(.footnote)
                        .foregroundColor(.secondary)
                }

                Section {
                    SecureField("New Password", text: $password)
                        .textContentType(.newPassword)

                    SecureField("Confirm Password", text: $passwordConfirmation)
                        .textContentType(.newPassword)
                }

                if let error = errorMessage {
                    Section {
                        Text(error)
                            .foregroundColor(.red)
                            .font(.footnote)
                    }
                }

                Section {
                    Button(action: submitResetPassword) {
                        if isLoading {
                            ProgressView()
                                .frame(maxWidth: .infinity)
                        } else {
                            Text("Reset Password")
                                .frame(maxWidth: .infinity)
                        }
                    }
                    .disabled(isLoading || password.isEmpty || passwordConfirmation.isEmpty)
                }
            }
            .navigationTitle("Set New Password")
            .alert("Success", isPresented: $resetSuccess) {
                Button("OK") {
                    // Navigate to the login screen
                    dismiss()
                }
            } message: {
                Text("Your password has been changed successfully. You can now log in with your new password.")
            }
        }
    }

    private func submitResetPassword() {
        guard password.count >= 8 else {
            errorMessage = "Password must be at least 8 characters"
            return
        }
        guard password == passwordConfirmation else {
            errorMessage = "Passwords do not match"
            return
        }

        isLoading = true
        errorMessage = nil

        Task {
            do {
                let response = try await service.resetPassword(
                    token: token,
                    email: email,
                    password: password,
                    passwordConfirmation: passwordConfirmation
                )
                await MainActor.run {
                    if response.success {
                        resetSuccess = true
                    } else {
                        errorMessage = response.message
                    }
                    isLoading = false
                }
            } catch {
                await MainActor.run {
                    errorMessage = "An error occurred. Please try again."
                    isLoading = false
                }
            }
        }
    }
}
```

### Deep Link Handling (iOS)

```swift
// Inside SceneDelegate or App struct
import SwiftUI

@main
struct KolabingApp: App {
    var body: some Scene {
        WindowGroup {
            ContentView()
                .onOpenURL { url in
                    handleDeepLink(url)
                }
        }
    }

    private func handleDeepLink(_ url: URL) {
        // kolabing://reset-password?token=xxx&email=yyy
        guard url.host == "reset-password" || url.path == "/reset-password" else { return }

        let components = URLComponents(url: url, resolvingAgainstBaseURL: false)
        let token = components?.queryItems?.first(where: { $0.name == "token" })?.value
        let email = components?.queryItems?.first(where: { $0.name == "email" })?.value

        if let token = token, let email = email {
            // Navigate to ResetPasswordView
            // Navigation state management varies depending on your app architecture
        }
    }
}
```

---

## 7. Important Notes

### Token Expiration
- The password reset token is valid for **60 minutes** (Laravel default).
- Expired tokens are automatically rejected.
- It is recommended to inform the user about the token expiration time.

### Throttle (Rate Limiting)
- A new token generation request for the same email cannot be sent within **60 seconds**.
- When the throttle limit is hit, the API returns a `400` status code.
- Enabling the "Resend" button after 60 seconds is a good UX practice in the mobile app.

### Security
- After a successful password reset, **all existing tokens (sessions) are invalidated**.
- This means the user will be logged out from all devices.
- The user will need to log in again after the password reset.

### UX Recommendations
1. **On the Forgot Password screen**, show a confirmation screen after the email has been sent.
2. **Show a timer** - A countdown like "You can resend in 60 seconds".
3. **Password strength indicator** - Add a component that shows password strength (weak/medium/strong).
4. **After a successful reset**, automatically redirect to the login screen.
5. **Display error messages** in a user-friendly manner, avoiding technical details.
6. **On "email not found" errors**, offer the user an option to sign up.
7. **Check for offline status** and inform the user if there is no internet connection.

### For Testing

In the development environment, you can use Laravel's log driver instead of actually sending emails:

```env
MAIL_MAILER=log
```

This way, the password reset token will appear in the `storage/logs/laravel.log` file.
