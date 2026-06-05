# Auth token storage — design decision & tradeoff

**Status:** accepted · **Last reviewed:** 2026-06-05

This document records *why* the SPA stores its auth token the way it does, the
security tradeoff that choice makes, and what migrating to an httpOnly cookie
would involve. It exists so the decision is deliberate and reversible rather
than accidental.

## Current approach

The API authenticates with **Laravel Sanctum personal access tokens** (bearer
tokens), not Sanctum's stateful SPA cookie mode.

- On `POST /login`, `/register`, and after `/reset-password`, the API returns a
  plaintext token (`$user->createToken('api')->plainTextToken`).
- The Nuxt app stores it in a cookie via `useCookie('token', { sameSite: 'lax' })`
  (`web/stores/auth.ts`). This cookie is **not `httpOnly`** — it is readable by
  JavaScript by design.
- Every API request attaches it as an `Authorization: Bearer <token>` header
  (`web/composables/useApi.ts`, `onRequest`). The server authenticates from that
  header — **not** from the cookie being sent automatically.
- Tokens expire server-side after **24h** (`config/sanctum.php` →
  `expiration = 60 * 24`) and are revoked on logout and on password reset.

The cookie is used purely as a convenient persistence slot that survives reload
and is readable during SSR; it is *not* the authentication credential channel.

## The tradeoff

| Concern | Current (JS-readable cookie + Bearer header) | httpOnly cookie |
|---|---|---|
| **CSRF** | ✅ Not exposed. Browsers never auto-attach an `Authorization` header cross-site, and the server ignores the cookie for auth, so a forged cross-site request carries no credential. | ⚠️ Reintroduced. The browser auto-sends the cookie, so requires CSRF tokens and/or strict `SameSite`. |
| **XSS token theft** | ⚠️ **Exposed.** Any script running in the page (an XSS payload or a compromised dependency) can read the token from the cookie / outgoing header and exfiltrate it. | ✅ Mitigated. `httpOnly` hides the token from JavaScript, so XSS cannot read it directly. |
| **Cross-origin setup** | ✅ Simple. SPA (`:3000`) and API (`:8000`) are different origins; bearer tokens work without cookie-domain/SameSite gymnastics or `withCredentials`. | ⚠️ Harder. Needs a shared parent domain or proxy, `SameSite=None; Secure`, CORS `credentials`, and Sanctum stateful-domain config. |
| **Mobile / non-browser clients** | ✅ Same token flow works for any client. | ⚠️ Cookie semantics are browser-centric. |

**The crux:** this approach trades *XSS resistance* for *CSRF immunity and
operational simplicity*. The dominant residual risk is that an XSS foothold can
steal a live token (good for up to 24h).

## Why this approach was chosen

- The frontend and API are deployed as **separate origins**; bearer tokens avoid
  the cookie-domain, `SameSite=None`, and CSRF-token machinery that stateful
  cookie auth requires cross-origin.
- It keeps the API **stateless** and usable by non-browser clients with the same
  contract.
- CSRF — the most common cookie-auth pitfall — is structurally absent.

## Mitigations in place

- **Short token lifetime:** 24h server-side expiration.
- **Revocation:** tokens are deleted on logout and on password reset
  (`AuthController::resetPassword`), so a reset invalidates any stolen token.
- **`SameSite=lax`** on the cookie.
- **Reduced XSS surface:** Vue escapes interpolations by default; the API never
  reflects unsanitized input; inputs are validated server-side.

## Recommended hardening (without changing the model)

- Set **`secure: true`** on the token cookie in production (HTTPS only). *(Not
  yet applied — `web/stores/auth.ts` sets only `sameSite`.)*
- Add a **Content-Security-Policy** header to shrink the XSS exfiltration surface.
- Consider shortening the token TTL and/or adding refresh-on-activity.

## If we later migrate to an httpOnly cookie

This is the path **not** taken; recorded so it can be picked up deliberately:

1. Switch to **Sanctum SPA (stateful) mode**: `EnsureFrontendRequestsAreStateful`
   middleware, `SANCTUM_STATEFUL_DOMAINS`, and a shared site/domain or reverse
   proxy so SPA and API are same-site.
2. Authenticate with the session cookie (httpOnly, secure, `SameSite`); drop the
   `Bearer` header injection in `useApi.ts`.
3. Add CSRF protection: fetch `/sanctum/csrf-cookie` and send the
   `X-XSRF-TOKEN` header on mutating requests.
4. Configure CORS with `supports_credentials = true` and the SPA origin; set
   `credentials: 'include'` on `$fetch`.
5. Remove the client-side `token` cookie and the `token` from auth responses.

Until then, the bearer-token approach above is the accepted design.
