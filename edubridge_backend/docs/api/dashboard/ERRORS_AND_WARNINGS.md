# EduBridge Error Catalog & Non-Error States

This document details all application-level errors, warning states, and empty states that the Dashboard frontend must handle.

---

## 1. Centralized Error Catalog

| HTTP Status | Custom Code | Meaning / Scenario | Backend Message | Suggested Dashboard Action |
| :--- | :--- | :--- | :--- | :--- |
| **401 Unauthorized** | `UNAUTHENTICATED` | Token is invalid, expired, revoked, or missing. | "Unauthenticated." / "A school-scoped token is required." | Redirect to `/login`, clear local token/session storage. |
| **403 Forbidden** | `APP_ACCESS_DENIED` | Credentials are correct, but the user lacks an authorized role for this app (Dashboard). | "Access denied for this application context." | Show "Access Denied" page/alert; do not proceed. |
| **403 Forbidden** | `FORBIDDEN` | The user lacks the specific permission (e.g. `academic.manage`) for this endpoint. | "This action is unauthorized." | Toast error or show `403 Forbidden` inline warning. |
| **404 Not Found** | `NOT_FOUND` | The requested resource (or route) does not exist. | "The requested resource was not found." | Show `404 Not Found` graphic or redirect to dashboard index. |
| **405 Method Not Allowed** | `METHOD_NOT_ALLOWED` | Wrong HTTP method used for the route. | "Method Not Allowed" | Log/Report frontend routing bug. |
| **409 Conflict** | `CONFLICT` | State conflict (e.g. Idempotency Key collision, duplicate unique record). | "Conflict" | Display Toast showing the conflict; reload resources. |
| **422 Unprocessable** | `VALIDATION_FAILED` | Input validation failed. | "The given data was invalid." | Render inline validation errors under inputs. |
| **429 Too Many Requests** | `RATE_LIMITED` | Too many requests sent in a short window. | "Too many login attempts. Please try again in 60 seconds." | Disable submit buttons and show countdown/retry message. |
| **500 Server Error** | `SERVER_ERROR` | An unhandled exception occurred on the server. | "Server Error" | Display a global "Something went wrong" maintenance banner. |

---

## 2. Non-Error States & Empty States

A successful API request can sometimes return an empty result or a warning. The Dashboard must present these states gracefully instead of showing errors.

### A. Empty List / Collection (Empty State)
*   **Trigger**: No records exist for the selected filters or search terms.
*   **HTTP Status**: `200 OK`
*   **Response Body**:
    ```json
    {
      "data": [],
      "links": {
        "first": "http://alpha.edubridge.test/api/v1/students?page=1",
        "last": "http://alpha.edubridge.test/api/v1/students?page=1",
        "prev": null,
        "next": null
      },
      "meta": {
        "current_page": 1,
        "from": null,
        "last_page": 1,
        "path": "http://alpha.edubridge.test/api/v1/students",
        "per_page": 25,
        "to": null,
        "total": 0,
        "request_id": "01J2V..."
      }
    }
    ```
*   **Suggested Handling**: Do not show a raw JSON empty array. Display a friendly "No results found" graphic with a button to clear active filters or add a new record.

### B. School Onboarding Lookup Failure
*   **Trigger**: Scanned QR/invitation token is invalid or expired.
*   **HTTP Status**: `422 Unprocessable Content`
*   **Response Body (Invalid)**:
    ```json
    {
      "message": "Invalid invitation token.",
      "code": "INVALID_TOKEN",
      "errors": {},
      "meta": { "request_id": "01J2V..." }
    }
    ```
*   **Response Body (Expired)**:
    ```json
    {
      "message": "Expired invitation token.",
      "code": "EXPIRED_TOKEN",
      "errors": {},
      "meta": { "request_id": "01J2V..." }
    }
    ```
*   **Suggested Handling**: Show a distinct alert informing the user that the code has expired or is invalid, prompting them to contact school administration.

### C. Deprecated Endpoint Call
*   **Trigger**: The frontend attempts to access a legacy or deprecated route.
*   **HTTP Status**: `200 OK` or `204 No Content`
*   **Response Header**:
    ```http
    Warning: 299 - "This endpoint is deprecated and will be removed in a future release."
    ```
*   **Suggested Handling**: Log a console warning for developers during integration testing to identify endpoints that need migration.
