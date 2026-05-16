# Security policy

## Reporting a vulnerability

If you've found a security issue in this bundle, please email
**ejosterberg@gmail.com** rather than opening a public GitHub
issue. Use a clear subject line beginning with `[opensalestax-sylius
security]`.

You'll get an acknowledgement within 72 hours and a fix
timeline within one week.

## Scope

This bundle's security surface is intentionally minimal:

- **No inbound HTTP** — no webhook receiver, no JWT verifier, no
  authentication tokens to manage. The bundle runs in the
  merchant's Sylius process; whatever code loaded the bundle is
  already trusted.
- **Outbound HTTP only** — the bundle calls the merchant's own
  OpenSalesTax engine via the configured `engine_url`. URL
  scheme is restricted to `http://` and `https://` (parsed +
  validated at container build time).
- **No secrets stored** — the optional `api_key` is read from
  the merchant's environment and forwarded as the `X-API-Key`
  header on outbound engine calls. It is not logged.

## Threat model

The bundle is in scope for:

- SSRF via the engine URL (mitigated by scheme allowlist; the
  merchant is responsible for ensuring the engine URL points at
  their own infrastructure)
- Engine-error propagation (mitigated by `fail_hard: false`
  default — engine errors don't break checkout)
- Injection through cached values (mitigated — the cache key
  shape is constrained to digits + lowercase letters; values
  are decimal-string tax totals, not user input)

The bundle is NOT in scope for:

- Vulnerabilities in Sylius itself
- Vulnerabilities in the OpenSalesTax engine itself (report
  those upstream)
- Vulnerabilities in the `ejosterberg/opensalestax` PHP SDK
  (report those at the SDK repo)

## Supported versions

Pre-1.0: only the latest released version receives security
fixes. Once we ship `v1.0.0`, the latest minor of the current
major and the previous major (`v(N).x` and `v(N-1).x`) will
receive fixes.
