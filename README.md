# identity-link-bff

Backend-for-frontend (BFF) that handles OIDC login, stores the user session, and proxies requests to backend services while attaching the access token.

## Quick start

Configure environment variables (use `.env.local` for local overrides):

```
OIDC_WELL_KNOWN_URL=https://example.com/.well-known/openid-configuration
OIDC_CLIENT_ID=...
OIDC_CLIENT_SECRET=...
PROXY_SERVICE_BASE_URLS='{"users":"https://example.com/users"}'
PROXY_TLS_VERIFY=1
```

## Auth flow

- User hits the BFF first (e.g. `/protected`), which triggers OIDC login.
- On success, the BFF stores the session and redirects to the frontend.
- The frontend calls the BFF for API requests; the BFF attaches the access token.

## OIDC redirect URI

The OIDC callback path is `/login_check`. Register this exact URL with the IdP, e.g.
`https://ui.example.com/bff/login_check`.

## Notes

- `.env` contains only non-secret defaults. Use `.env.local` for local secrets.
