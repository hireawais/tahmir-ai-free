# Lovable to WordPress with the free Tahmir AI plugin

Use Tahmir AI Free to import supported public-facing pages from a deployed Lovable website into WordPress. Keep Lovable as your design and build workflow, then review the supported page output on your own WordPress hosting. The official [Lovable to WordPress converter guide](https://www.tahmir.com/lovable-to-wordpress-converter/) explains the broader workflow.

## Prepare the Lovable project

Publish the project to a public URL and test the routes you want to deliver. Choose a representative marketing page with real images, navigation and responsive layouts. If you use an export, build the front end first and provide supported HTML with its assets; an unbuilt React/Vite source ZIP is not an HTML export.

## Import Lovable pages into WordPress

1. Install [Tahmir AI Free](../README.md#download-and-install-the-free-converter-plugin).
2. Open **Tahmir AI → Import → Import from URL** and paste the deployed address.
3. Review discovered pages and import the selected pages.
4. Compare typography, images, spacing and mobile navigation with the approved source.
5. Test links, scripts, contact forms and each required route.
6. Publish after the client or project owner approves the WordPress result.

The [Lovable homepage walkthrough](https://www.tahmir.com/how-to-convert-lovable-to-wordpress/) shows the original and imported homepages and how to check their project links.

## What comes across, and what needs separate work?

The importer handles supported front-end page content and assets. Supabase databases, authentication, server functions, application state, payments and external API behavior need their own migration or integration. A successful landing-page import does not establish that the full application works in WordPress.

Free includes import, page display/management and supported imported contact-form submission. For visual editing, page modules, logo/menu controls, advanced forms and client delivery tools, [upgrade to Pro](https://www.tahmir.com/upgrade/). Pro uses the imported pages alongside Free.

Continue with [AI to WordPress](ai-to-wordpress.md), [Make HTML websites editable](make-html-websites-editable.md) and the [client delivery checklist](deliver-client-websites.md).
