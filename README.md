# Tahmir AI Free — HTML to WordPress & Lovable to WordPress

**Import your HTML or AI-built website into WordPress for free.** Tahmir AI Free converts supported public website URLs and HTML ZIPs into WordPress pages while preserving supported front-end layouts and assets.

[Visit Tahmir.com](https://tahmir.com/) · [HTML to WordPress](https://tahmir.com/html-to-wordpress-converter-tahmir-ai/) · [Lovable to WordPress](https://tahmir.com/lovable-to-wordpress-converter-tahmir-ai/) · [AI website to WordPress](https://tahmir.com/ai-website-to-wordpress-converter-tahmir-ai/)

This public repository contains **the Free edition only**, licensed under **GPL-2.0-or-later**. No Tahmir license, paid subscription, or AI API key is needed for the free import workflow. The separate Tahmir AI Pro add-on is distributed through [Tahmir.com](https://tahmir.com/).

## What you can do for free

- **HTML to WordPress:** upload an HTML website ZIP with supported CSS, JavaScript, images, fonts and linked pages.
- **Lovable to WordPress:** import a public deployed Lovable website or supported built HTML export.
- **AI website to WordPress:** bring supported public pages or static exports made with Bolt, Claude, Cursor, v0 and other AI website tools into WordPress.
- Clone supported public website URLs, review discovered pages and import selected pages.
- Display imported pages and manage them from the Tahmir workspace.
- Accept submissions from supported imported contact forms and notify the WordPress site administrator.

Import only sites and assets you own or have permission to use. React/Vite source code must be built and deployed first. Logins, databases, server functions, payments, external APIs and other backend behavior need their own integration. Import fidelity depends on the source; test routes, responsive layouts, scripts and forms on your actual site.

## Download and install

1. Download the installable `tahmir-ai-free-1.0.7.zip` from [Releases](https://github.com/hireawais/tahmir-ai-free/releases/latest).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it and activate **Tahmir AI — AI to WP Website Converter**.
4. Open **Tahmir AI → Import** and choose a public URL or HTML ZIP.
5. Review the imported pages, check links and forms, and publish when ready.

Requires WordPress 6.2+ and PHP 8.1+. ZIP importing requires PHP's ZIP extension; HTML parsing requires DOM. For a source checkout, place this repository in `wp-content/plugins/tahmir-ai-to-wp-converter/` and activate it. Pro recognizes that folder name, so future upgrades can reuse your imported pages and form data.

## Conversion guides

| Workflow | Free repository guide | Official Tahmir guide |
| --- | --- | --- |
| HTML, CSS and JavaScript to WordPress | [HTML to WordPress](docs/html-to-wordpress.md) | [HTML converter](https://tahmir.com/html-to-wordpress-converter-tahmir-ai/) |
| Lovable website to WordPress | [Lovable to WordPress](docs/lovable-to-wordpress.md) | [Lovable converter](https://tahmir.com/lovable-to-wordpress-converter-tahmir-ai/) |
| Other AI-generated websites | Use a public deployed URL or supported built HTML ZIP | [AI website converter](https://tahmir.com/ai-website-to-wordpress-converter-tahmir-ai/) |

## Free and Pro

| Capability | Free | Separate Pro add-on |
| --- | --- | --- |
| Public URL import and HTML ZIP import | Included | Included |
| Imported page display and management | Included | Included |
| Supported imported contact-form submissions | Included | Included |
| Visual editor and page modules | — | Included |
| Create/edit forms, view entries and configure integrations | — | Included |
| Detect/replace site logos and edit shared menus | — | Included |
| AI editing, SEO/AI search, analytics and client delivery | — | Included |
| License required | No | Yes |

Explore the complete product at [Tahmir.com](https://tahmir.com/) and [compare Free and Pro](https://tahmir.com/#pricing). Pro installs alongside Free. Provider configuration or credits may be needed for optional AI services.

## Privacy and external services

Imports fetch the website URL and supported asset URLs you select. Free update checks contact Tahmir's release service with the installed version. Optional installation usage sharing is off unless you consent; it sends a random installation ID, edition/version, WordPress/PHP versions and an activity signal. It does not send site URLs, page content, visitors or form entries. You can change your choice in **Usage sharing**. See [Tahmir's privacy policy](https://tahmir.com/privacy-policy-tahmir-ai/).

## Contributing and license

Use [GitHub Issues](https://github.com/hireawais/tahmir-ai-free/issues) for reproducible Free-edition bugs. Include your WordPress/PHP versions and a minimal sample you have permission to share. Keep credentials, customer content and Pro files out of issues and pull requests.

The Free edition is GPLv2 or later; see [LICENSE.txt](LICENSE.txt). Bundled icon/font assets retain their own licenses and attribution in [third-party.txt](third-party.txt). This GitHub release does not imply acceptance into the WordPress.org plugin directory.
