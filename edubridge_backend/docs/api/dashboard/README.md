# EduBridge Dashboard API Integration Guide

Welcome to the official integration documentation for the **EduBridge Dashboard**. This directory contains the complete contract and integration rules extracted directly from the EduBridge backend source code.

## 📁 Documentation Structure

This directory is organized into the following files:

1.  **[README.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/README.md)** (This file) - General architecture and connection settings.
2.  **[AUTHENTICATION.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/AUTHENTICATION.md)** - Details the login lifecycle, device session tracking, and the transition from the legacy contract to the new subdomain-based endpoints.
3.  **[ENDPOINTS.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/ENDPOINTS.md)** - Comprehensive list of every dashboard route, its request structure, parameters, rules, and example responses.
4.  **[ERRORS_AND_WARNINGS.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/ERRORS_AND_WARNINGS.md)** - Centralized catalog of HTTP statuses, custom application error codes, and frontend handling recommendations.
5.  **[ROLES_AND_PERMISSIONS.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/ROLES_AND_PERMISSIONS.md)** - Role matrices and how dashboard routing scopes permissions.
6.  **[INTEGRATION_MATRIX.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/INTEGRATION_MATRIX.md)** - Frontend screens mapping to backend route readiness.
7.  **[MISSING_APIS.md](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/MISSING_APIS.md)** - Planned/Missing backend features.
8.  **[openapi-dashboard-v1.yaml](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/openapi-dashboard-v1.yaml)** - OpenAPI 3.1 contract.
9.  **[EduBridge-Dashboard.postman_collection.json](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/EduBridge-Dashboard.postman_collection.json)** - Executable Postman collection for local testing.
10. **[EduBridge-Local.postman_environment.json](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/EduBridge-Local.postman_environment.json)** - Local environment file.
11. **[EduBridge-Production.example.postman_environment.json](file:///d:/android%20tog/edubridge_backend/docs/api/dashboard/EduBridge-Production.example.postman_environment.json)** - Production environment template.

---

## 🌐 Environment Configurations

### Local Development (Host-based / Header Fallback)
The API runs on local ports. In multi-tenant setups, you must map subdomains in your local `hosts` file or pass headers if accessing via a direct IP:

*   **API Base URL**: `http://alpha.edubridge.test/api/v1`
*   **Alternative Base URL**: `http://localhost:8000/api/v1` (requires sending the header `X-School-Code: alpha` or query parameter `school_code=alpha` if subdomains are not mapped).

### Production
The dashboard panel interacts with the resolved production base URL mapping to each tenant's custom domain or wildcard subdomain:

*   **API Base URL**: `https://{school_subdomain}.api.edubridge.com/api/v1`
*   **Custom Domain API**: `https://api.{school-custom-domain}.com/api/v1`

---

## ✉️ Standard Response Envelopes

Every API endpoint returns a standard JSON wrapper to make parsing predictable.

### 1. Success Response (Single Item)
```json
{
  "data": {
    "id": "1",
    "name": "Sarah Connor"
  },
  "meta": {
    "request_id": "01J2V..."
  }
}
```

### 2. Success Response (Collection / List)
```json
{
  "data": [
    {
      "id": "1",
      "name": "Sarah Connor"
    }
  ],
  "links": {
    "first": "http://alpha.edubridge.test/api/v1/students?page=1",
    "last": "http://alpha.edubridge.test/api/v1/students?page=1",
    "prev": null,
    "next": null
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 1,
    "path": "http://alpha.edubridge.test/api/v1/students",
    "per_page": 25,
    "to": 1,
    "total": 1,
    "request_id": "01J2V..."
  }
}
```

### 3. Error Response
```json
{
  "message": "The given data was invalid.",
  "code": "VALIDATION_FAILED",
  "errors": {
    "email": ["The email field is required."]
  },
  "meta": {
    "request_id": "01J2V..."
  }
}
```
*   `message`: Translatable user-facing error message (do not use for program logic).
*   `code`: Stable machine-readable code (e.g. `VALIDATION_FAILED`, `APP_ACCESS_DENIED`, `UNAUTHENTICATED`).
*   `errors`: Key-value map of validation rules that failed. Returns an empty object (`{}`) if there are no sub-errors.
*   `meta.request_id`: ULID generated for each request to aid in logs correlation.
