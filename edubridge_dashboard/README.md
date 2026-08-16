# EduBridge Dashboard

Next.js 16 dashboard connected to the EduBridge Laravel multi-tenant API.

## Local setup

1. Add this line to `C:\Windows\System32\drivers\etc\hosts`:

```text
127.0.0.1 alpha.edubridge.test
```

2. Run the backend on port 8000.
3. Copy `.env.example` to `.env.local` if needed. The supplied local file already points to:

```text
NEXT_PUBLIC_API_BASE_URL=http://alpha.edubridge.test:8000/api/v1
NEXT_PUBLIC_API_TIMEOUT_MS=15000
```

4. Install and run the dashboard:

```bash
npm install
npm run dev
```

For production change only `NEXT_PUBLIC_API_BASE_URL` to the deployed tenant API URL and build normally.

## Authentication

The login page sends email/password plus a stable installation UUID generated automatically by the browser. It does not ask the user for a school code or role. The server derives the app context from `/dashboard/auth/login`, resolves the tenant from the host, and `/auth/me` provides the active role and permissions.

## Integration notes

See `docs/API_INTEGRATION_STATUS.md` for the live modules and the few backend data gaps that are intentionally shown as unavailable instead of mock values.
