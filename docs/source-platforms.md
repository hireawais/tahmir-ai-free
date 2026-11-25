# Choose a WordPress migration workflow by source platform

Start with a single publicly accessible page that you control, or a complete build of your static site. Keep the first WordPress import in draft while you compare the design, test visitor interactions and identify which content the site's owners need to edit.

## Pick an input and an acceptance test

| Starting point | What to give the importer | What to prove before cutover | Official Tahmir instructions |
| --- | --- | --- | --- |
| AI-built marketing website | A deployed public URL or a built static HTML ZIP | Change a headline and link in the intended editor, then verify both in the saved page. | [AI website to WordPress](https://www.tahmir.com/ai-website-to-wordpress-converter/) |
| Static HTML | A ZIP of the built pages with CSS, JS, fonts and images in their original relative folders | Open a nested page directly and check its asset requests. Submit the form and verify delivery at the destination. | [HTML to WordPress](https://www.tahmir.com/free-html-to-wordpress-converter/) |
| Lovable | The published URL of a public-facing page | Confirm that public links work independently from any Supabase-backed login, data or service. | [Lovable to WordPress](https://www.tahmir.com/lovable-to-wordpress-converter/) |
| Claude | A public deployment or ZIP of finished HTML and its assets | Open a deep link in a signed-out browser, then check any buttons built as prototype interactions. | [Claude to WordPress](https://www.tahmir.com/claude-to-wordpress-converter/) |
| Bolt.new | The published URL, or built static files when the project provides them | Refresh public routes individually and exercise interactions that previously depended on the app runtime. | [Bolt to WordPress](https://www.tahmir.com/bolt-to-wordpress-converter/) |
| GoHighLevel AI Studio | A published page URL | Book an appointment or submit a test lead. Verify the intended HighLevel location, workflow and attribution. | [GoHighLevel to WordPress](https://www.tahmir.com/gohighlevel-to-wordpress-converter/) |
| Framer | The published URL for a representative page | Recreate one project as a WordPress record and confirm that its card and detail page update from that record. | [Framer to WordPress](https://www.tahmir.com/framer-to-wordpress-converter/) |
| Webflow | A published page URL for layout, plus separate Collection exports when you need records | Import a small CMS sample and compare its slugs, images, references and template output. | [Webflow to WordPress](https://www.tahmir.com/webflow-to-wordpress-converter/) |

## Where migration work usually splits

- **Page import.** Use the public URL or built export to establish the starting layout. Keep its source URL and desktop and mobile screenshots in the migration notes.
- **Content model.** Decide whether recurring content belongs in a WordPress post, page or custom post type with fields and a shared template. Add and edit one sample record before migrating all the entries.
- **Services and actions.** Record where each form, booking, checkout or sign-in hands off to another system. Work through each as a visitor and check the result where an operator will see it.
- **Publishing.** Compare title tags, indexability and canonicals, then map changed URLs and test redirects. Owners can use the [client website delivery checklist](deliver-client-websites.md) for the handoff.

This documentation is maintained by Tahmir AI. See the [full converter index](https://www.tahmir.com/ai-website-to-wordpress-converters/) for the platform walkthroughs.
