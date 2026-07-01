# Identity Link BFF

![License](https://img.shields.io/github/license/sgoranov/identity-link-bff)
![Last Commit](https://img.shields.io/github/last-commit/sgoranov/identity-link-bff)
![Issues](https://img.shields.io/github/issues/sgoranov/identity-link-bff)
[![PHPUnit Tests](https://github.com/sgoranov/identity-link-bff/actions/workflows/phpunit.yml/badge.svg)](https://github.com/sgoranov/identity-link-bff/actions/workflows/phpunit.yml)
[![Security Audit](https://github.com/sgoranov/identity-link-bff/actions/workflows/vulnerability-scan.yml/badge.svg)](https://github.com/sgoranov/identity-link-bff/actions/workflows/vulnerability-scan.yml)

Backend-for-frontend (BFF) that handles OIDC login, stores the user session, and proxies requests to backend services while attaching the access token.

## Quick start

Configure environment variables (use `.env.local` for local overrides):

```
OIDC_WELL_KNOWN_URL=https://example.com/.well-known/openid-configuration
OIDC_CLIENT_ID_FILE=...
OIDC_CLIENT_SECRET_FILE=...
PROXY_SERVICE_BASE_URLS='{"users":"https://example.com/users"}'
HTTP_CLIENT_SSL_VERIFYPEER=true
HTTP_CLIENT_SSL_VERIFYHOST=2
```

## Auth flow

- User hits the BFF first, which triggers OIDC login.
- On success, the BFF stores the session and redirects to the frontend.
- The frontend calls the BFF for API requests; the BFF attaches the access token.

## OIDC redirect URI

The OIDC callback path is `/login_check`. Register this exact URL with the IdP, e.g.
`https://ui.example.com/bff/login_check`.

## License

Identity Link is open source software licensed under the [MIT License](LICENSE), which permits reuse,
modification, and distribution with minimal restrictions.