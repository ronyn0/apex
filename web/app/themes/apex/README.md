# Apex theme

A minimal dark blog theme with no parent theme. Classic PHP templates; colors and type sizes live in [theme.json](theme.json) so the block editor matches the front end.

| File | Renders |
| --- | --- |
| `index.php` | Blog home, archives, search results |
| `singular.php` | Single posts and pages |
| `404.php` | WordPress's own "not found" page |
| `inc/error-pages.php` | The `/error/<code>/` route for the reverse proxy |

## Setup

1. Activate it: **Appearance → Themes → Apex** (or `wp theme activate apex`). Activating also registers the `/error/` URL.
2. Add a menu under **Appearance → Menus** and assign it to **Primary**. With no menu, the header shows just the site name.

If `/error/502/` ever returns the blog home page instead of the error page, the URL rules need refreshing: **Settings → Permalinks → Save**, or `wp rewrite flush`. `/?apex_error=502` always works, even without the URL rules.

## Error pages for other apps

`https://<blog>/error/<code>/` returns a themed page **with that HTTP status**. The page has no-cache and noindex headers, and a `Retry-After` header for 502–504. There's specific wording for 400, 401, 403, 404, 500, 502, 503 and 504. Any other 4xx/5xx code gets a generic message.

Point each app's reverse-proxy config at it. Replace `blog.example.com` with the blog's hostname.

### nginx / Nginx Proxy Manager

Put this inside the app's `server { }` block. In Nginx Proxy Manager, paste it into the proxy host's **Advanced** tab.

```nginx
error_page 502 /__apex_error/502/;
error_page 503 /__apex_error/503/;
error_page 504 /__apex_error/504/;

location ^~ /__apex_error/ {
    internal;
    rewrite ^/__apex_error/(\d{3})/$ /error/$1/ break;
    proxy_pass https://blog.example.com;
    proxy_set_header Host blog.example.com;
    proxy_ssl_server_name on;
}
```

This covers the errors nginx generates itself: the app is down, refusing connections or timing out. To also replace error pages the app sends itself (its own 500 page, say), add `proxy_intercept_errors on;` and the codes you want to replace. Don't do this for apps with an API, because it replaces their JSON error bodies too.

### Caddy

```caddy
app.example.com {
    reverse_proxy 10.0.0.20:8080

    handle_errors 502 503 504 {
        rewrite * /error/{err.status_code}/
        reverse_proxy https://blog.example.com {
            header_up Host {upstream_hostport}
        }
    }
}
```

### Traefik

```yaml
http:
  middlewares:
    apex-errors:
      errors:
        status: ["502-504"]
        service: apex-blog
        query: "/error/{status}/"
  services:
    apex-blog:
      loadBalancer:
        servers:
          - url: "https://blog.example.com"
```

Then add `apex-errors` to each app router's `middlewares`.

### Redirects (302)

A 302 is a normal redirect, not an error, so the proxy has to pass it through for the browser to follow. That's how Authentik's forward-auth login works, for example. If a redirect lands on a host that's down, the request that fails there gets the themed 502/504 page from that host's proxy config.

## Testing

```sh
curl -sI https://blog.example.com/error/502/   # expect: HTTP/2 502, Retry-After: 30
```

To see the page through the proxy, stop an app's container or VM and load its address.
