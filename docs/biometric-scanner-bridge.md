# Biometric Scanner Bridge Contract

This document describes the application-to-device bridge boundary for GOFMIS.

GOFMIS (Filament/domain) never talks directly to scanner vendor SDKs, USB/WebUSB
APIs, browser plugins, or vendor binaries. It depends only on
`FingerprintDeviceClientInterface`. This keeps the core vendor-neutral.

## Architecture

```
GOFMIS server/browser
        |
        | FingerprintDeviceClient
        |
        +----------------------------+
        |                            |
MockFingerprintDeviceClient     HttpBiometricBridgeClient
(test/dev)                      (real bridge)
                                        |
                                        | HTTP on trusted local boundary
                                        |
                                 Scanner Bridge Service
                                        |
                                 Vendor SDK / Scanner
                                        |
                                 Physical Device
```

## Client selection

`FingerprintDeviceClientInterface` is resolved from the service container.

- `BIOMETRICS_CLIENT=mock` -> `MockFingerprintDeviceClient` (dev/test, no hardware).
- `BIOMETRICS_CLIENT=http` -> `HttpBiometricBridgeClient` (production, real bridge).

Any other value fails closed with a clear error; the container never silently
falls back to the mock client in production.

## Bridge HTTP API contract (versioned)

The `HttpBiometricBridgeClient` targets:

```
GET  /api/v1/health
POST /api/v1/fingerprints/capture
```

### Capture request

A capture/enroll request sends only what the scanner needs — never beneficiary PII
(name, NIN, reg no, date of birth, address, family details).

```json
{
  "finger_position": "RIGHT_THUMB",
  "request_id": "<uuid-correlation-id>"
}
```

`request_id` is a unique correlation id. Its purpose is to correlate a capture
with a single human action and (where the bridge supports idempotency) to make a
retried request recognizable. `HttpBiometricBridgeClient` does not blindly retry
capture POSTs.

### Capture response

```json
{
  "success": true,
  "template": "...",
  "template_format": "iso_iec_19794_2",
  "quality_score": 85,
  "device": {
    "vendor": "...",
    "model": "...",
    "serial": "..."
  }
}
```

The client validates the response as untrusted input before it is persisted:
template must be present, non-empty, within `BIOMETRICS_MAX_TEMPLATE_BYTES`
(default 65536), the quality score numeric and in range, and the format
recognized (raw / iso_iec_19794_2 / ansi_incits_378 / wsq / mock_format). The
result is mapped to the canonical domain value used by `FingerprintsRelationManager`
and encrypted via the PB-NEXT-02A dedicated biometric key.

## Configuration

All bridge settings come from trusted server configuration. They must never be
derived from request input, Livewire, or beneficiary fields (no SSRF surface,
no user-selectable scanner URL).

```
BIOMETRICS_CLIENT=mock               # or 'http' in production
BIOMETRICS_BRIDGE_URL=http://127.0.0.1:8787
BIOMETRICS_BRIDGE_TOKEN=             # optional bearer token, isolated from APP_KEY
BIOMETRICS_BRIDGE_CONNECT_TIMEOUT=3  # seconds
BIOMETRICS_BRIDGE_TIMEOUT=30         # seconds (interactive capture)
BIOMETRICS_MAX_TEMPLATE_BYTES=65536  # max template payload size
```

- The bridge should normally bind to localhost or a trusted private
  workstation/LAN endpoint.
- The bridge auth token is dedicated and must never reuse `APP_KEY` or
  `BIOMETRICS_ENCRYPTION_KEY`.
- TLS verification is never disabled in production. If HTTPS is used, normal
  certificate validation remains enabled.

## Error handling

Failures are mapped to safe, distinct exception types:

- `BridgeUnavailableException` — bridge unreachable.
- `CaptureTimeoutException` — capture timed out.
- `ScannerOperationException` — disconnected / busy / cancelled.
- `LowQualityCaptureException` — poor fingerprint quality.
- `InvalidBridgeResponseException` — auth failure / malformed / missing / empty /
  oversized / invalid-quality / unsupported-format.

User-facing messages are safe and actionable (e.g. "Fingerprint scanner is
unavailable.", "Fingerprint capture timed out. Please try again."). Templates,
tokens, keys, and raw bridge bodies are never shown or logged.

## Future hardware adapter (deferred)

Physical scanner integration is a later POC to be performed on the target
workstation/OS. The proprietary SDK belongs at the bridge/device boundary, not in
GOFMIS core. No claim of physical scanner certification is made by this slice.

Planned deployment direction (NOT yet implemented):

```
GOFMIS server/browser
        -> HttpBiometricBridgeClient
        -> local scanner bridge
        -> SecuGen SDK
        -> Hamster Pro 20 / approved scanner
```

## Boundary of PB-NEXT-02B

- Application-to-bridge HTTP contract — complete.
- Mock client — complete (no hardware needed for tests).
- Physical scanner / vendor SDK POC — deferred (requires a later POC).
- Verification / identification — owned by a later slice.