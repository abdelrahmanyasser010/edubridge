# Dashboard Authentication & Lifecycle Documentation

This document describes how to handle login, token storage, and session lifecycles for the EduBridge Dashboard panel.

---

## 1. Login Endpoint Contracts

EduBridge recently refactored its login logic to transition from a single body-based legacy endpoint to app-specific, subdomain-resolved endpoints (specified in decision `ADR-008`).

### ❌ Legacy Login (DEPRECATED & REMOVED)
*   **Path**: `POST /api/v1/auth/login`
*   **Legacy Payload**:
    ```json
    {
      "school_code": "alpha",
      "email": "admin@example.test",
      "password": "secret-password",
      "device_id": "unique-device-uuid",
      "device_name": "Chrome (Windows)",
      "app_type": "dashboard"
    }
    ```
*   **Status**: **Removed from routes/api.php**. All clients must migrate.

---

### 🛡️ Active Dashboard Login (IMPLEMENTED — ADR-008 Target)
This is the **current live endpoint** that the Dashboard frontend must use.

*   **Status**: **IMPLEMENTED** (in production routing)
*   **Path**: `POST /api/v1/dashboard/auth/login`
*   **Routing & Resolution**: Resolves the tenant database automatically based on the request Host/Subdomain (e.g. `http://alpha.edubridge.test`).
*   **Request Body**:
    ```json
    {
      "email": "admin@example.test",
      "password": "secret-password",
      "device_id": "browser-fingerprint-uuid",
      "device_name": "Chrome (Windows)"
    }
    ```
    > [!IMPORTANT]
    > `school_code` and `app_type` are **no longer allowed** in the login request body validation rules.
    > *   `app_type` is determined automatically by the route prefix `/dashboard/`.
    > *   `school_code` is determined by the subdomain. If testing locally on a shared port (e.g. `localhost:8000`), you must pass the school code in the request header `X-School-Code: alpha` or query string `?school_code=alpha` as a fallback.

*   **Success Response (200 OK)**:
    ```json
    {
      "data": {
        "token": "4|sanctum_personal_access_token_value_here",
        "token_type": "Bearer",
        "expires_at": null,
        "user": {
          "id": "1",
          "name": "Admin User",
          "email": "admin@example.test"
        },
        "school": {
          "id": 1,
          "public_id": "d8a1e2f7-...",
          "code": "alpha",
          "name": "Alpha School"
        }
      },
      "meta": {
        "request_id": "01J2V..."
      }
    }
    ```

---

## 2. Token Capabilities & Authorization (ADR-004)

When logging in through `/dashboard/auth/login`, the issued token receives a specific ability: `app:dashboard`.

*   **Server-Side Check**: If the credentials are valid but the user lacks the `school_admin`, `academic_admin`, `student_affairs`, or `finance_officer` role in the central tenant context, the server rejects the request with `403 Forbidden` and error code `APP_ACCESS_DENIED`.
*   **Endpoint Lock**: Every API route inside the dashboard group checks if the token has the `app:dashboard` ability. If a client attempts to access a dashboard endpoint with a teacher token (ability `app:teacher`), the server automatically blocks it with `403 Forbidden`.

---

## 3. Session Lifecycle Management

Once the token is issued, the frontend should manage the following flow:

### 💾 Storage Requirements
Save the following values in secure client storage (e.g., `localStorage` or `sessionStorage` in a SPA):
*   `access_token`: The Bearer token value.
*   `user`: Current user object (name, email, ID).
*   `school`: Current school object (code, name, public ID).
*   `school_code`: Keep track of the active subdomain context.

### 🔄 Header Configuration
Inject the token into all subsequent Axios or Fetch requests:
```http
Authorization: Bearer <token_value>
Accept: application/json
Content-Type: application/json
```

### 🚪 Logout Endpoint
Invalidate the current token session on the server.
*   **Path**: `POST /api/v1/auth/logout`
*   **Required Header**: `Authorization: Bearer <token_value>`
*   **Success Response**: `204 No Content`
*   **Frontend Action**: Clear all stored tokens and redirect to the login screen.

### 💻 List Device Sessions
Get list of all active login sessions for the user.
*   **Path**: `GET /api/v1/auth/device-sessions`
*   **Success Response (200 OK)**:
    ```json
    {
      "data": [
        {
          "id": 1,
          "device_id": "browser-fingerprint-uuid",
          "device_name": "Chrome (Windows)",
          "app_type": "dashboard",
          "platform": "web",
          "last_active_at": "2026-07-21T09:00:00Z"
        }
      ]
    }
    ```

### ❌ Revoking a Specific Session
Allows an admin to terminate another device session (e.g. from another browser or app).
*   **Path**: `POST /api/v1/auth/device-sessions/{deviceSession}/revoke`
*   **Path Parameter**: `deviceSession` (id of session from list)
*   **Success Response**: `204 No Content`
