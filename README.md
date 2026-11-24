# Tahmir AI Free — HTML / Lovable to WordPress Converter Plugin

**A free WordPress plugin to convert supported HTML websites, Lovable sites and AI-built pages into WordPress.** Import a public website URL or HTML ZIP, review the result, and publish on your own WordPress hosting. No Tahmir license, paid subscription or AI API key is needed for Free imports.

[Download the Free plugin](https://github.com/hireawais/tahmir-ai-free/releases/latest) · [Visit Tahmir.com](https://www.tahmir.com/) · [Upgrade to Pro](https://www.tahmir.com/upgrade/) · [Discounted upgrade offers](docs/pro-upgrade-offers.md)

This public repository contains **Tahmir AI Free only**, licensed under **GPL-2.0-or-later**. The paid Pro add-on is distributed separately through Tahmir.com.

## Choose your conversion workflow

| Your starting point | Free plugin workflow | Practical guide | Official Tahmir resource |
| --- | --- | --- | --- |
| HTML, CSS and JavaScript website | Import a supported HTML ZIP and its assets | [HTML to WordPress](docs/html-to-wordpress.md) | [Free HTML to WordPress converter](https://www.tahmir.com/free-html-to-wordpress-converter/) |
| Published Lovable website | Import a supported deployed public URL | [Lovable to WordPress](docs/lovable-to-wordpress.md) | [Lovable to WordPress converter](https://www.tahmir.com/lovable-to-wordpress-converter/) |
| GoHighLevel website or funnel page | Import supported public-facing pages, then reconnect integrations | [GoHighLevel to WordPress](docs/gohighlevel-to-wordpress.md) | [GoHighLevel to WordPress converter](https://www.tahmir.com/gohighlevel-to-wordpress-converter/) |
| AI-built landing page or marketing site | Use a deployed URL or built static HTML ZIP | [AI to WordPress](docs/ai-to-wordpress.md) | [AI website to WordPress converter](https://www.tahmir.com/ai-website-to-wordpress-converter/) |
| Approved client website | Import, compare, test and hand over the WordPress site | [Client website delivery checklist](docs/deliver-client-websites.md) | [Tahmir's agency delivery playbook](https://www.tahmir.com/ai-to-wordpress-agency-playbook/) |
| HTML website that needs everyday visual edits | Import with Free; add licensed Pro for visual editing | [Make HTML websites editable](docs/make-html-websites-editable.md) | [Pro editing and delivery tools](https://www.tahmir.com/upgrade/) |

## What is included for free?

- **HTML to WordPress conversion:** upload supported HTML pages with CSS, JavaScript, images, fonts and local assets.
- **Public URL import:** discover supported pages, review them and import selected pages into WordPress.
- **Lovable and AI website imports:** use the deployed front end or a supported built HTML export from Lovable, Bolt, Claude, Cursor, v0 and similar tools.
- **GoHighLevel to WordPress page migration:** use the published website or funnel page you own; review supported page content and assets after import.
- **Imported page display and management:** keep the converted pages in your WordPress site and manage them from the Tahmir workspace.
- **Supported imported contact forms:** accept submissions and notify the WordPress site administrator.

Start with one representative page before importing a whole website. Test desktop and mobile layouts, navigation, images, scripts and forms on your actual hosting.

## Download and install the free converter plugin

1. Download `tahmir-ai-free-1.0.10.zip` from [GitHub Releases](https://github.com/hireawais/tahmir-ai-free/releases/latest).
2. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the ZIP, install it and activate **Tahmir AI Free — HTML & Lovable to WordPress Converter**.
4. Open **Tahmir AI → Import** and choose a public URL or HTML ZIP.
5. Review the imported pages, check links and forms, and publish when ready.

Requires WordPress 6.2+ and PHP 8.1+. ZIP importing requires PHP's ZIP extension; HTML parsing requires DOM. For a source checkout, use the `wp-content/plugins/tahmir-ai-to-wp-converter/` folder. Pro recognizes that folder so your imported pages and form data can carry over.

## Why upgrade to Tahmir AI Pro?

Free gets supported pages into WordPress. **Pro adds the editing and delivery tools for the next stage of client work:**

- **Make HTML and AI websites editable:** update text, images, buttons and page sections visually with the Pro editor and page modules.
- **Keep branding and navigation consistent:** detect and replace logos and manage shared menus from **Manage**.
- **Manage leads:** create and edit forms, review stored entries, and configure notifications and integrations.
- **Prepare pages for search:** use SEO and AI-search tools to review page details before launch.
- **Refine content and design:** use optional AI-assisted tools, with provider configuration or credits where required.
- **Deliver and maintain client websites:** use client delivery, analytics and experiments to support review and ongoing work.

[**See Pro benefits and upgrade →**](https://www.tahmir.com/upgrade/)

Looking for discounted Tahmir AI pricing? Read the [Free-download upgrade offers and eligibility](docs/pro-upgrade-offers.md). Eligible identified Free downloaders receive a personal, time-limited offer; GitHub downloads do not automatically enroll you. Current plan terms and your personal checkout determine availability and price.

Pro installs alongside Free and needs an active license. Keep Free installed; upgrading does not require importing your pages again. This repository and its downloadable ZIP contain no paid Pro add-on.

| Capability | Free | Separate licensed Pro |
| --- | --- | --- |
| Public URL import and HTML ZIP import | Included | Included |
| Imported page display and management | Included | Included |
| Supported imported contact-form submissions | Included | Included |
| Visual editing and page modules | — | Included |
| Logo detection/replacement and shared-menu editing | — | Included |
| Create/edit forms, view entries and integrations | — | Included |
| AI, SEO, analytics and client delivery tools | — | Included |

## Common conversion questions

### Can I convert a GoHighLevel funnel to WordPress?

Use the public URL of a website or funnel page you control and test a representative page first. Tahmir Free imports supported front-end content; it does not migrate the GoHighLevel CRM, contacts, workflows, pipelines, membership accounts, checkout logic or appointments. Reconnect and test forms, embeds, calendars and tracking separately. See the [GoHighLevel migration guide](docs/gohighlevel-to-wordpress.md).

### Does Free make an HTML website visually editable?

Free imports, displays and manages supported pages. The Tahmir visual editor, AI editing, logo replacement and shared-menu editing require Pro. Follow [Make HTML websites editable](docs/make-html-websites-editable.md) for the Free-to-Pro workflow. Imported layouts are not automatically converted into Gutenberg blocks or Elementor widgets.

### Can I deliver an AI-built website to a client?

Yes: use Free to import supported public-facing pages into the client's WordPress hosting, then complete the [client delivery checklist](docs/deliver-client-websites.md). Choose Pro when the scope includes visual content editing, advanced forms or Tahmir's client delivery tools. Hosting, domains, backups and ongoing support are separate responsibilities.

### Can I upload a Lovable, React or Vite source-code ZIP?

Build and deploy the front end first, or provide a supported static HTML export. Unbuilt source repositories, Supabase data, authentication, server functions, payments and external APIs need separate development or integration. See [Lovable to WordPress](docs/lovable-to-wordpress.md) and [AI to WordPress](docs/ai-to-wordpress.md).

## Privacy, support and license

Imports fetch the website URL and supported assets you select. Free update checks contact Tahmir's release service with the installed version. Optional installation usage sharing is off until you consent; it sends a random installation ID, edition/version, WordPress/PHP versions and an activity signal. It does not send site URLs, page content, visitors or form entries. Change your choice in **Usage sharing**. Read [Tahmir's privacy policy](https://www.tahmir.com/tahmir-ai-privacy-policy/).

Import websites and assets you own or have permission to use. For reproducible Free-edition bugs, use [GitHub Issues](https://github.com/hireawais/tahmir-ai-free/issues) with a minimal sample and WordPress/PHP versions. Keep credentials, private client content and Pro files out of public issues and pull requests.

See [LICENSE.txt](LICENSE.txt) for GPLv2 or later and [third-party.txt](third-party.txt) for bundled asset attribution. This GitHub release does not imply acceptance into the WordPress.org plugin directory. Tahmir is independent of GoHighLevel, Lovable and the other source platforms named here.
