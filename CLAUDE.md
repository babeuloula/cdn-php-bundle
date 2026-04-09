# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
make install    # Install Docker stack + Composer dependencies
make check      # Run all checks (lint + analyse + security)
make lint       # PHP Code Sniffer (PHPCS) — code style check
make fixer      # Auto-fix code style with PHPCBF
make analyse    # PHPStan static analysis (level 8)
make security   # Audit dependencies for CVEs
make shell      # Connect to PHP container
```

There are no unit tests in this project — quality is enforced through static analysis (PHPStan level 8) and code style (PHPCS).

## Architecture

This is a Symfony Bundle that proxies image requests through [CDN PHP](https://github.com/babeuloula/cdn-php), with optional local fallback processing via Intervention Image.

**Request flow:**
1. A Twig template calls `{{ cdn_php('image.jpg', {w: 200, h: 200}) }}`
2. `ProxyExtension` builds an `Options` value object, optionally signs it via `Signer`, and generates a URL pointing to a configured Symfony route
3. The application's controller receives the request and calls `Proxy::response()`
4. `Proxy` optionally checks the local file exists (`check_assets`), then fetches from the remote CDN PHP service via `HttpClientInterface`
5. If the CDN PHP call fails, `FallbackHandlerInterface` (default: `InterventionImageFallbackHandler`) processes the image locally and caches it for 14 days
6. Response headers (cache-control, etag, content-type, etc.) are copied to the returned `Response`

**Key classes:**

| Class                              | Role                                                                                                        |
|------------------------------------|-------------------------------------------------------------------------------------------------------------|
| `CdnPhpBundle`                     | Bundle entry point; defines configuration tree and loads services                                           |
| `Proxy`                            | Core service; orchestrates CDN fetch + fallback                                                             |
| `AbstractHandler`                  | Base class; normalizes asset paths, parses request headers                                                  |
| `Options`                          | Value object for image transformation parameters (w, h, watermark, signature)                               |
| `Signer`                           | Dual signing: SHA1 (app→browser, paramètre `signature`) + HMAC-SHA256 (app→CDN, paramètres `expires`+`sig`) |
| `InterventionImageFallbackHandler` | Local Intervention Image v3 processing when CDN is unavailable                                              |
| `ProxyExtension`                   | Twig functions `cdn_php()` and `cdn()`                                                                      |

**Configuration (config/packages/cdn_php.yaml):**
```yaml
cdn_php:
  proxy:
    assets_path: '/path/to/assets'   # Local assets directory (required)
    url: 'https://cdn.example.com'   # CDN PHP service URL (required)
    check_assets: true               # Validate local file before CDN request
    encrypted_parameters: false      # Enable HMAC query parameter signing
  encrypter:
    secret_key: null                 # Signs Twig-generated URLs (app→browser). Required when encrypted_parameters: true
    cdn_secret_key: null             # Signs Proxy requests to CDN PHP (app→CDN). Must match CDN's SIGNATURE_SECRET
    cdn_expires_ttl: 3600            # Validity of CDN signatures in seconds (default: 1 hour)
  twig:
    route_name: 'app_cdn'            # Symfony route to the proxy controller (required)
    route_parameter: 'path'          # Route parameter name for the file path (required)
```

**Supported PHP:** >=8.1 | **Supported Symfony:** 6, 7, 8
