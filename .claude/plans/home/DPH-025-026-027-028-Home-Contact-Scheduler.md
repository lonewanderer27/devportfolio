# Plan: Home Contact scheduler (4-step card, Interests screen with Akismet spam check, confirm email, phone scheduler modal)

Tickets **DPH-025** (Step 1, Availability), **DPH-026** (Steps 2 and 3, About you and Your need), **DPH-027** (Step 4, Confirm and Thank you, Dave gets an email), **DPH-028** (Mobile scheduler), all High, Homepage. Sources: the user stories as pasted, `.claude/plans/home/Home-User-Stories-Revised.csv` and the lo-fi wireframe `.claude/plans/home/home-lofi-wireframe.html` (section C3, C5/C6 phone screens, section E, "Contact section – spec").
Branch: `feat/DPH-025-026-027-028-Contact-Scheduler`. Touches the **plugin** (the read-only `interest` post type and its wp-admin screens, DPB-017, the contact request endpoint with the Akismet check, minor version bump 0.33.0 → 0.34.0) and the **theme** (one block, two pure JS modules, Home template, `functions.php`). Follows the shape of `DPH-022-023-024-Home-Testimonials-Software-Tools.md` (dynamic block, pure `*.js` with `node --test`, `view.js` checked in headless Chrome).

**Implementer rule:** every "Fact" below was checked in the repo on the day of writing. If the code does not match a Fact, **stop and report**; do not improvise.

## As built (supersedes the plan below where they differ)

The plan below is the original design. What was built differs in these points; the Phase text is kept for history.

- **Time zones (replaces "Dave's zone defines availability").** The visitor picks a day and one of the times offered, in their own zone (the browser's). The endpoint converts it to UTC in that zone and stores `cptdp_slot_utc` (UTC), `cptdp_visitor_local` (the time as entered) and `cptdp_visitor_tz`. Dave's zone is used only when no visitor zone arrives. The site-zone offsets table (`cptdp_contact_utc_offsets()`) and the zone label were removed.
- **Times offered (replaces 09:00 to 16:30).** Settings → Contact has a "Booking times" list of 48 half-hour checkboxes (00:00 to 23:30), option `cptdp_contact_times`. Until Dave saves it, the offered times are 10:00, 11:00, 13:00, 14:00, 15:00 and 16:00. Nothing ticked means nothing can be booked (a warning is shown).
- **Phone layout (replaces the step 1 dialog).** All four steps are one full-screen modal that covers the whole screen, the site header included. It has the same header (✕ and "Check my availability") and the same footer (Back and Next) on every step. On step 1 the day and time are a draft until Next, so ✕, Esc and Back discard it.
- **Step 1 heading.** "Pick a day and time" is hidden visually; it stays for screen readers and for the focus that moves to it.
- **Motion.** The modal rises in on opening. Steps slide in from the side they come from (forward from the right, Back from the left). The footer stays still. The thank-you rises in, and Send pulses while the request is sent. All of it is off under `prefers-reduced-motion`.
- **Emails.** One email only: to the Settings → Contact address, with Reply-To set to the visitor. No confirmation is sent to the visitor (an auto-reply is out of scope). The email gives Dave's site-zone time and the visitor's entry.
- **Admin sidebar order.** Core only honours whole-number positions for custom post types (a fraction gets the next free slot). The order is Pages (20), Interests (21), Statistics (22), Services (23), Testimonials (29), Software Tools (30), Documents (31), Collaterals (32), Videos (33), Comments (34). Comments is WordPress's own item (25); `cptdp_move_comments_after_videos()` moves it to 34.
- **Interests view.** The main section shows Submitted (a relative phrase such as "7 hours ago", with the full date and time in a tooltip on hover or tap), Name, Email, Service, Message (or "No message"), and Requested Time in the admin's zone. Details (closed by default) shows the visitor's entry as "Requested Time (visitor's time zone)", Spam check, Email notification and IP address.

## Out of scope
- **Marking dates unavailable is a separate, later ticket (DPH-031), confirmed by the user.** Nothing here marks a day as booked, fully booked, struck-through, striped or "unavailable" for any reason other than being in the past (or beyond the 365-day window), and there is no booking or availability data of any kind. Every future day is simply available with the same 16 times.
- **DPH-031 Availability management** (booked and unavailable day styles, Dave's own calendar). The legend shows only "Available" and "Selected". The slot source is one PHP function (`cptdp_contact_time_slots()`) validated by the server, so DPH-031 later swaps that one function.
- **The hero and nav "Book a call" / "Let's work together" buttons.** Neither exists yet (`parts/header.html` has no button; the hero is another ticket). This plan delivers their target: the section `id="contact"` (the nav's Contact link already points to `/#contact`, and `theme.json` already sets `html { scroll-padding-top }` under the sticky header). Whoever builds those buttons uses `href="/#contact"`; no JS is needed. Acceptance item 4 of DPH-025 is met for the target side only.
- Auto-reply to the prospect, calendar invites, editing or replying from the Interests screen, and **telling Akismet about its mistakes** (`submit-spam` / `submit-ham` also need the browser and the referrer, which are not stored; see Open decision 7).
- Arrow-key navigation inside the calendar grid (each day is a button reached with Tab). Listed as Open decision 4.
- `DPH-030` as a whole (only reduced-motion for this block's own transitions is done here).

## Facts (checked)
1. `templates/front-page.html` ends with `…<!-- wp:devportfolio/software-tools /-->\n\n\t</div>\n\t<!-- /wp:group -->\n\n</main>\n<!-- /wp:group -->\n\n<!-- wp:template-part {"slug":"footer","area":"footer"} /-->`. Order today: stats, `#services`, `#selected-work` + three modals, `devportfolio/testimonials`, `#software-tools`. `tests/test-home-template.php` has `test_testimonials_and_tools_follow_selected_work()` listing the needles in order.
2. Blocks are registered in `devportfolio_register_blocks()` in `functions.php`; the last line of the list is `register_block_type( __DIR__ . '/blocks/software-tools' );` followed by `\t}\nendif;\nadd_action( 'init', 'devportfolio_register_blocks' );`. `functions.php` ends with `endif;\n`. Helper functions there are wrapped in `if ( ! function_exists( … ) ) : … endif;`.
3. A block is `block.json` (`"render": "file:./render.php"`, `"style": "file:./style.css"`, `"viewScriptModule": "file:./view.js"`) like `blocks/testimonials/`. A view module may import from `../shared/` (the collateral modal does).
4. `blocks/shared/dom.js` exports `el( tag, className, attrs )` (no text argument). `blocks/shared/sheet-back.js` exports `createSheetHistory( { isOpen, onBack } )` returning `{ push, pop, release, pushed }`: one history entry on the same URL, a capture-phase `popstate` listener that stops the event for the moves it owns (the Interactivity Router answers every `popstate`). **It must be created synchronously when the module runs, never inside a lazy import or `DOMContentLoaded`** (AGENTS.md, Mobile Pages sheet).
5. Plugin: version **0.33.0** (header and `CPTDP_VERSION` in `custom-post-types-dp.php`). `includes/class-loader.php` globs and requires every file in `includes/post-types/`, `includes/settings/` and `includes/seeders/`, so a new file in `includes/settings/` is loaded with no other edit. `includes/settings/contact.php` defines `cptdp_get_contact( ?string $key )` returning option `cptdp_contact` (`email`, `whatsapp_url`, `linkedin_url`). REST routes in the plugin use namespace `cptdp/v1`.
6. The `service` CPT is hierarchical; the seeder gives three top-level services (`Social Media Marketing`, `Digital Marketing`, `Web Creation / Management`) with children. `blocks/service-list` shows top-level published services by menu order then title. The story's three chips are exactly these, so **the chips are read from the top-level Services**, not hard-coded (the story says "same service names as What I do").
7. Palette tokens: `base` #fff, `contrast` #222, `mid` #8a8a8a, `line` #c9c9c9, `fill` #eee (`--wp--preset--color--*`); sections use `--wp--preset--spacing--60`; the Home page shell is `main.documents-page.home-page`. The routing for the document/collateral/video viewers uses 700px; **this block uses 800px** (the story's number) and its own query.
8. PHP tests: plugin suite `ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit`; theme suite `ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit`. The WP test library has `reset_phpmailer_instance()` and `tests_retrieve_phpmailer_instance()->get_sent()`.
9. DDEV catches outgoing mail in Mailpit (`ddev launch -m`).
10. The admin wireframe `.claude/plans/backend/wordpress-admin-lofi-wireframe.html` (section A item 7, section D4 and its "Rules for these screens") defines the **Interests** screen (DPB-017): a read-only sidebar item with a lock and a count badge for new submissions; list columns **Date, Name, Email, Service**; a row opens a view where every value is a grey read-only block under a "Read-only" badge (Date and time, Name, Email, Service, Message); no Add New, no Publish, no Update, no Trash. **No `interest` post type, DPB-017 plan or code exists in the repo** (checked with grep), so this plan builds it. The wireframe has no spam state: "Potential spam" is designed here.
11. Akismet is not in the repo (other plugins are git-ignored). The Akismet plugin keeps its key in option `wordpress_api_key`; a site may instead define `WPCOM_API_KEY`. Its REST call is `POST https://<key>.rest.akismet.com/1.1/comment-check` and answers the body `true` (spam) or `false` (not spam), or `invalid` for a bad key.
12. `testimonial` uses `menu_position` 29, so the Interests item takes 31. Post types hook `register_post_type` to the `cptdp_register_post_types` action (see `testimonial.php`).

## Decisions made (do not block)
- **Time (decided with the user): everything stored is UTC; everything shown is local to the person looking.** Dave's availability is defined in the **site's time zone** (Settings → General): the calendar days and the 16 slots are his wall-clock times. Stored: the requested time as `cptdp_slot_utc` (ISO 8601 with `Z`, e.g. `2026-10-05T23:30:00Z`, computed **by the server** from the validated day and time, never taken from the browser), the moment received as WordPress's `post_date_gmt`, and the visitor's time zone as `cptdp_visitor_tz` (an IANA id such as `America/Los_Angeles`, validated against PHP's list, empty when unknown or invalid). Shown: the prospect sees Dave's times labelled with his city ("Times are shown in Sydney time") and, as soon as a time is chosen, the **same moment in the prospect's own time zone** when it differs ("Mon 5 Oct · 16:30 in your time zone (America/Los_Angeles)"), in the picker, the summary, the phone time line and the thank-you. Admins see the list's Date column and the view's times converted to **their own browser's time zone** with the zone shown (`<time datetime="…Z">` rewritten by a few lines of admin JS; the server-written text is in the site zone for when scripts are off), plus the prospect's local time in the view. Dave's email gives the site-zone time, the UTC time and the visitor's own time. The page needs no time zone library: the server sends a short table of the site zone's UTC offsets for the bookable window (`offsets`, one entry per daylight-saving change) and a day's slots all use that day's offset.
- **Available days: tomorrow up to 365 days ahead, every day including weekends.** Today is not bookable (a day's notice; the story says "past days cannot be picked", this is the cheapest safe reading, see Open decision 1). **Times: the same 16 slots every day, 09:00 to 16:30 every 30 minutes**, from `cptdp_contact_time_slots()` (filter `cptdp_contact_time_slots`).
- **One endpoint**: `POST /wp-json/cptdp/v1/contact-request`, public (anonymous prospects), JSON body `{ date, time, timezone, name, email, services[], message, website }` (`date` and `time` are Dave-zone wall-clock choices, `timezone` is the browser's IANA zone). `website` is a **honeypot** (never shown, off-screen, `tabindex="-1"`, `aria-hidden`): when filled the endpoint answers 200 and **saves and sends nothing**. Rate limit: **10 requests per hour per client** (transient keyed by hashed `REMOTE_ADDR`, filters `cptdp_contact_rate_limit` and `cptdp_contact_client_ip` for hosts behind a proxy). Answers: 200 `{ok:true}` (also for spam, so a bot learns nothing), 422 `{code, message, data:{field}}` (first invalid field), 429, 500 (the submission could not be saved). **No 503**: with no Contact email set the submission is still saved and shows in Interests, only the email is skipped.
- **Order inside the endpoint:** honeypot, validate, rate limit, **Akismet check**, **save** (straight into its final status: `publish` or `potential-spam`, never published first), then email only when it is not spam. A submission is therefore never visible before it has been checked.
- **Interests (DPB-017):** a non-public post type `interest`, one post per submission: title = name, content = message, meta `cptdp_email`, `cptdp_ip` (the submitter's IP address, as Akismet saw it), `cptdp_slot_utc` (the requested time in UTC, ISO 8601), `cptdp_visitor_tz`, `cptdp_services` (array of the service titles at that moment), `cptdp_spam_check` (`ham` | `spam` | `skipped` | `error` | `manual-ham`), `cptdp_mail_sent` (`yes` | `no`), `cptdp_seen`. Nothing is exposed in REST or on the front end. The admin screen follows the wireframe: sidebar item "Interests" with a lock icon and a badge counting submissions not opened yet, a list with the columns Date, Name, Email, Service, a read-only view (which also shows the IP address), no Add New, no Publish box, no Update. **Trash is enabled** (the user's decision, a departure from the wireframe's "no Trash"): Move to Trash, a Trash tab with Restore and Delete permanently, and WordPress's normal 30-day automatic emptying of the trash.
- **Potential spam** is a custom post status `potential-spam`: hidden from the "All" list and the badge by core (`show_in_admin_all_list` false), shown in its own **Potential spam (n)** tab (`show_in_admin_status_list` true), excluded from search and from `post_status=any`. A visible row has **View** and **Move to Trash**; a potential spam row has **View**, **Not spam** and **Move to Trash**; a trashed row has core's **Restore** (back to the status it had) and **Delete permanently**. Bulk actions are core's minus Edit. **Not spam** moves it to `publish`, sets `cptdp_spam_check` to `manual-ham` and sends the email that was held back.
- **Akismet:** called directly with `wp_remote_post()` (5 s timeout), not through the Akismet plugin's internals. The key is read by `cptdp_akismet_key()`: constant `WPCOM_API_KEY`, else option `wordpress_api_key` (what the Akismet plugin saves), overridable with the filter `cptdp_akismet_key`; only `[A-Za-z0-9]` keys are accepted. Sent: site URL, visitor IP, browser (User-Agent), referrer, `comment_type=contact-form`, name, email, message, language and charset. **Fails open**: no key (`skipped`), a network error, a non-200 answer or an unreadable answer (`error`) all count as genuine, so a real enquiry is never lost to an outage; the Interests list shows a warning notice while there is no key.
- **Privacy (accepted by the user):** the name, email, message and **IP address** are stored as an Interest in the site database (only people who can open the Interests screen see them) and are also sent to Akismet for the check, together with the browser and the referrer. **The browser and the referrer are not stored**; the IP is stored only on the Interest (`cptdp_ip`, validated with `FILTER_VALIDATE_IP`, empty when it is not a valid address) and the rate-limit transient still holds only an `md5` of it and a count. The browser keeps the entered data **in memory only** (no `localStorage`/`sessionStorage`). Trashing or deleting an Interest removes its IP with it.
- **Email:** to the Settings → Contact address, subject `[Site name] Interest from <name>`, plain text, `Reply-To: <name> <email>` (name reduced to letters, digits, space, `.`, `'`, `-` for the header), body lists Name, Email, Time (the site zone, UTC and, when known, the visitor's own time), Help with (service titles, "Something else"), Message and a link to the Interest in wp-admin. A failed `wp_mail()` no longer fails the request (the Interest is saved); it is recorded in `cptdp_mail_sent` and shown on the Interest.
- **Markup is server-rendered, state is JS.** `render.php` outputs the section, the heading, four step forms (one `<form novalidate>` per step so Enter submits the step and each step owns its footer), the dialog and a JSON payload. The crumbs, calendar, times and summary are built by `view.js`/`picker.js`. Without JS the card and the button are hidden and a `<noscript>` line shows the email address.
- **Layout:** a dark rounded band (`contrast` background, inside the normal content width, not full-bleed) with the heading in white and a white card (`base`) with the steps, per the wireframe. At **800px and below** the card is hidden until a time is confirmed, and the band shows only the heading and the "Check availability" button.
- **Mobile steps** are one screen each: the card is at least a screen high and the footer (Back, Next) is `position: sticky; bottom: 0`. Back on step 2 reopens the scheduler modal (step 1 on a phone is the modal). The "Time · Change" line shows above steps 2 to 4 on phones only.
- **After "Edit" on step 4** the visitor goes to that step and **Next returns straight to step 4** (`returnTo`), not through the steps in between. Back and the crumbs clear it.
- **Mobile modal keeps a draft**: ✕, Esc and Back close without touching the confirmed time; only "Confirm date & time" commits. Reopening copies the confirmed time into the draft.
- **Back button closes the modal** through the shared `createSheetHistory()` (one same-URL history entry while it is open).

## How a submission ends up (every path)

This section is the reference for "what happens to it". Each row is pinned by a test named in the last column; the admin steps are in Phase E.

### 1. The pipeline (what the endpoint does, in order)

```
visitor presses "Confirm my interest"
        |
        v
  POST /wp-json/cptdp/v1/contact-request
        |
        +-- honeypot field filled? ----------- yes --> 200 "ok"  (nothing saved, nothing sent)
        |
        +-- any field invalid? --------------- yes --> 422 + which field   (nothing saved)
        |      (name, email, day, time, services, message)
        |
        +-- over 10 requests/hour/client? ---- yes --> 429                  (nothing saved)
        |
        +-- ask Akismet (5 s, fails open)
        |      |-- "spam" ...................... status = potential-spam, check = spam
        |      |-- "not spam" .................. status = publish,        check = ham
        |      |-- no key ...................... status = publish,        check = skipped
        |      '-- error / timeout / bad key ... status = publish,        check = error
        |
        +-- save the Interest straight into that status (UTC times, IP, services snapshot)
        |        could not save? ------------- yes --> 500                  (nothing saved)
        |
        +-- status = publish?  -- yes --> email Dave (if a Contact email is set), record mail_sent yes/no
        |                      -- no  --> no email
        v
   200 "ok"   (identical for spam and not spam: the visitor and a bot cannot tell)
```

### 2. Outcome of each submission, by case

| # | Case | What the visitor sees | Saved as | In wp-admin | Badge | Email to Dave | `cptdp_spam_check` / `cptdp_mail_sent` | Pinned by (plugin test) |
|---|---|---|---|---|---|---|---|---|
| 1 | Honeypot filled (a bot) | The normal "Thanks, <name>" | **nothing** | nothing | no | no | n/a | `test_the_honeypot_answers_200_and_saves_and_sends_nothing` |
| 2 | A field is invalid (also a stale day or time from a cached page) | The plain-words error under the field, or back to step 1 with "That time is no longer available" | **nothing** | nothing | no | no | n/a | `test_each_invalid_field_is_a_422_naming_the_field_and_saves_nothing` |
| 3 | Over the rate limit | "Too many tries. Please wait a while and try again." | **nothing** | nothing | no | no | n/a | `test_the_rate_limit_answers_429_after_the_limit_and_never_reaches_akismet` |
| 4 | **Akismet: not spam** (the normal case) | "Thanks, <name>. Interest received." | `publish` | **All** tab, row "View \| Trash" | **+1** until opened | **yes** | `ham` / `yes` | `test_akismet_says_genuine_so_it_is_visible_and_emailed` |
| 5 | **Akismet: spam** | **The same** "Thanks, <name>" | `potential-spam` | **Potential spam** tab only (not in All), row "View \| Not spam \| Trash" | **no** | **no** | `spam` / (not set) | `test_akismet_says_spam_so_it_is_saved_hidden_and_not_emailed_and_the_visitor_sees_success` |
| 6 | No Akismet key set | Thanks | `publish` | All; a yellow "Spam check is off" notice sits above the list | +1 | yes | `skipped` / `yes` | `test_without_a_key_akismet_is_not_called` |
| 7 | Akismet unreachable, slow (over 5 s), bad key or a nonsense answer | Thanks | `publish` (fails open: a real enquiry is never lost) | All; the view says "Not checked (Akismet could not be reached)" | +1 | yes | `error` / `yes` | `test_akismet_trouble_fails_open` |
| 8 | No Contact email set in Settings → Contact | Thanks | `publish` or `potential-spam` as above | as case 4 or 5; the view says "Email notification: Not sent" | as case 4 or 5 | **no** | as above / `no` | `test_without_a_contact_email_it_is_still_saved_and_no_email_is_sent` |
| 9 | `wp_mail()` fails (mail server down) | Thanks | `publish` | All; the view says "Not sent" so Dave knows to look here; **no retry** | +1 | no | `ham` / `no` | `test_a_failed_mail_still_saves_the_interest_and_says_so` |
| 10 | The database refuses the save | "That did not send. Please try again." (the data in the card is kept) | **nothing** | nothing | no | no | n/a | `test_a_save_failure_is_a_500_and_sends_nothing` |

What is saved in every "saved" row: name (title), message (content), email, **IP address**, the requested time **in UTC**, the visitor's time zone, the service titles as they were that day, the received time in UTC (`post_date_gmt`), the spam verdict and whether the email left. Never saved: the browser string and the referrer (they go to Akismet only).

### 3. What the admin sees, for two example submissions

Received at 21:32 UTC on 8 Oct 2026 (stored as UTC), chosen time Tue 13 Oct 10:30 (site zone Sydney), visitor in Los Angeles. The admin is in Manila; the list and the view show Manila time, hover shows UTC.

```
Interests (lock icon, badge 1)      [ All (1) | Potential spam (1) ]

   [ ]  Date                       Name           Email                Service
   [ ]  9 Oct 2026, 05:32 GMT+8    Maya Ortiz     maya@bloom.example   Social Media Marketing
        View | Trash                                                     <- visible: case 4

Potential spam tab:
   [ ]  9 Oct 2026, 05:33 GMT+8    Pharma Deals   cheap@spam.example   Something else
        View | Not spam | Trash                                          <- hidden: case 5
```

The read-only view of the first (🔒 Read-only badge, grey blocks, no Update):

```
Date and time        9 Oct 2026, 05:32 GMT+8       (datetime 2026-10-08T21:32:00Z)
Name                 Maya Ortiz
Email                maya@bloom.example
IP address           198.51.100.9
Service              Social Media Marketing
Requested time       13 Oct 2026, 07:30 GMT+8      (datetime 2026-10-12T23:30:00Z)
                     Visitor's time: Mon 12 Oct 2026, 16:30 (America/Los_Angeles)
Message              Hi Dave, I run a small café and would love help with weekly posts...
Spam check           Not spam (Akismet)
Email notification   Sent
                     [Move to Trash]
```

### 4. What happens to it afterwards (admin life cycle)

```
                         Akismet: not spam                         Akismet: spam
                                |                                        |
                                v                                        v
                          [ publish ] <------ "Not spam" ------ [ potential-spam ]
                      shows in All, counts                       only in its own tab,
                      in the badge until opened                  never in the badge
                                |   ^                                    |   ^
                      Move to   |   | Restore                  Move to   |   | Restore (comes back
                      Trash     v   |  (back to publish)       Trash     v   |  as potential-spam)
                          [        trash        ]  <--------------------'   |
                                   |                                          
              Delete permanently, or WordPress empties the trash after 30 days -> gone (IP and all)
```

Rules that follow from it:
- **"Not spam"** moves it to `publish`, sets the check to `manual-ham`, **sends the email that was held back** (so Dave's notification arrives late, never twice) and it joins the badge.
- **Opening** a visible Interest marks it seen (the badge drops on the next page load); opening a potential-spam one does not.
- **Nothing is ever edited**: no field can be changed from the screen, and there is no Publish or Update button.
- **Trash is reversible** for 30 days; **Delete permanently** (in the Trash tab) and the automatic empty remove the post, its meta and the IP together.
- **If Akismet is down for a while**, the submissions of that time are all visible (case 7); Dave trashes any that are spam by hand. Nothing is re-checked afterwards.
- **A visitor who submits twice** creates two Interests (no de-duplication; the rate limit is the only guard).

## Open decisions (default applied; none blocks the work)
1. **Is today bookable?** Default: no (tomorrow onwards). To allow it, change `'first'` in `cptdp_contact_window()` to `$today->format( 'Y-m-d' )` and filter past times out of the slots; one function, one test.
2. **Opening hours and slot length.** Default 09:00 to 16:30, 30 minutes, all 7 days. Dave confirms; it is one function.
3. **Rate limit behind a proxy.** Hostinger may show the proxy as `REMOTE_ADDR`, making the 10/hour limit global. If so, return the real address from the `cptdp_contact_client_ip` filter in a small mu-plugin. Not done here.
4. **Calendar keyboard model.** Default: Tab through the day buttons. A roving-tabindex grid with arrow keys is a later polish.
5. **Phone "pinned at the bottom"** is a sticky footer inside a screen-high card, not a fixed overlay. Say if a fixed bar is wanted.
6. **How long potential spam is kept.** Default: until Dave moves it to the Trash, which WordPress then empties after 30 days. A daily purge of potential spam older than 30 days is a small cron job to add if the tab fills up.
7. **Teaching Akismet.** "Not spam" and Trash do not report back to Akismet. The IP is now stored, but `submit-ham` / `submit-spam` also want the browser and the referrer; storing those two is a small addition (two more meta keys) if Dave wants Akismet to learn from his corrections.
9. **Odd time zones.** A site zone set as a fixed offset ("UTC+10") works but has no daylight saving. A slot list changed by the `cptdp_contact_time_slots` filter to include the hours around a daylight-saving change (02:00 to 03:00) would use that day's offset for all slots; the default 09:00 to 16:30 never does.
8. **Akismet licence.** The free key is for non-commercial sites; a portfolio that wins paid work is probably commercial. Dave decides before launch; the code does not change.

---

## Phase A. Plugin: the Interest post type (DPB-017), the Akismet check and the contact request endpoint

Order of work: A1 `interest.php` (storage, notify, Akismet), A2 `interest-admin.php`, A3 their tests, A4 `contact-request.php`, A5 its tests, A6 the version. Each step leaves the suite green because later files only call earlier ones.

### A1. `wp-content/plugins/custom-post-types-dp/includes/post-types/interest.php` (new, in full)
The loader requires it. Everything about an Interest that is not a screen: the type, the spam status, saving, the email, "not spam" and the Akismet call.

```php
<?php
/**
 * Interest post type (DPB-017): what the Home Contact scheduler receives. Not public, no REST, read-only in wp-admin
 * (screens in interest-admin.php). A submission is created straight into `publish` or `potential-spam`, so it is never
 * visible before the Akismet check. Callers must escape on output.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Interest post type and the Potential spam status.
 */
function cptdp_register_interest(): void {
	register_post_type(
		'interest',
		array(
			'labels'              => array(
				'name'          => esc_html__( 'Interests', 'custom-post-types-dp' ),
				'singular_name' => esc_html__( 'Interest', 'custom-post-types-dp' ),
				'menu_name'     => esc_html__( 'Interests', 'custom-post-types-dp' ),
				'all_items'     => esc_html__( 'All Interests', 'custom-post-types-dp' ),
				'search_items'  => esc_html__( 'Search Interests', 'custom-post-types-dp' ),
				'not_found'     => esc_html__( 'No interests yet.', 'custom-post-types-dp' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_rest'        => false,
			'menu_position'       => 31,
			'menu_icon'           => 'dashicons-lock',
			'supports'            => false,
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'exclude_from_search' => true,
		)
	);

	register_post_status(
		'potential-spam',
		array(
			'label'                     => esc_html_x( 'Potential spam', 'post status', 'custom-post-types-dp' ),
			'public'                    => false,
			'internal'                  => false,
			'protected'                 => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			/* translators: %s: number of items. */
			'label_count'               => _n_noop( 'Potential spam <span class="count">(%s)</span>', 'Potential spam <span class="count">(%s)</span>', 'custom-post-types-dp' ),
		)
	);
}
add_action( 'cptdp_register_post_types', 'cptdp_register_interest' );

/**
 * The Akismet key: the constant a host may define, else the option the Akismet plugin saves. '' = no check.
 */
function cptdp_akismet_key(): string {
	$key = defined( 'WPCOM_API_KEY' ) && WPCOM_API_KEY ? WPCOM_API_KEY : get_option( 'wordpress_api_key' );
	$key = is_string( $key ) ? trim( $key ) : '';
	$key = (string) apply_filters( 'cptdp_akismet_key', $key );

	return 1 === preg_match( '/^[A-Za-z0-9]+$/', $key ) ? $key : '';
}

/**
 * The visitor's IP address (filter `cptdp_contact_client_ip` for hosts behind a proxy), or '' when it is not a valid address.
 * Used for the rate limit (hashed), Akismet and the stored `cptdp_ip`.
 */
function cptdp_contact_client_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip = (string) apply_filters( 'cptdp_contact_client_ip', $ip );

	return false === filter_var( $ip, FILTER_VALIDATE_IP ) ? '' : $ip;
}

/**
 * Ask Akismet whether a clean submission is spam. Fails open: any problem counts as genuine.
 *
 * @param array<string,mixed> $clean   Output of cptdp_contact_validate().
 * @param WP_REST_Request     $request The request (for the browser and referrer).
 * @return string `spam`, `ham`, `skipped` (no key) or `error`.
 */
function cptdp_akismet_check( array $clean, WP_REST_Request $request ): string {
	$key = cptdp_akismet_key();
	if ( '' === $key ) {
		return 'skipped';
	}

	$body = array(
		'blog'                 => home_url(),
		'user_ip'              => cptdp_contact_client_ip(),
		'user_agent'           => (string) $request->get_header( 'user_agent' ),
		'referrer'             => (string) $request->get_header( 'referer' ),
		'comment_type'         => 'contact-form',
		'comment_author'       => $clean['name'],
		'comment_author_email' => $clean['email'],
		'comment_content'      => $clean['message'],
		'blog_lang'            => get_locale(),
		'blog_charset'         => get_option( 'blog_charset' ),
	);
	if ( apply_filters( 'cptdp_akismet_is_test', false ) ) {
		$body['is_test'] = '1';
	}

	$response = wp_remote_post(
		'https://' . $key . '.rest.akismet.com/1.1/comment-check',
		array(
			'timeout'    => 5,
			'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . ' | CPTDP/' . CPTDP_VERSION,
			'body'       => $body,
		)
	);
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return 'error';
	}

	$verdict = trim( wp_remote_retrieve_body( $response ) );
	if ( 'true' === $verdict ) {
		return 'spam';
	}

	return 'false' === $verdict ? 'ham' : 'error';
}

/**
 * Save a submission as an Interest.
 *
 * @param array<string,mixed> $clean  Output of cptdp_contact_validate() (incl. `slot_utc` and `timezone`), plus `ip` (cptdp_contact_client_ip()).
 * @param string              $status `publish` or `potential-spam`.
 * @param string              $check  The Akismet result, kept as `cptdp_spam_check`.
 * @return int|WP_Error Post ID.
 */
function cptdp_interest_create( array $clean, string $status, string $check ) {
	$status = 'potential-spam' === $status ? 'potential-spam' : 'publish';
	$now    = gmdate( 'Y-m-d H:i:s' ); // Stored in UTC (post_date_gmt); post_date is the same moment in the site zone.

	return wp_insert_post(
		wp_slash(
			array(
				'post_type'     => 'interest',
				'post_status'   => $status,
				'post_date_gmt' => $now,
				'post_date'     => get_date_from_gmt( $now ),
				'post_title'   => $clean['name'],
				'post_content' => $clean['message'],
				'meta_input'   => array(
					'cptdp_email'      => $clean['email'],
					'cptdp_ip'         => (string) ( $clean['ip'] ?? '' ),
					'cptdp_slot_utc'   => $clean['slot_utc'],
					'cptdp_visitor_tz' => (string) ( $clean['timezone'] ?? '' ),
					'cptdp_services'   => $clean['help'],
					'cptdp_spam_check' => $check,
				),
			)
		),
		true
	);
}

/**
 * The requested time as a UTC DateTimeImmutable, or null when the Interest has none.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_slot( int $post_id ): ?DateTimeImmutable {
	$raw  = (string) get_post_meta( $post_id, 'cptdp_slot_utc', true );
	$when = DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s\Z', $raw, new DateTimeZone( 'UTC' ) );

	return $when ? $when : null;
}

/**
 * The visitor's stored time zone, or '' when it is missing or not a real zone.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_visitor_tz( int $post_id ): string {
	$zone = (string) get_post_meta( $post_id, 'cptdp_visitor_tz', true );

	return in_array( $zone, timezone_identifiers_list(), true ) ? $zone : '';
}

/**
 * The requested time as one line for Dave: his time zone, UTC and, when known, the visitor's own time.
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_time_label( int $post_id ): string {
	$when = cptdp_interest_slot( $post_id );
	if ( ! $when ) {
		return '';
	}

	$label = $when->setTimezone( wp_timezone() )->format( 'D j M Y, H:i' ) . ' (' . wp_timezone_string() . ')';
	$label .= '; ' . $when->format( 'Y-m-d H:i' ) . ' UTC';

	$zone = cptdp_interest_visitor_tz( $post_id );
	if ( '' !== $zone ) {
		$label .= '; ' . $when->setTimezone( new DateTimeZone( $zone ) )->format( 'D j M Y, H:i' ) . ' (' . $zone . ", the visitor's time)";
	}

	return $label;
}

/**
 * Email Dave one Interest and record whether it left (`cptdp_mail_sent`). No Contact address = no email, recorded as `no`.
 *
 * @param int $post_id Interest ID.
 * @return bool Whether the email was handed over.
 */
function cptdp_interest_notify( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'interest' !== $post->post_type ) {
		return false;
	}

	$to   = cptdp_get_contact( 'email' );
	$sent = false;

	if ( is_email( $to ) ) {
		$email = (string) get_post_meta( $post_id, 'cptdp_email', true );
		$help  = get_post_meta( $post_id, 'cptdp_services', true );
		$help  = is_array( $help ) ? $help : array();
		$label = cptdp_interest_time_label( $post_id );

		$lines = array(
			'Name: ' . $post->post_title,
			'Email: ' . $email,
			'Time: ' . $label,
			'Help with: ' . implode( ', ', $help ),
			'Message:',
			'' === trim( $post->post_content ) ? '(none)' : $post->post_content,
			'',
			'View: ' . admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);

		$header_name = trim( (string) preg_replace( '/[^\p{L}\p{N} .\'-]/u', '', $post->post_title ) );
		$headers     = array( 'Reply-To: ' . ( '' === $header_name ? '' : $header_name . ' ' ) . '<' . $email . '>' );
		$subject     = sprintf(
			'[%s] Interest from %s',
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$post->post_title
		);

		$sent = (bool) wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
	}

	update_post_meta( $post_id, 'cptdp_mail_sent', $sent ? 'yes' : 'no' );

	return $sent;
}

/**
 * Dave says a potential spam item is genuine: make it visible and send the email that was held back.
 *
 * @param int $post_id Interest ID.
 * @return bool False when it is not a potential spam Interest.
 */
function cptdp_interest_mark_not_spam( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'interest' !== $post->post_type || 'potential-spam' !== $post->post_status ) {
		return false;
	}

	$result = wp_update_post(
		array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		),
		true
	);
	if ( is_wp_error( $result ) ) {
		return false;
	}

	update_post_meta( $post_id, 'cptdp_spam_check', 'manual-ham' );
	cptdp_interest_notify( $post_id );

	return true;
}
```

### A2. `wp-content/plugins/custom-post-types-dp/includes/post-types/interest-admin.php` (new, in full)
The wp-admin screens, following the wireframe (A item 7, D4). Hooks only fire in wp-admin.

```php
<?php
/**
 * Interests in wp-admin (DPB-017): the list, the read-only view, the badge, Potential spam. Follows
 * .claude/plans/backend/wordpress-admin-lofi-wireframe.html (A item 7, D4): Date, Name, Email, Service; no Add New, no
 * Publish, no Update. Trash is enabled (the user's decision). The actions are View and Move to Trash, Not spam in the
 * Potential spam tab, and core's Restore and Delete permanently in the Trash tab.
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * List columns: a checkbox, Date, Name (the title, links to the view), Email, Service.
 *
 * @param array<string,string> $columns Default columns.
 * @return array<string,string>
 */
function cptdp_interest_columns( array $columns ): array {
	return array(
		'cb'            => '<input type="checkbox" />',
		'cptdp_received' => esc_html__( 'Date', 'custom-post-types-dp' ),
		'title'         => esc_html__( 'Name', 'custom-post-types-dp' ),
		'cptdp_email'   => esc_html__( 'Email', 'custom-post-types-dp' ),
		'cptdp_service' => esc_html__( 'Service', 'custom-post-types-dp' ),
	);
}
add_filter( 'manage_interest_posts_columns', 'cptdp_interest_columns' );

/**
 * A moment as a `<time>` element: the UTC instant in `datetime` (what is stored), the site-zone text inside for when
 * scripts are off. assets/admin-interest.js rewrites the text in the viewer's own time zone. Returns escaped HTML.
 *
 * @param int $timestamp Unix timestamp.
 */
function cptdp_interest_time_tag( int $timestamp ): string {
	return sprintf(
		'<time datetime="%1$s" data-cptdp-local>%2$s</time>',
		esc_attr( gmdate( 'Y-m-d\TH:i:s\Z', $timestamp ) ),
		esc_html( wp_date( 'j M Y, H:i T', $timestamp ) )
	);
}

/**
 * Load the script that shows the times in the viewer's own time zone on the Interests screens.
 */
function cptdp_interest_admin_assets(): void {
	$screen = get_current_screen();
	if ( $screen && 'interest' === $screen->post_type ) {
		wp_enqueue_script( 'cptdp-admin-interest', CPTDP_URL . 'assets/admin-interest.js', array(), CPTDP_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'cptdp_interest_admin_assets' );

/**
 * Cell content for Date, Email and Service.
 *
 * @param string $column  Column key.
 * @param int    $post_id Interest ID.
 */
function cptdp_interest_column( string $column, int $post_id ): void {
	if ( 'cptdp_received' === $column ) {
		echo cptdp_interest_time_tag( (int) get_post_time( 'U', true, $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cptdp_interest_time_tag().
	}
	if ( 'cptdp_email' === $column ) {
		echo esc_html( (string) get_post_meta( $post_id, 'cptdp_email', true ) );
	}
	if ( 'cptdp_service' === $column ) {
		$help = get_post_meta( $post_id, 'cptdp_services', true );
		echo esc_html( is_array( $help ) ? implode( ', ', $help ) : '' );
	}
}
add_action( 'manage_interest_posts_custom_column', 'cptdp_interest_column', 10, 2 );

/**
 * The link of the "Not spam" action (admin-post, with a nonce per item).
 *
 * @param int $post_id Interest ID.
 */
function cptdp_interest_not_spam_url( int $post_id ): string {
	return wp_nonce_url( admin_url( 'admin-post.php?action=cptdp_interest_not_spam&post=' . $post_id ), 'cptdp_not_spam_' . $post_id );
}

/**
 * Row actions: View and Move to Trash; in the Potential spam tab also Not spam; in the Trash tab core's Restore and
 * Delete permanently. No Edit or Quick Edit.
 *
 * @param array<string,string> $actions Default actions.
 * @param WP_Post              $post    The row.
 * @return array<string,string>
 */
function cptdp_interest_row_actions( array $actions, $post ): array {
	if ( 'interest' !== $post->post_type ) {
		return $actions;
	}

	if ( 'trash' === $post->post_status ) {
		return array_intersect_key( $actions, array_flip( array( 'untrash', 'delete' ) ) );
	}

	$out = array(
		'view' => sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post->ID ) ), esc_html__( 'View', 'custom-post-types-dp' ) ),
	);

	if ( 'potential-spam' === $post->post_status ) {
		$out['notspam'] = sprintf( '<a href="%s">%s</a>', esc_url( cptdp_interest_not_spam_url( $post->ID ) ), esc_html__( 'Not spam', 'custom-post-types-dp' ) );
	}
	if ( isset( $actions['trash'] ) ) {
		$out['trash'] = $actions['trash'];
	}

	return $out;
}
add_filter( 'post_row_actions', 'cptdp_interest_row_actions', 10, 2 );

/**
 * Bulk actions: core's, without Edit (nothing here can be edited).
 *
 * @param array<string,string> $actions Default actions.
 * @return array<string,string>
 */
function cptdp_interest_bulk_actions( array $actions ): array {
	unset( $actions['edit'] );

	return $actions;
}
add_filter( 'bulk_actions-edit-interest', 'cptdp_interest_bulk_actions' );

/**
 * Status tabs: "All (n) | Potential spam (n)" (no "Published" tab).
 *
 * @param array<string,string> $views Default views.
 * @return array<string,string>
 */
function cptdp_interest_views( array $views ): array {
	unset( $views['publish'] );

	return $views;
}
add_filter( 'views_edit-interest', 'cptdp_interest_views' );

/**
 * The Not spam link.
 */
function cptdp_interest_handle_not_spam(): void {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	check_admin_referer( 'cptdp_not_spam_' . $post_id );

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'custom-post-types-dp' ), '', array( 'response' => 403 ) );
	}

	cptdp_interest_mark_not_spam( $post_id );
	wp_safe_redirect( admin_url( 'edit.php?post_type=interest' ) );
	exit;
}
add_action( 'admin_post_cptdp_interest_not_spam', 'cptdp_interest_handle_not_spam' );

/**
 * Submissions not opened yet (visible ones only; potential spam never counts).
 */
function cptdp_interest_unseen_count(): int {
	$query = new WP_Query(
		array(
			'post_type'      => 'interest',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => 'cptdp_seen',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	return (int) $query->found_posts;
}

/**
 * The count badge on the sidebar item.
 */
function cptdp_interest_menu_badge(): void {
	global $menu;

	$count = cptdp_interest_unseen_count();
	if ( $count < 1 || ! is_array( $menu ) ) {
		return;
	}

	foreach ( $menu as $position => $item ) {
		if ( isset( $item[2] ) && 'edit.php?post_type=interest' === $item[2] ) {
			$menu[ $position ][0] .= ' <span class="awaiting-mod">' . (int) $count . '</span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}
}
add_action( 'admin_menu', 'cptdp_interest_menu_badge', 99 );

/**
 * Opening a visible Interest marks it seen.
 */
function cptdp_interest_mark_seen_on_open(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only records that it was opened.
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	if ( $post_id && 'interest' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
		update_post_meta( $post_id, 'cptdp_seen', '1' );
	}
}
add_action( 'load-post.php', 'cptdp_interest_mark_seen_on_open' );

/**
 * Replace the edit screen's boxes with the one read-only box (no Publish box, no Update).
 */
function cptdp_interest_add_box(): void {
	remove_meta_box( 'submitdiv', 'interest', 'side' );
	add_meta_box( 'cptdp_interest_view', esc_html__( 'Submission', 'custom-post-types-dp' ), 'cptdp_interest_render_box', 'interest', 'normal', 'high' );
}
add_action( 'add_meta_boxes_interest', 'cptdp_interest_add_box', 99 );

/**
 * One read-only value: a label over a grey block.
 */
function cptdp_interest_row( string $label, string $value ): string {
	return '<div class="cptdp-ro"><label>' . esc_html( $label ) . '</label><div>' . nl2br( esc_html( $value ) ) . '</div></div>';
}

/**
 * One read-only value that is already escaped HTML (a `<time>` element, a line break).
 */
function cptdp_interest_row_html( string $label, string $html ): string {
	return '<div class="cptdp-ro"><label>' . esc_html( $label ) . '</label><div>' . $html . '</div></div>';
}

/**
 * The read-only view (wireframe D4): Date and time, Name, Email, Service, Message, plus how it was handled.
 *
 * @param WP_Post $post The Interest.
 */
function cptdp_interest_render_box( $post ): void {
	$help   = get_post_meta( $post->ID, 'cptdp_services', true );
	$slot   = cptdp_interest_slot( $post->ID );
	$zone   = cptdp_interest_visitor_tz( $post->ID );
	$checks = array(
		'ham'        => __( 'Not spam (Akismet)', 'custom-post-types-dp' ),
		'manual-ham' => __( 'Marked not spam by you', 'custom-post-types-dp' ),
		'spam'       => __( 'Potential spam (Akismet)', 'custom-post-types-dp' ),
		'skipped'    => __( 'Not checked (no Akismet key)', 'custom-post-types-dp' ),
		'error'      => __( 'Not checked (Akismet could not be reached)', 'custom-post-types-dp' ),
	);
	$check  = (string) get_post_meta( $post->ID, 'cptdp_spam_check', true );
	$mail   = (string) get_post_meta( $post->ID, 'cptdp_mail_sent', true );

	echo '<style>.cptdp-ro{margin:0 0 14px}.cptdp-ro label{display:block;margin-bottom:4px;font-weight:600}.cptdp-ro div{padding:8px 10px;border:1px solid #c9c9c9;border-radius:6px;background:#eee;min-height:1.4em}.cptdp-badge{display:inline-block;padding:2px 8px;border:1px solid #222;border-radius:9px;font-size:11px}</style>';
	echo '<p><span class="cptdp-badge">🔒 ' . esc_html__( 'Read-only', 'custom-post-types-dp' ) . '</span></p>';

	if ( 'potential-spam' === $post->post_status ) {
		echo '<p><a class="button button-primary" href="' . esc_url( cptdp_interest_not_spam_url( $post->ID ) ) . '">' . esc_html__( 'Not spam', 'custom-post-types-dp' ) . '</a> <span class="description">' . esc_html__( 'Makes it visible in Interests and sends the email.', 'custom-post-types-dp' ) . '</span></p>';
	}

	echo cptdp_interest_row_html( __( 'Date and time', 'custom-post-types-dp' ), cptdp_interest_time_tag( (int) get_post_time( 'U', true, $post ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in cptdp_interest_time_tag().
	echo cptdp_interest_row( __( 'Name', 'custom-post-types-dp' ), $post->post_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo cptdp_interest_row( __( 'Email', 'custom-post-types-dp' ), (string) get_post_meta( $post->ID, 'cptdp_email', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo cptdp_interest_row( __( 'IP address', 'custom-post-types-dp' ), (string) get_post_meta( $post->ID, 'cptdp_ip', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo cptdp_interest_row( __( 'Service', 'custom-post-types-dp' ), is_array( $help ) ? implode( ', ', $help ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	$requested = $slot ? cptdp_interest_time_tag( $slot->getTimestamp() ) : '';
	if ( $slot && '' !== $zone ) {
		/* translators: 1: the requested time in the visitor's zone, 2: the zone. */
		$requested .= '<br>' . esc_html( sprintf( __( "Visitor's time: %1\$s (%2\$s)", 'custom-post-types-dp' ), $slot->setTimezone( new DateTimeZone( $zone ) )->format( 'D j M Y, H:i' ), $zone ) );
	}
	echo cptdp_interest_row_html( __( 'Requested time', 'custom-post-types-dp' ), $requested ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	echo cptdp_interest_row( __( 'Message', 'custom-post-types-dp' ), $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo cptdp_interest_row( __( 'Spam check', 'custom-post-types-dp' ), $checks[ $check ] ?? '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo cptdp_interest_row( __( 'Email notification', 'custom-post-types-dp' ), 'yes' === $mail ? __( 'Sent', 'custom-post-types-dp' ) : ( 'no' === $mail ? __( 'Not sent', 'custom-post-types-dp' ) : '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<p class="description">' . esc_html__( 'Submissions cannot be edited or published from here. Admins can view them and move them to the Trash.', 'custom-post-types-dp' ) . '</p>';

	$trash = 'trash' === $post->post_status ? '' : get_delete_post_link( $post->ID );
	if ( $trash ) {
		echo '<p><a class="submitdelete" href="' . esc_url( $trash ) . '">' . esc_html__( 'Move to Trash', 'custom-post-types-dp' ) . '</a></p>';
	}
}

/**
 * A warning on the Interests list while there is no Akismet key (nothing is being checked).
 */
function cptdp_interest_notices(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'edit-interest' !== $screen->id || '' !== cptdp_akismet_key() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Spam check is off: no Akismet key was found. New submissions are shown and emailed without being checked. Install Akismet and connect it to turn the check on.', 'custom-post-types-dp' ) . '</p></div>';
}
add_action( 'admin_notices', 'cptdp_interest_notices' );
```

### A2b. `wp-content/plugins/custom-post-types-dp/assets/admin-interest.js` (new, in full)
Shows every stored UTC moment in the time zone of the admin who is looking (the server cannot know it).

```js
/**
 * Interests (DPB-017): show every <time data-cptdp-local> in the viewer's own time zone, with the zone's short name.
 * The server wrote the site-zone text (what scripts-off sees); `datetime` holds the UTC instant, which is what is stored.
 */
( function () {
	var format;
	try {
		format = new Intl.DateTimeFormat( document.documentElement.lang || undefined, {
			day: 'numeric',
			month: 'short',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
			hourCycle: 'h23',
			timeZoneName: 'short',
		} );
	} catch ( error ) {
		return;
	}

	document.querySelectorAll( 'time[data-cptdp-local]' ).forEach( function ( node ) {
		var iso = node.getAttribute( 'datetime' );
		var date = new Date( iso );
		if ( isNaN( date.getTime() ) ) {
			return;
		}
		node.textContent = format.format( date );
		node.title = iso + ' (UTC)';
	} );
}() );
```

### A3. `wp-content/plugins/custom-post-types-dp/tests/post-types/test-interest.php` (new, in full)

```php
<?php
/**
 * Interest post type and screens tests (DPB-017).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group post-types
 */
class CPTDP_Test_Interest extends CPTDP_Test_Case {

	public function set_up(): void {
		parent::set_up();

		reset_phpmailer_instance();
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		update_option( 'timezone_string', 'Australia/Sydney' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		unset( $_GET['post'] );

		parent::tear_down();
	}

	/**
	 * @return array<string,mixed>
	 */
	private function clean( array $override = array() ): array {
		return array_merge(
			array(
				'name'    => 'Jane Citizen',
				'email'   => 'jane@example.com',
				'ip'      => '198.51.100.9',
				'date'     => '2030-01-15',
				'time'     => '10:30',
				'slot_utc' => '2030-01-14T23:30:00Z', // 10:30 on 15 Jan in Sydney (UTC+11).
				'timezone' => 'America/Los_Angeles',
				'help'    => array( 'Social Media Marketing', 'Something else' ),
				'message' => 'Hello there.',
			),
			$override
		);
	}

	private function make( string $status = 'publish', string $check = 'ham' ): int {
		$id = cptdp_interest_create( $this->clean(), $status, $check );
		$this->assertIsInt( $id );

		return $id;
	}

	public function test_the_type_is_private_and_cannot_be_created_by_hand(): void {
		$type = get_post_type_object( 'interest' );

		$this->assertNotNull( $type );
		$this->assertFalse( $type->public );
		$this->assertFalse( $type->show_in_rest );
		$this->assertTrue( $type->show_ui );
		$this->assertSame( 'do_not_allow', $type->cap->create_posts );
		$this->assertFalse( post_type_supports( 'interest', 'title' ) );
		$this->assertFalse( post_type_supports( 'interest', 'editor' ) );
		$this->assertSame( 31, $type->menu_position );
	}

	public function test_potential_spam_is_hidden_from_all_but_has_its_own_tab(): void {
		$status = get_post_status_object( 'potential-spam' );

		$this->assertNotNull( $status );
		$this->assertFalse( $status->show_in_admin_all_list );
		$this->assertTrue( $status->show_in_admin_status_list );
		$this->assertTrue( $status->exclude_from_search );
		$this->assertNotContains( 'potential-spam', get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
		$this->assertContains( 'publish', get_post_stati( array( 'show_in_admin_all_list' => true ) ) );
	}

	public function test_create_saves_everything_in_its_final_status(): void {
		$id = $this->make( 'publish', 'ham' );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'interest', get_post_type( $id ) );
		$this->assertSame( 'Jane Citizen', get_the_title( $id ) );
		$this->assertSame( 'Hello there.', get_post_field( 'post_content', $id ) );
		$this->assertSame( 'jane@example.com', get_post_meta( $id, 'cptdp_email', true ) );
		$this->assertSame( '198.51.100.9', get_post_meta( $id, 'cptdp_ip', true ) );
		$this->assertSame( '2030-01-14T23:30:00Z', get_post_meta( $id, 'cptdp_slot_utc', true ) );
		$this->assertSame( 'America/Los_Angeles', get_post_meta( $id, 'cptdp_visitor_tz', true ) );
		$this->assertSame( array( 'Social Media Marketing', 'Something else' ), get_post_meta( $id, 'cptdp_services', true ) );
		$this->assertSame( 'ham', get_post_meta( $id, 'cptdp_spam_check', true ) );
	}

	public function test_create_keeps_backslashes_and_quotes_in_the_message(): void {
		$id = cptdp_interest_create( $this->clean( array( 'message' => 'It\'s a "test" \\ done' ) ), 'publish', 'ham' );

		$this->assertSame( 'It\'s a "test" \\ done', get_post_field( 'post_content', $id ) );
	}

	public function test_a_spam_submission_is_created_hidden_and_never_published_first(): void {
		$seen = array();
		add_action(
			'transition_post_status',
			static function ( $new, $old, $post ) use ( &$seen ) {
				if ( 'interest' === $post->post_type ) {
					$seen[] = $new;
				}
			},
			10,
			3
		);

		$id = $this->make( 'potential-spam', 'spam' );

		$this->assertSame( 'potential-spam', get_post_status( $id ) );
		$this->assertNotContains( 'publish', $seen );
	}

	public function test_notify_emails_dave_with_the_details_and_a_link(): void {
		$id = $this->make();

		$this->assertTrue( cptdp_interest_notify( $id ) );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertSame( 'dave@example.com', $sent->to[0][0] );
		$this->assertStringContainsString( 'Interest from Jane Citizen', $sent->subject );
		$this->assertStringContainsString( 'Help with: Social Media Marketing, Something else', $sent->body );
		$this->assertStringContainsString( 'Time: Tue 15 Jan 2030, 10:30 (Australia/Sydney)', $sent->body );
		$this->assertStringContainsString( '2030-01-14 23:30 UTC', $sent->body );
		$this->assertStringContainsString( "Mon 14 Jan 2030, 15:30 (America/Los_Angeles, the visitor's time)", $sent->body );
		$this->assertStringContainsString( 'post.php?post=' . $id . '&action=edit', $sent->body );
		$this->assertStringContainsString( 'Reply-To: Jane Citizen <jane@example.com>', $sent->header );
		$this->assertSame( 'yes', get_post_meta( $id, 'cptdp_mail_sent', true ) );
	}

	public function test_notify_without_a_contact_address_sends_nothing_and_records_no(): void {
		delete_option( 'cptdp_contact' );
		$id = $this->make();

		$this->assertFalse( cptdp_interest_notify( $id ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
		$this->assertSame( 'no', get_post_meta( $id, 'cptdp_mail_sent', true ) );
	}

	public function test_notify_ignores_anything_that_is_not_an_interest(): void {
		$this->assertFalse( cptdp_interest_notify( self::factory()->post->create() ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_not_spam_publishes_records_and_sends_the_held_back_email(): void {
		$id = $this->make( 'potential-spam', 'spam' );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );

		$this->assertTrue( cptdp_interest_mark_not_spam( $id ) );

		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'manual-ham', get_post_meta( $id, 'cptdp_spam_check', true ) );
		$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_not_spam_refuses_a_visible_item_and_other_types(): void {
		$id = $this->make( 'publish' );

		$this->assertFalse( cptdp_interest_mark_not_spam( $id ) );
		$this->assertFalse( cptdp_interest_mark_not_spam( self::factory()->post->create() ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_times_are_stored_in_utc(): void {
		$id = $this->make();

		$this->assertSame( '2030-01-14T23:30:00Z', get_post_meta( $id, 'cptdp_slot_utc', true ) );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', (string) get_post_meta( $id, 'cptdp_slot_utc', true ) );

		$slot = cptdp_interest_slot( $id );
		$this->assertSame( 'UTC', $slot->getTimezone()->getName() );
		$this->assertSame( '2030-01-14 23:30', $slot->format( 'Y-m-d H:i' ) );

		// WordPress keeps the moment received in UTC (post_date_gmt) and the same moment in the site zone (post_date).
		$post = get_post( $id );
		$this->assertEqualsWithDelta( time(), strtotime( $post->post_date_gmt . ' UTC' ), 5 );
		$this->assertSame( get_date_from_gmt( $post->post_date_gmt ), $post->post_date );
	}

	public function test_a_missing_or_wrong_visitor_zone_is_ignored_when_labelling(): void {
		$id = cptdp_interest_create( $this->clean( array( 'timezone' => '' ) ), 'publish', 'ham' );
		$this->assertSame( '', cptdp_interest_visitor_tz( $id ) );
		$this->assertStringNotContainsString( "visitor's time", cptdp_interest_time_label( $id ) );

		update_post_meta( $id, 'cptdp_visitor_tz', 'Mars/Phobos' );
		$this->assertSame( '', cptdp_interest_visitor_tz( $id ) );
		$this->assertStringContainsString( 'UTC', cptdp_interest_time_label( $id ) );
	}

	public function test_a_time_tag_carries_the_utc_instant_and_site_zone_text(): void {
		$tag = cptdp_interest_time_tag( gmmktime( 23, 30, 0, 1, 14, 2030 ) );

		$this->assertStringContainsString( 'datetime="2030-01-14T23:30:00Z"', $tag );
		$this->assertStringContainsString( 'data-cptdp-local', $tag );
		$this->assertStringContainsString( '15 Jan 2030, 10:30', $tag ); // Sydney, UTC+11 in January.
	}

	public function test_the_date_cell_is_a_time_tag_in_utc(): void {
		$id = $this->make();

		ob_start();
		cptdp_interest_column( 'cptdp_received', $id );
		$html = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z" data-cptdp-local>/', $html );
	}

	public function test_the_list_columns_follow_the_wireframe(): void {
		$this->assertSame(
			array( 'cb', 'cptdp_received', 'title', 'cptdp_email', 'cptdp_service' ),
			array_keys( cptdp_interest_columns( array() ) )
		);
	}

	public function test_the_client_ip_is_validated(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertSame( '203.0.113.7', cptdp_contact_client_ip() );

		$_SERVER['REMOTE_ADDR'] = 'not an ip';
		$this->assertSame( '', cptdp_contact_client_ip() );

		add_filter(
			'cptdp_contact_client_ip',
			static function () {
				return '2001:db8::1';
			}
		);
		$this->assertSame( '2001:db8::1', cptdp_contact_client_ip() );

		unset( $_SERVER['REMOTE_ADDR'] );
	}

	public function test_the_cells_show_email_and_service_escaped(): void {
		$id = cptdp_interest_create( $this->clean( array( 'help' => array( '<b>x</b>' ) ) ), 'publish', 'ham' );

		ob_start();
		cptdp_interest_column( 'cptdp_email', $id );
		cptdp_interest_column( 'cptdp_service', $id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'jane@example.com', $html );
		$this->assertStringContainsString( '&lt;b&gt;x&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>', $html );
	}

	public function test_rows_offer_view_and_trash_spam_adds_not_spam_and_trash_has_restore_and_delete(): void {
		$core    = array(
			'edit'    => 'e',
			'inline'  => 'i',
			'trash'   => 't',
			'untrash' => 'u',
			'delete'  => 'd',
		);
		$visible = get_post( $this->make( 'publish' ) );
		$spam    = get_post( $this->make( 'potential-spam', 'spam' ) );
		$gone    = get_post( $this->make( 'publish' ) );
		wp_trash_post( $gone->ID );
		$gone = get_post( $gone->ID );

		$this->assertSame( array( 'view', 'trash' ), array_keys( cptdp_interest_row_actions( $core, $visible ) ) );

		$actions = cptdp_interest_row_actions( $core, $spam );
		$this->assertSame( array( 'view', 'notspam', 'trash' ), array_keys( $actions ) );
		$this->assertStringContainsString( '_wpnonce=', $actions['notspam'] );
		$this->assertStringContainsString( 'action=cptdp_interest_not_spam', $actions['notspam'] );

		$this->assertSame( array( 'untrash', 'delete' ), array_keys( cptdp_interest_row_actions( $core, $gone ) ) );
	}

	public function test_a_trashed_potential_spam_item_is_restored_as_potential_spam(): void {
		$id = $this->make( 'potential-spam', 'spam' );

		wp_trash_post( $id );
		wp_untrash_post( $id );

		$this->assertSame( 'potential-spam', get_post_status( $id ) );
	}

	public function test_other_post_types_keep_their_row_actions(): void {
		$post = get_post( self::factory()->post->create() );

		$this->assertSame( array( 'edit' => 'x' ), cptdp_interest_row_actions( array( 'edit' => 'x' ), $post ) );
	}

	public function test_bulk_actions_are_core_s_without_edit(): void {
		$this->assertSame(
			array( 'trash', 'untrash' ),
			array_keys( cptdp_interest_bulk_actions( array( 'edit' => 'e', 'trash' => 't', 'untrash' => 'u' ) ) )
		);
	}

	public function test_there_is_no_published_tab(): void {
		$this->assertSame( array( 'all' => 'a', 'potential-spam' => 'b' ), cptdp_interest_views( array( 'all' => 'a', 'publish' => 'p', 'potential-spam' => 'b' ) ) );
	}

	public function test_the_badge_counts_only_unopened_visible_items_and_opening_one_clears_it(): void {
		$first = $this->make( 'publish' );
		$this->make( 'publish' );
		$this->make( 'potential-spam', 'spam' );

		$this->assertSame( 2, cptdp_interest_unseen_count() );

		$_GET['post'] = (string) $first;
		cptdp_interest_mark_seen_on_open();

		$this->assertSame( 1, cptdp_interest_unseen_count() );
	}

	public function test_opening_a_potential_spam_item_does_not_mark_it_seen(): void {
		$id           = $this->make( 'potential-spam', 'spam' );
		$_GET['post'] = (string) $id;

		cptdp_interest_mark_seen_on_open();

		$this->assertSame( '', get_post_meta( $id, 'cptdp_seen', true ) );
	}

	public function test_the_badge_is_added_to_the_menu_item(): void {
		global $menu;

		$this->make( 'publish' );
		$menu = array( 99 => array( 'Interests', 'edit_posts', 'edit.php?post_type=interest' ) );

		cptdp_interest_menu_badge();

		$this->assertStringContainsString( '<span class="awaiting-mod">1</span>', $menu[99][0] );
	}

	public function test_the_view_is_read_only_escaped_and_shows_how_it_was_handled(): void {
		$id   = cptdp_interest_create( $this->clean( array( 'name' => 'Eve <script>x</script>', 'message' => "Line one\nLine <b>two</b>" ) ), 'publish', 'ham' );
		cptdp_interest_notify( $id );

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Read-only', $html );
		$this->assertStringContainsString( 'Eve &lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'Line one<br />', $html );
		$this->assertStringContainsString( 'Not spam (Akismet)', $html );
		$this->assertStringContainsString( 'IP address', $html );
		$this->assertStringContainsString( '198.51.100.9', $html );
		$this->assertStringContainsString( '<time datetime="2030-01-14T23:30:00Z" data-cptdp-local>', $html );
		$this->assertStringContainsString( "Visitor's time: Mon 14 Jan 2030, 15:30 (America/Los_Angeles)", $html );
		$this->assertStringContainsString( 'Sent', $html );
		$this->assertStringContainsString( 'Move to Trash', $html );
		$this->assertStringNotContainsString( 'cptdp_interest_not_spam', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	public function test_a_spam_item_view_offers_not_spam(): void {
		$id = $this->make( 'potential-spam', 'spam' );

		ob_start();
		cptdp_interest_render_box( get_post( $id ) );

		$this->assertStringContainsString( 'cptdp_interest_not_spam', ob_get_clean() );
	}

	public function test_the_edit_screen_loses_the_publish_box(): void {
		global $wp_meta_boxes;

		add_meta_box( 'submitdiv', 'Publish', '__return_true', 'interest', 'side' );
		cptdp_interest_add_box();

		$this->assertFalse( $wp_meta_boxes['interest']['side']['core']['submitdiv'] ?? false );
		$this->assertArrayHasKey( 'cptdp_interest_view', $wp_meta_boxes['interest']['normal']['high'] );
	}

	public function test_the_admin_script_loads_only_on_the_interest_screens(): void {
		set_current_screen( 'edit-interest' );
		cptdp_interest_admin_assets();
		$this->assertTrue( wp_script_is( 'cptdp-admin-interest', 'enqueued' ) );

		wp_dequeue_script( 'cptdp-admin-interest' );
		set_current_screen( 'edit-post' );
		cptdp_interest_admin_assets();
		$this->assertFalse( wp_script_is( 'cptdp-admin-interest', 'enqueued' ) );
	}

	public function test_the_admin_script_file_rewrites_time_tags_only(): void {
		$js = (string) file_get_contents( CPTDP_PATH . 'assets/admin-interest.js' );

		$this->assertStringContainsString( 'time[data-cptdp-local]', $js );
		$this->assertStringContainsString( 'timeZoneName', $js );
		$this->assertStringNotContainsString( 'timeZone:', $js ); // The viewer's own zone: never a fixed one.
	}

	public function test_the_list_warns_while_there_is_no_akismet_key(): void {
		set_current_screen( 'edit-interest' );

		ob_start();
		cptdp_interest_notices();
		$this->assertStringContainsString( 'Spam check is off', ob_get_clean() );

		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'abc123def456';
			}
		);
		ob_start();
		cptdp_interest_notices();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_the_key_comes_from_the_option_and_only_plain_keys_are_accepted(): void {
		$this->assertSame( '', cptdp_akismet_key() );

		update_option( 'wordpress_api_key', ' abc123def456 ' );
		$this->assertSame( 'abc123def456', cptdp_akismet_key() );

		update_option( 'wordpress_api_key', 'evil.example/x' );
		$this->assertSame( '', cptdp_akismet_key() );
	}
}
```
In `test_the_edit_screen_loses_the_publish_box`, if `$wp_meta_boxes` structure differs (`remove_meta_box` sets the entry to `false`), the first assertion is correct; report if not.


### A4. `wp-content/plugins/custom-post-types-dp/includes/settings/contact-request.php` (new, in full)

```php
<?php
/**
 * Contact request (DPH-025 to DPH-028): the endpoint behind the Home scheduler.
 *
 * One public REST route: validate, check with Akismet, save an Interest (hidden as potential spam when Akismet says so),
 * then email Dave unless it is spam. Saving, the Akismet call and the email live in interest.php.
 * The slots, the bookable window and the service options live here so the theme (which draws them) and the endpoint
 * (which validates them) can never disagree. DPH-031 will replace cptdp_contact_time_slots() and cptdp_contact_window().
 *
 * @package CustomPostTypesDP
 */

defined( 'ABSPATH' ) || exit;

/**
 * The times offered on every bookable day, "HH:MM" in the site's time zone.
 *
 * @return string[]
 */
function cptdp_contact_time_slots(): array {
	$slots = array();
	for ( $minutes = 9 * 60; $minutes <= ( 16 * 60 + 30 ); $minutes += 30 ) {
		$slots[] = sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	$slots = apply_filters( 'cptdp_contact_time_slots', $slots );
	if ( ! is_array( $slots ) ) {
		return array();
	}

	return array_values(
		array_filter(
			$slots,
			static function ( $slot ) {
				return is_string( $slot ) && 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $slot );
			}
		)
	);
}

/**
 * The bookable days as "YYYY-MM-DD" in the site's time zone: tomorrow up to 365 days ahead. `today` is the server's
 * today (the page never decides it).
 *
 * @return array{today:string,first:string,last:string}
 */
function cptdp_contact_window(): array {
	$today = new DateTimeImmutable( 'now', wp_timezone() );

	return array(
		'today' => $today->format( 'Y-m-d' ),
		'first' => $today->modify( '+1 day' )->format( 'Y-m-d' ),
		'last'  => $today->modify( '+365 days' )->format( 'Y-m-d' ),
	);
}

/**
 * The time zone shown next to the times: the city of the site zone ("Sydney"), else the zone string.
 */
function cptdp_contact_timezone_label(): string {
	$zone = wp_timezone_string();
	$pos  = strrpos( $zone, '/' );
	if ( false !== $pos ) {
		$zone = substr( $zone, $pos + 1 );
	}

	return str_replace( '_', ' ', $zone );
}

/**
 * What the prospect can ask help with: the top-level published Services (menu order, then title, the order of the
 * "What I do" list), then "Something else". `id` is a string: the Service's post ID, or `other`.
 *
 * @return array<int,array{id:string,title:string}>
 */
function cptdp_contact_service_options(): array {
	$ids = get_posts(
		array(
			'post_type'      => 'service',
			'post_status'    => 'publish',
			'post_parent'    => 0,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
		)
	);

	$options = array();
	foreach ( $ids as $id ) {
		$title = trim( html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' ) );
		if ( '' !== $title ) {
			$options[] = array(
				'id'    => (string) $id,
				'title' => $title,
			);
		}
	}

	$options[] = array(
		'id'    => 'other',
		'title' => __( 'Something else', 'custom-post-types-dp' ),
	);

	return $options;
}

/**
 * The site zone's offset from UTC, in minutes, for each stretch of the bookable window: `[ { from: 'YYYY-MM-DD',
 * minutes: 660 }, ... ]`, the first entry from today and one more per daylight-saving change (the new offset applies
 * from that local day, which is right for slots after the small hours). The page uses it to turn a chosen day and time
 * into an instant without a time zone library; the server never trusts it (it converts with PHP's own zone data).
 *
 * @return array<int,array{from:string,minutes:int}>
 */
function cptdp_contact_utc_offsets(): array {
	$zone   = wp_timezone();
	$window = cptdp_contact_window();
	$start  = new DateTimeImmutable( $window['today'] . ' 12:00', $zone );
	$end    = new DateTimeImmutable( $window['last'] . ' 12:00', $zone );
	$out    = array(
		array(
			'from'    => $window['today'],
			'minutes' => intdiv( $zone->getOffset( $start ), 60 ),
		),
	);

	foreach ( $zone->getTransitions( $start->getTimestamp(), $end->getTimestamp() ) as $index => $change ) {
		if ( 0 === $index ) {
			continue; // The state at the start, already the first entry.
		}
		$out[] = array(
			'from'    => ( new DateTimeImmutable( '@' . $change['ts'] ) )->setTimezone( $zone )->format( 'Y-m-d' ),
			'minutes' => intdiv( (int) $change['offset'], 60 ),
		);
	}

	return $out;
}

/**
 * Everything the theme needs to draw the scheduler.
 *
 * @return array<string,mixed>
 */
function cptdp_contact_config(): array {
	return array_merge(
		cptdp_contact_window(),
		array(
			'endpoint' => rest_url( 'cptdp/v1/contact-request' ),
			'slots'    => cptdp_contact_time_slots(),
			'timezone' => cptdp_contact_timezone_label(),
			'offsets'  => cptdp_contact_utc_offsets(),
			'services' => cptdp_contact_service_options(),
		)
	);
}

/**
 * Validate and clean a request body.
 *
 * @param array<string,mixed> $input Decoded body.
 * @return array<string,mixed>|WP_Error Clean values, or a 422 error naming the first bad field.
 */
function cptdp_contact_validate( array $input ) {
	$str  = static function ( $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	};
	$fail = static function ( string $field, string $message ): WP_Error {
		return new WP_Error(
			'cptdp_invalid_' . $field,
			$message,
			array(
				'status' => 422,
				'field'  => $field,
			)
		);
	};

	$name = trim( sanitize_text_field( $str( $input['name'] ?? '' ) ) );
	if ( '' === $name || mb_strlen( $name ) > 100 ) {
		return $fail( 'name', __( 'Please enter your name (100 characters at most).', 'custom-post-types-dp' ) );
	}

	$email = sanitize_email( $str( $input['email'] ?? '' ) );
	if ( ! is_email( $email ) || strlen( $email ) > 254 ) {
		return $fail( 'email', __( 'Please enter a valid email address.', 'custom-post-types-dp' ) );
	}

	$window = cptdp_contact_window();
	$date   = $str( $input['date'] ?? '' );
	$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date || $date < $window['first'] || $date > $window['last'] ) {
		return $fail( 'date', __( 'That day is not available.', 'custom-post-types-dp' ) );
	}

	$time = $str( $input['time'] ?? '' );
	if ( ! in_array( $time, cptdp_contact_time_slots(), true ) ) {
		return $fail( 'time', __( 'That time is not available.', 'custom-post-types-dp' ) );
	}

	// The one stored form of the time: UTC, converted here with PHP's own zone data.
	$when = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time, wp_timezone() );
	if ( ! $when ) {
		return $fail( 'time', __( 'That time is not available.', 'custom-post-types-dp' ) );
	}
	$slot_utc = $when->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );

	$zone = $str( $input['timezone'] ?? '' ); // The visitor's zone, for showing the time back to them. Optional.
	$zone = in_array( $zone, timezone_identifiers_list(), true ) ? $zone : '';

	$options = cptdp_contact_service_options();
	$known   = array_column( $options, 'id' );
	$given   = is_array( $input['services'] ?? null ) ? array_map( $str, $input['services'] ) : array();
	if ( array() === $given || count( $given ) > 10 || array_diff( $given, $known ) ) {
		return $fail( 'services', __( 'Please choose at least one thing you need help with.', 'custom-post-types-dp' ) );
	}

	$titles = array();
	foreach ( $options as $option ) {
		if ( in_array( $option['id'], $given, true ) ) {
			$titles[] = $option['title'];
		}
	}

	$message = trim( sanitize_textarea_field( $str( $input['message'] ?? '' ) ) );
	if ( mb_strlen( $message ) > 2000 ) {
		return $fail( 'message', __( 'Please keep the message under 2000 characters.', 'custom-post-types-dp' ) );
	}

	return array(
		'name'    => $name,
		'email'   => $email,
		'date'     => $date,
		'time'     => $time,
		'slot_utc' => $slot_utc,
		'timezone' => $zone,
		'help'     => $titles,
		'message' => $message,
	);
}

/**
 * The key of the rate-limit counter for this client (a hash, never the address).
 */
function cptdp_contact_client_key(): string {
	return 'cptdp_contact_rl_' . md5( cptdp_contact_client_ip() );
}

/**
 * Handle POST /cptdp/v1/contact-request.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function cptdp_contact_handle_request( WP_REST_Request $request ) {
	$input = $request->get_json_params();
	$input = is_array( $input ) ? $input : (array) $request->get_params();

	// Honeypot: a person never sees this field. Answer as if it worked, save and send nothing.
	if ( '' !== trim( is_scalar( $input['website'] ?? '' ) ? (string) $input['website'] : '' ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	$clean = cptdp_contact_validate( $input );
	if ( is_wp_error( $clean ) ) {
		return $clean;
	}
	$clean['ip'] = cptdp_contact_client_ip();

	// Before Akismet, so a flood never reaches the service.
	$key   = cptdp_contact_client_key();
	$count = (int) get_transient( $key );
	$limit = (int) apply_filters( 'cptdp_contact_rate_limit', 10 );
	if ( $count >= $limit ) {
		return new WP_Error(
			'cptdp_contact_rate_limited',
			__( 'Too many requests. Please try again later.', 'custom-post-types-dp' ),
			array( 'status' => 429 )
		);
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	// Check first, save second: the Interest is created straight into its final status, so it is never visible
	// before the check has run.
	$check = cptdp_akismet_check( $clean, $request );
	$spam  = 'spam' === $check;
	$id    = cptdp_interest_create( $clean, $spam ? 'potential-spam' : 'publish', $check );
	if ( is_wp_error( $id ) ) {
		return new WP_Error(
			'cptdp_contact_save_failed',
			__( 'The message could not be saved. Please try again.', 'custom-post-types-dp' ),
			array( 'status' => 500 )
		);
	}

	if ( ! $spam ) {
		cptdp_interest_notify( $id );
	}

	// The same answer for spam, so a bot learns nothing.
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Register the route. Public on purpose: the visitors are anonymous prospects. The honeypot, the rate limit and the
 * validation above are what protect it.
 */
function cptdp_contact_register_route(): void {
	register_rest_route(
		'cptdp/v1',
		'/contact-request',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'cptdp_contact_handle_request',
			'permission_callback' => '__return_true', // phpcs:ignore WordPressVIPMinimum.Security.PHPFilterFunctions -- public by design, see the docblock.
		)
	);
}
add_action( 'rest_api_init', 'cptdp_contact_register_route' );
```



### A5. `wp-content/plugins/custom-post-types-dp/tests/settings/test-contact-request.php` (new, in full)

```php
<?php
/**
 * Contact request endpoint tests (DPH-027).
 *
 * @package CustomPostTypesDP
 */

/**
 * @group settings
 */
class CPTDP_Test_Contact_Request extends CPTDP_Test_Case {

	/** @var int[] Top-level service IDs, in menu order. */
	private $services = array();

	/** @var array<int,array<string,mixed>> Akismet calls seen by with_akismet(). */
	private $akismet_calls = array();

	public function set_up(): void {
		parent::set_up();

		$GLOBALS['wp_rest_server'] = null;
		rest_get_server();
		reset_phpmailer_instance();
		wp_set_current_user( 0 );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		update_option( 'timezone_string', 'Australia/Sydney' );
		$this->akismet_calls = array();

		$first            = self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Social Media Marketing', 'menu_order' => 1, 'post_status' => 'publish' ) );
		$second           = self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Digital Marketing', 'menu_order' => 2, 'post_status' => 'publish' ) );
		$this->services   = array( $first, $second );
		self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Analytics', 'post_parent' => $first, 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_type' => 'service', 'post_title' => 'Hidden Draft', 'menu_order' => 3, 'post_status' => 'draft' ) );
	}

	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		unset( $_SERVER['REMOTE_ADDR'] );

		parent::tear_down();
	}

	/**
	 * A valid body, with overrides.
	 *
	 * @param array<string,mixed> $override Values to replace.
	 * @return array<string,mixed>
	 */
	private function valid( array $override = array() ): array {
		$window = cptdp_contact_window();

		return array_merge(
			array(
				'date'     => $window['first'],
				'time'     => '10:30',
				'timezone' => 'America/Los_Angeles',
				'name'     => 'Jane Citizen',
				'email'    => 'jane@example.com',
				'services' => array( (string) $this->services[0], 'other' ),
				'message'  => 'I need a plan.',
				'website'  => '',
			),
			$override
		);
	}

	/**
	 * Give the site an Akismet key and answer its calls with `$answer` (a body, or a WP_Error).
	 *
	 * @param string|WP_Error $answer Body of the reply.
	 * @param int             $code   HTTP status of the reply.
	 */
	private function with_akismet( $answer, int $code = 200 ): void {
		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'abc123def456';
			}
		);
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $answer, $code ) {
				$this->akismet_calls[] = array(
					'url'  => $url,
					'body' => $args['body'],
				);
				if ( is_wp_error( $answer ) ) {
					return $answer;
				}

				return array(
					'headers'  => array(),
					'body'     => $answer,
					'response' => array(
						'code'    => $code,
						'message' => '',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			},
			10,
			3
		);
	}

	/**
	 * Interest IDs in one status.
	 *
	 * @return int[]
	 */
	private function interest_ids( string $status ): array {
		return get_posts(
			array(
				'post_type'      => 'interest',
				'post_status'    => $status,
				'fields'         => 'ids',
				'posts_per_page' => -1,
			)
		);
	}

	private function post( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/cptdp/v1/contact-request' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'User-Agent', 'TestBrowser/9' );
		$request->set_body( wp_json_encode( $body ) );

		return rest_get_server()->dispatch( $request );
	}

	public function test_slots_are_sixteen_half_hours_from_nine(): void {
		$slots = cptdp_contact_time_slots();

		$this->assertCount( 16, $slots );
		$this->assertSame( '09:00', $slots[0] );
		$this->assertSame( '09:30', $slots[1] );
		$this->assertSame( '16:30', $slots[15] );
	}

	public function test_slots_filter_drops_malformed_entries(): void {
		add_filter(
			'cptdp_contact_time_slots',
			static function () {
				return array( '08:00', '25:00', 'noon', 12 );
			}
		);

		$this->assertSame( array( '08:00' ), cptdp_contact_time_slots() );
	}

	public function test_window_starts_tomorrow_and_ends_365_days_later(): void {
		$window = cptdp_contact_window();
		$today  = new DateTimeImmutable( $window['today'], wp_timezone() );

		$this->assertSame( $today->modify( '+1 day' )->format( 'Y-m-d' ), $window['first'] );
		$this->assertSame( $today->modify( '+365 days' )->format( 'Y-m-d' ), $window['last'] );
	}

	public function test_service_options_are_top_level_published_in_order_then_other(): void {
		$options = cptdp_contact_service_options();

		$this->assertSame(
			array( (string) $this->services[0], (string) $this->services[1], 'other' ),
			wp_list_pluck( $options, 'id' )
		);
		$this->assertSame( array( 'Social Media Marketing', 'Digital Marketing', 'Something else' ), wp_list_pluck( $options, 'title' ) );
	}

	public function test_a_valid_request_is_saved_visible_and_emailed(): void {
		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'Jane Citizen', get_the_title( $ids[0] ) );
		$this->assertSame( 'jane@example.com', get_post_meta( $ids[0], 'cptdp_email', true ) );
		$this->assertSame( 'I need a plan.', get_post_field( 'post_content', $ids[0] ) );
		$this->assertSame( array( 'Social Media Marketing', 'Something else' ), get_post_meta( $ids[0], 'cptdp_services', true ) );
		$this->assertSame( 'skipped', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
		$this->assertSame( 'yes', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
		$this->assertSame( 'America/Los_Angeles', get_post_meta( $ids[0], 'cptdp_visitor_tz', true ) );

		// 10:30 on the chosen day in Dave's zone, stored as the same instant in UTC.
		$window   = cptdp_contact_window();
		$expected = ( new DateTimeImmutable( $window['first'] . ' 10:30', new DateTimeZone( 'Australia/Sydney' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$this->assertSame( $expected, get_post_meta( $ids[0], 'cptdp_slot_utc', true ) );

		$mailer = tests_retrieve_phpmailer_instance();
		$sent   = $mailer->get_sent();
		$this->assertNotFalse( $sent );
		$this->assertSame( 'dave@example.com', $sent->to[0][0] );
		$this->assertStringContainsString( 'Interest from Jane Citizen', $sent->subject );
		$this->assertStringContainsString( 'Name: Jane Citizen', $sent->body );
		$this->assertStringContainsString( 'Email: jane@example.com', $sent->body );
		$this->assertStringContainsString( '10:30', $sent->body );
		$this->assertStringContainsString( 'Australia/Sydney', $sent->body );
		$this->assertStringContainsString( ' UTC', $sent->body );
		$this->assertStringContainsString( "(America/Los_Angeles, the visitor's time)", $sent->body );
		$this->assertStringContainsString( 'Help with: Social Media Marketing, Something else', $sent->body );
		$this->assertStringContainsString( 'I need a plan.', $sent->body );
		$this->assertStringContainsString( 'post.php?post=' . $ids[0] . '&action=edit', $sent->body );
		$this->assertStringContainsString( 'Reply-To: Jane Citizen <jane@example.com>', $sent->header );
		$this->assertFalse( $mailer->get_sent( 1 ) );
	}

	public function test_an_empty_message_is_allowed(): void {
		$this->assertSame( 200, $this->post( $this->valid( array( 'message' => '' ) ) )->get_status() );
		$this->assertStringContainsString( '(none)', tests_retrieve_phpmailer_instance()->get_sent()->body );
	}

	public function test_each_invalid_field_is_a_422_naming_the_field_and_saves_nothing(): void {
		$window = cptdp_contact_window();
		$today  = new DateTimeImmutable( $window['today'], wp_timezone() );

		$cases = array(
			'name empty'       => array( 'name', array( 'name' => '   ' ) ),
			'name too long'    => array( 'name', array( 'name' => str_repeat( 'a', 101 ) ) ),
			'email bad'        => array( 'email', array( 'email' => 'not-an-email' ) ),
			'date today'       => array( 'date', array( 'date' => $window['today'] ) ),
			'date past'        => array( 'date', array( 'date' => '2000-01-01' ) ),
			'date too far'     => array( 'date', array( 'date' => $today->modify( '+366 days' )->format( 'Y-m-d' ) ) ),
			'date impossible'  => array( 'date', array( 'date' => '2027-02-31' ) ),
			'date missing'     => array( 'date', array( 'date' => '' ) ),
			'time not offered' => array( 'time', array( 'time' => '03:00' ) ),
			'services empty'   => array( 'services', array( 'services' => array() ) ),
			'services unknown' => array( 'services', array( 'services' => array( '999999' ) ) ),
			'services string'  => array( 'services', array( 'services' => 'other' ) ),
			'message long'     => array( 'message', array( 'message' => str_repeat( 'a', 2001 ) ) ),
		);

		foreach ( $cases as $label => $case ) {
			$response = $this->post( $this->valid( $case[1] ) );

			$this->assertSame( 422, $response->get_status(), $label );
			$this->assertSame( $case[0], $response->get_data()['data']['field'], $label );
		}

		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->interest_ids( 'potential-spam' ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_without_a_contact_email_it_is_still_saved_and_no_email_is_sent(): void {
		delete_option( 'cptdp_contact' );

		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'no', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_honeypot_answers_200_and_saves_and_sends_nothing(): void {
		$this->with_akismet( 'false' );

		$response = $this->post( $this->valid( array( 'website' => 'http://spam.example' ) ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->akismet_calls );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_rate_limit_answers_429_after_the_limit_and_never_reaches_akismet(): void {
		$this->with_akismet( 'false' );
		add_filter(
			'cptdp_contact_rate_limit',
			static function () {
				return 2;
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
		$this->assertSame( 429, $this->post( $this->valid() )->get_status() );
		$this->assertCount( 2, $this->akismet_calls );
		$this->assertCount( 2, $this->interest_ids( 'publish' ) );
	}

	public function test_invalid_requests_do_not_use_up_the_rate_limit(): void {
		add_filter(
			'cptdp_contact_rate_limit',
			static function () {
				return 1;
			}
		);

		$this->assertSame( 422, $this->post( $this->valid( array( 'name' => '' ) ) )->get_status() );
		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
	}

	public function test_a_name_cannot_inject_headers(): void {
		$this->post( $this->valid( array( 'name' => "Eve\r\nBcc: victim@example.com" ) ) );

		$sent = tests_retrieve_phpmailer_instance()->get_sent();
		$this->assertNotFalse( $sent );
		$this->assertStringNotContainsString( "\nBcc:", $sent->header );
		$this->assertStringNotContainsString( 'victim@example.com', $sent->header );
	}

	public function test_a_failed_mail_still_saves_the_interest_and_says_so(): void {
		add_filter( 'pre_wp_mail', '__return_false' );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'no', get_post_meta( $ids[0], 'cptdp_mail_sent', true ) );
	}

	public function test_a_save_failure_is_a_500_and_sends_nothing(): void {
		add_filter( 'wp_insert_post_empty_content', '__return_true' ); // Makes wp_insert_post() refuse and return a WP_Error.

		$response = $this->post( $this->valid() );

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_the_route_is_public(): void {
		wp_set_current_user( 0 );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );
	}

	public function test_akismet_says_genuine_so_it_is_visible_and_emailed(): void {
		$this->with_akismet( 'false' );

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertCount( 1, $this->akismet_calls );
		$call = $this->akismet_calls[0];
		$this->assertSame( 'https://abc123def456.rest.akismet.com/1.1/comment-check', $call['url'] );
		$this->assertSame( 'contact-form', $call['body']['comment_type'] );
		$this->assertSame( 'Jane Citizen', $call['body']['comment_author'] );
		$this->assertSame( 'jane@example.com', $call['body']['comment_author_email'] );
		$this->assertSame( 'I need a plan.', $call['body']['comment_content'] );
		$this->assertSame( '203.0.113.7', $call['body']['user_ip'] );
		$this->assertSame( 'TestBrowser/9', $call['body']['user_agent'] );
		$this->assertSame( home_url(), $call['body']['blog'] );

		$ids = $this->interest_ids( 'publish' );
		$this->assertCount( 1, $ids );
		$this->assertSame( 'ham', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
		$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent() );
	}

	public function test_akismet_says_spam_so_it_is_saved_hidden_and_not_emailed_and_the_visitor_sees_success(): void {
		$this->with_akismet( 'true' );

		$response = $this->post( $this->valid() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'ok' => true ), $response->get_data() );
		$this->assertSame( array(), $this->interest_ids( 'publish' ) );
		$this->assertSame( array(), $this->interest_ids( 'any' ) );

		$spam = $this->interest_ids( 'potential-spam' );
		$this->assertCount( 1, $spam );
		$this->assertSame( 'spam', get_post_meta( $spam[0], 'cptdp_spam_check', true ) );
		$this->assertFalse( tests_retrieve_phpmailer_instance()->get_sent() );
		$this->assertSame( 0, cptdp_interest_unseen_count() );
	}

	public function test_akismet_trouble_fails_open(): void {
		$cases = array(
			'network error' => array( new WP_Error( 'http_request_failed', 'timeout' ), 200 ),
			'bad key'       => array( 'invalid', 200 ),
			'server error'  => array( 'true', 500 ),
			'nonsense'      => array( '<html>', 200 ),
		);

		foreach ( $cases as $label => $case ) {
			remove_all_filters( 'pre_http_request' );
			remove_all_filters( 'cptdp_akismet_key' );
			reset_phpmailer_instance();
			$before = count( $this->interest_ids( 'publish' ) );

			$this->with_akismet( $case[0], $case[1] );
			$this->assertSame( 200, $this->post( $this->valid() )->get_status(), $label );

			$ids = $this->interest_ids( 'publish' );
			$this->assertCount( $before + 1, $ids, $label );
			$this->assertSame( 'error', get_post_meta( $ids[0], 'cptdp_spam_check', true ), $label );
			$this->assertNotFalse( tests_retrieve_phpmailer_instance()->get_sent(), $label );
		}
	}

	public function test_without_a_key_akismet_is_not_called(): void {
		add_filter(
			'pre_http_request',
			function () {
				$this->akismet_calls[] = 'called';

				return new WP_Error( 'blocked', 'no network in tests' );
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertSame( array(), $this->akismet_calls );
		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( 'skipped', get_post_meta( $ids[0], 'cptdp_spam_check', true ) );
	}

	public function test_an_unsafe_key_is_never_put_in_a_url(): void {
		$this->with_akismet( 'false' );
		remove_all_filters( 'cptdp_akismet_key' );
		add_filter(
			'cptdp_akismet_key',
			static function () {
				return 'evil.example/x';
			}
		);

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$this->assertSame( array(), $this->akismet_calls );
	}

	public function test_the_ip_is_saved_on_the_interest_and_the_browser_is_not_stored(): void {
		global $wpdb;
		$this->with_akismet( 'false' );

		$this->post( $this->valid() );

		$this->assertSame( '203.0.113.7', $this->akismet_calls[0]['body']['user_ip'] );
		$this->assertSame( 'TestBrowser/9', $this->akismet_calls[0]['body']['user_agent'] );

		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( '203.0.113.7', get_post_meta( $ids[0], 'cptdp_ip', true ) );

		// The address lives in exactly one place: the Interest's `cptdp_ip`. The browser lives nowhere.
		$ip = '%' . $wpdb->esc_like( '203.0.113.7' ) . '%';
		$ua = '%' . $wpdb->esc_like( 'TestBrowser' ) . '%';
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $ip ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $ua ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_title LIKE %s OR post_content LIKE %s", $ip, $ip, $ua ) ) );
		$this->assertSame( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s OR option_value LIKE %s", $ip, $ip, $ua ) ) );
	}

	public function test_a_spam_submission_keeps_its_ip_too(): void {
		$this->with_akismet( 'true' );

		$this->post( $this->valid() );

		$spam = $this->interest_ids( 'potential-spam' );
		$this->assertSame( '203.0.113.7', get_post_meta( $spam[0], 'cptdp_ip', true ) );
	}

	public function test_an_invalid_address_is_stored_as_empty(): void {
		$_SERVER['REMOTE_ADDR'] = 'not an ip';

		$this->assertSame( 200, $this->post( $this->valid() )->get_status() );

		$ids = $this->interest_ids( 'publish' );
		$this->assertSame( '', get_post_meta( $ids[0], 'cptdp_ip', true ) );
	}

	public function test_the_stored_time_is_utc_whatever_the_site_zone(): void {
		$window = cptdp_contact_window();

		foreach ( array( 'Australia/Sydney', 'America/Los_Angeles', 'UTC', 'Asia/Kolkata' ) as $zone ) {
			update_option( 'timezone_string', $zone );
			$window = cptdp_contact_window(); // The window follows the zone, so ask again.
			$ids    = $this->interest_ids( 'publish' );

			$this->assertSame( 200, $this->post( $this->valid( array( 'date' => $window['first'] ) ) )->get_status(), $zone );

			$new = array_values( array_diff( $this->interest_ids( 'publish' ), $ids ) );
			$this->assertCount( 1, $new, $zone );
			$expected = ( new DateTimeImmutable( $window['first'] . ' 10:30', new DateTimeZone( $zone ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
			$this->assertSame( $expected, get_post_meta( $new[0], 'cptdp_slot_utc', true ), $zone );
		}
	}

	public function test_the_browser_cannot_send_its_own_utc_instant(): void {
		$window = cptdp_contact_window();

		$this->post( $this->valid( array( 'slot_utc' => '1999-01-01T00:00:00Z', 'slot' => '1999-01-01T00:00:00Z' ) ) );

		$ids      = $this->interest_ids( 'publish' );
		$expected = ( new DateTimeImmutable( $window['first'] . ' 10:30', new DateTimeZone( 'Australia/Sydney' ) ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d\TH:i:s\Z' );
		$this->assertSame( $expected, get_post_meta( $ids[0], 'cptdp_slot_utc', true ) );
	}

	public function test_an_unknown_visitor_zone_is_stored_as_empty_and_does_not_fail_the_request(): void {
		foreach ( array( 'Mars/Phobos', '', 'x; DROP TABLE', 123 ) as $zone ) {
			$response = $this->post( $this->valid( array( 'timezone' => $zone ) ) );
			$this->assertSame( 200, $response->get_status() );
		}

		foreach ( $this->interest_ids( 'publish' ) as $id ) {
			$this->assertSame( '', get_post_meta( $id, 'cptdp_visitor_tz', true ) );
		}
	}

	public function test_the_utc_offsets_table_follows_the_site_zone(): void {
		$window  = cptdp_contact_window();
		$offsets = cptdp_contact_utc_offsets();

		$this->assertSame( $window['today'], $offsets[0]['from'] );
		$this->assertGreaterThanOrEqual( 2, count( $offsets ) ); // A year in Sydney always crosses a daylight-saving change.
		$froms = wp_list_pluck( $offsets, 'from' );
		$sorted = $froms;
		sort( $sorted );
		$this->assertSame( $sorted, $froms );
		foreach ( $offsets as $entry ) {
			$this->assertContains( $entry['minutes'], array( 600, 660 ) );
		}

		update_option( 'timezone_string', 'UTC' );
		$this->assertSame( 1, count( cptdp_contact_utc_offsets() ) );
		$this->assertSame( 0, cptdp_contact_utc_offsets()[0]['minutes'] );

		update_option( 'timezone_string', 'Asia/Kolkata' );
		$this->assertSame( 330, cptdp_contact_utc_offsets()[0]['minutes'] );
	}

	public function test_config_carries_everything_the_theme_draws(): void {
		$config = cptdp_contact_config();

		$this->assertStringContainsString( 'cptdp/v1/contact-request', $config['endpoint'] );
		foreach ( array( 'today', 'first', 'last', 'slots', 'timezone', 'offsets', 'services' ) as $key ) {
			$this->assertArrayHasKey( $key, $config );
		}
	}
}
```

If `test_the_ip_is_saved_on_the_interest_and_the_browser_is_not_stored` fails on `options`, check whether the failing row is a transient: its name must be `_transient_cptdp_contact_rl_<md5>`, which contains neither string; anything else is a bug to report.

### A6. Plugin version
Edit `wp-content/plugins/custom-post-types-dp/custom-post-types-dp.php`: ` * Version:           0.33.0` → ` * Version:           0.34.0` and `define( 'CPTDP_VERSION', '0.33.0' );` → `define( 'CPTDP_VERSION', '0.34.0' );`. Then `grep -rn "0\.33\.0" wp-content/plugins/custom-post-types-dp --include=*.php --include=*.md --include=*.json -l` and report any other hit (do not edit vendored code).

### Gate A
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit --filter 'CPTDP_Test_Interest|CPTDP_Test_Contact_Request'
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
```
Expected: both new classes all green; the full suite = previous total + the new tests, nothing else changed. Also run `ddev wp eval 'var_dump( post_type_exists( "interest" ), get_post_status_object( "potential-spam" ) !== null );'` (both `bool(true)`) and open wp-admin once: the sidebar shows "Interests" with a lock and there is no "Add New".

---

## Phase B. Theme: pure modules (no DOM)

Folder `wp-content/themes/devportfolio/blocks/contact/`.

### B1. `blocks/contact/calendar.js` (new, in full)

```js
/**
 * Calendar maths for the Contact scheduler (DPH-025). Pure: dates are ISO strings "YYYY-MM-DD", months are
 * `{ y, m }` with m = 1 to 12. No clock and no time zone: "today", the first and the last bookable day all come
 * from the server, so string comparison is date comparison.
 */
const pad = ( n ) => String( n ).padStart( 2, '0' );

export function toIso( y, m, d ) {
	return `${ y }-${ pad( m ) }-${ pad( d ) }`;
}

/** `{ y, m, d }` or null for anything that is not "YYYY-MM-DD". */
export function parseIso( iso ) {
	const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec( iso || '' );
	return match ? { y: Number( match[ 1 ] ), m: Number( match[ 2 ] ), d: Number( match[ 3 ] ) } : null;
}

/** The month a date is in, or null. */
export function monthOf( iso ) {
	const parts = parseIso( iso );
	return parts ? { y: parts.y, m: parts.m } : null;
}

export function shiftMonth( { y, m }, delta ) {
	const index = y * 12 + ( m - 1 ) + delta;
	return { y: Math.floor( index / 12 ), m: ( index % 12 ) + 1 };
}

/** Whether any day of the month can be in the bookable range. */
export function monthInRange( { y, m }, first, last ) {
	const key = `${ y }-${ pad( m ) }`;
	return key >= first.slice( 0, 7 ) && key <= last.slice( 0, 7 );
}

export function isSelectable( iso, first, last ) {
	return iso >= first && iso <= last;
}

/** 0 for Monday to 6 for Sunday. */
export function weekdayIndex( y, m, d ) {
	return ( new Date( Date.UTC( y, m - 1, d ) ).getUTCDay() + 6 ) % 7;
}

export function daysInMonth( y, m ) {
	return new Date( Date.UTC( y, m, 0 ) ).getUTCDate();
}

/**
 * The month as weeks (Monday first) of 7 cells: `null` for the padding, else `{ iso, day, selectable }`.
 */
export function monthGrid( { y, m }, first, last ) {
	const cells = [];
	for ( let i = 0; i < weekdayIndex( y, m, 1 ); i++ ) {
		cells.push( null );
	}
	for ( let d = 1; d <= daysInMonth( y, m ); d++ ) {
		const iso = toIso( y, m, d );
		cells.push( { iso, day: d, selectable: isSelectable( iso, first, last ) } );
	}
	while ( cells.length % 7 !== 0 ) {
		cells.push( null );
	}

	const weeks = [];
	for ( let i = 0; i < cells.length; i += 7 ) {
		weeks.push( cells.slice( i, i + 7 ) );
	}
	return weeks;
}
```

### B2. `blocks/contact/steps.js` (new, in full)

```js
/**
 * The steps of the Contact card (DPH-025 to DPH-027). Pure: validation, crumb states, the next step and the
 * summary rows. Every word comes from the `strings` the server hands over; dates and times are time.js's job.
 */
export const LAST_STEP = 4;

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/**
 * Errors for one step as `{ field: message }`; empty when the step is fine. Fields: slot, name, email, services.
 */
export function validateStep( step, data, strings ) {
	const errors = {};

	if ( step === 1 && ! ( data.date && data.time ) ) {
		errors.slot = strings.slotRequired;
	}
	if ( step === 2 ) {
		if ( ! data.name.trim() ) {
			errors.name = strings.nameRequired;
		}
		const email = data.email.trim();
		if ( ! email ) {
			errors.email = strings.emailRequired;
		} else if ( ! EMAIL.test( email ) ) {
			errors.email = strings.emailInvalid;
		}
	}
	if ( step === 3 && data.services.length === 0 ) {
		errors.services = strings.needRequired;
	}

	return errors;
}

/** 'done' | 'current' | 'todo' for the four crumbs. Once sent, all four are done. */
export function crumbStates( current, finished ) {
	return [ 1, 2, 3, 4 ].map( ( n ) => {
		if ( finished || n < current ) {
			return 'done';
		}
		return n === current ? 'current' : 'todo';
	} );
}

/** Next goes to `returnTo` while the visitor is editing from the summary, else one step on. */
export function nextStep( current, returnTo ) {
	if ( returnTo && current < returnTo ) {
		return returnTo;
	}
	return Math.min( LAST_STEP, current + 1 );
}

export function prevStep( current ) {
	return Math.max( 1, current - 1 );
}

/**
 * The summary card: `{ key, step, label, value }` per row. `step` is where its Edit link goes.
 *
 * @param {Object} data      `{ name, email, services: string[], message }`
 * @param {Array}  services  `[ { id, title } ]` in display order
 * @param {string} timeValue The chosen time, already worded for this visitor (see time.js and `describe()` in view.js)
 */
export function summaryRows( data, services, strings, timeValue ) {
	const help = services.filter( ( service ) => data.services.includes( service.id ) ).map( ( service ) => service.title );

	return [
		{ key: 'time', step: 1, label: strings.summaryTime, value: timeValue },
		{ key: 'name', step: 2, label: strings.summaryName, value: data.name.trim() },
		{ key: 'email', step: 2, label: strings.summaryEmail, value: data.email.trim() },
		{ key: 'help', step: 3, label: strings.summaryHelp, value: help.join( ', ' ) },
		{ key: 'message', step: 3, label: strings.summaryMessage, value: data.message.trim() || strings.noMessage },
	];
}
```

### B2b. `blocks/contact/time.js` (new, in full)
Pure. Turns Dave's wall-clock choice into an instant with the server's offset table and words it for the visitor. The server converts again with PHP's own zone data; this file is only for what the visitor sees.

```js
/**
 * Times of the Contact scheduler (DPH-025). Pure: no clock, no global time zone unless `timeZone` is left out, in which
 * case the browser's own is used (that is the point: the visitor's local time).
 *
 * `offsets` is the server's table `[ { from: 'YYYY-MM-DD', minutes: 660 }, ... ]` of the site zone's offset from UTC,
 * in ascending `from` order (cptdp_contact_utc_offsets()). A day's slots all use that day's offset.
 */
export function offsetFor( offsets, date ) {
	let minutes = offsets[ 0 ].minutes;
	for ( const entry of offsets ) {
		if ( entry.from <= date ) {
			minutes = entry.minutes;
		}
	}
	return minutes;
}

/** The instant (ms since the epoch, UTC) of a wall-clock `time` on `date` in the site zone. */
export function slotInstant( date, time, offsets ) {
	const [ y, m, d ] = date.split( '-' ).map( Number );
	const [ hours, minutes ] = time.split( ':' ).map( Number );
	return Date.UTC( y, m - 1, d, hours, minutes ) - offsetFor( offsets, date ) * 60000;
}

/** "Tue 6 Oct · 10:30" for an instant, in `timeZone` (an IANA id; omitted = the browser's). 24-hour clock. */
export function formatInstant( ms, locale, timeZone ) {
	const parts = new Intl.DateTimeFormat( locale, {
		weekday: 'short',
		day: 'numeric',
		month: 'short',
		hour: '2-digit',
		minute: '2-digit',
		hourCycle: 'h23',
		timeZone,
	} ).formatToParts( new Date( ms ) );
	const get = ( type ) => ( parts.find( ( part ) => part.type === type ) || { value: '' } ).value;

	return `${ get( 'weekday' ) } ${ get( 'day' ) } ${ get( 'month' ) } · ${ get( 'hour' ) }:${ get( 'minute' ) }`;
}

/**
 * The chosen slot worded twice: `site` (Dave's wall clock) and `local` (the same moment in the visitor's zone, or ''
 * when that reads the same).
 */
export function describeSlot( { date, time, offsets, locale, timeZone } ) {
	const ms = slotInstant( date, time, offsets );
	const site = formatInstant( ms + offsetFor( offsets, date ) * 60000, locale, 'UTC' );
	const local = formatInstant( ms, locale, timeZone );

	return { ms, site, local: local === site ? '' : local };
}
```

### B3. `blocks/contact/tests/calendar.test.mjs` (new, in full)

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { toIso, parseIso, monthOf, shiftMonth, monthInRange, isSelectable, weekdayIndex, daysInMonth, monthGrid } from '../calendar.js';

const FIRST = '2026-10-06';
const LAST = '2027-10-05';

test( 'toIso pads and parseIso reads it back', () => {
	assert.equal( toIso( 2026, 3, 4 ), '2026-03-04' );
	assert.deepEqual( parseIso( '2026-03-04' ), { y: 2026, m: 3, d: 4 } );
	assert.equal( parseIso( '2026-3-4' ), null );
	assert.equal( parseIso( '' ), null );
	assert.equal( parseIso( undefined ), null );
} );

test( 'monthOf reads the month of a date', () => {
	assert.deepEqual( monthOf( '2026-10-06' ), { y: 2026, m: 10 } );
	assert.equal( monthOf( 'x' ), null );
} );

test( 'shiftMonth crosses year ends both ways', () => {
	assert.deepEqual( shiftMonth( { y: 2026, m: 12 }, 1 ), { y: 2027, m: 1 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 1 }, -1 ), { y: 2025, m: 12 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 10 }, 0 ), { y: 2026, m: 10 } );
	assert.deepEqual( shiftMonth( { y: 2026, m: 10 }, 12 ), { y: 2027, m: 10 } );
} );

test( 'monthInRange holds from the first month to the last month', () => {
	assert.equal( monthInRange( { y: 2026, m: 9 }, FIRST, LAST ), false );
	assert.equal( monthInRange( { y: 2026, m: 10 }, FIRST, LAST ), true );
	assert.equal( monthInRange( { y: 2027, m: 10 }, FIRST, LAST ), true );
	assert.equal( monthInRange( { y: 2027, m: 11 }, FIRST, LAST ), false );
} );

test( 'a day is selectable from the first to the last day, both included', () => {
	assert.equal( isSelectable( '2026-10-05', FIRST, LAST ), false );
	assert.equal( isSelectable( '2026-10-06', FIRST, LAST ), true );
	assert.equal( isSelectable( '2027-10-05', FIRST, LAST ), true );
	assert.equal( isSelectable( '2027-10-06', FIRST, LAST ), false );
} );

test( 'weekdays start on Monday and months have the right length', () => {
	assert.equal( weekdayIndex( 2026, 10, 5 ), 0 ); // Monday.
	assert.equal( weekdayIndex( 2026, 10, 6 ), 1 ); // Tuesday, the wireframe's example.
	assert.equal( weekdayIndex( 2026, 10, 11 ), 6 ); // Sunday.
	assert.equal( daysInMonth( 2026, 2 ), 28 );
	assert.equal( daysInMonth( 2028, 2 ), 29 );
	assert.equal( daysInMonth( 2026, 10 ), 31 );
} );

test( 'monthGrid pads to whole weeks and marks the bookable days', () => {
	const weeks = monthGrid( { y: 2026, m: 10 }, FIRST, LAST );

	assert.equal( weeks.length, 5 );
	weeks.forEach( ( week ) => assert.equal( week.length, 7 ) );
	assert.deepEqual( weeks[ 0 ].slice( 0, 3 ), [ null, null, null ] ); // 1 Oct 2026 is a Thursday.
	assert.equal( weeks[ 0 ][ 3 ].iso, '2026-10-01' );

	const days = weeks.flat().filter( Boolean );
	assert.equal( days.length, 31 );
	assert.equal( days.find( ( cell ) => cell.iso === '2026-10-05' ).selectable, false );
	assert.equal( days.find( ( cell ) => cell.iso === '2026-10-06' ).selectable, true );
	assert.equal( days.filter( ( cell ) => cell.selectable ).length, 26 );
} );

test( 'a month fully in the past has no selectable day', () => {
	const days = monthGrid( { y: 2026, m: 9 }, FIRST, LAST ).flat().filter( Boolean );

	assert.equal( days.filter( ( cell ) => cell.selectable ).length, 0 );
} );
```

### B4. `blocks/contact/tests/steps.test.mjs` (new, in full)

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { validateStep, crumbStates, nextStep, prevStep, summaryRows, LAST_STEP } from '../steps.js';

const strings = {
	slotRequired: 'Pick a time.',
	nameRequired: 'Name please.',
	emailRequired: 'Email please.',
	emailInvalid: 'Bad email.',
	needRequired: 'Choose one.',
	summaryTime: 'Time',
	summaryName: 'Name',
	summaryEmail: 'Email',
	summaryHelp: 'Help with',
	summaryMessage: 'Message',
	noMessage: 'No message',
};

const data = { date: '2026-10-06', time: '10:30', name: ' Jane ', email: ' jane@example.com ', services: [ '12', 'other' ], message: ' Hi ' };

test( 'step 1 needs a day and a time', () => {
	assert.deepEqual( validateStep( 1, data, strings ), {} );
	assert.deepEqual( validateStep( 1, { ...data, time: '' }, strings ), { slot: 'Pick a time.' } );
	assert.deepEqual( validateStep( 1, { ...data, date: '' }, strings ), { slot: 'Pick a time.' } );
} );

test( 'step 2 needs a name and a plausible email', () => {
	assert.deepEqual( validateStep( 2, data, strings ), {} );
	assert.deepEqual( validateStep( 2, { ...data, name: '   ', email: '' }, strings ), { name: 'Name please.', email: 'Email please.' } );
	assert.deepEqual( validateStep( 2, { ...data, email: 'jane@' }, strings ), { email: 'Bad email.' } );
	assert.deepEqual( validateStep( 2, { ...data, email: 'a b@c.d' }, strings ), { email: 'Bad email.' } );
} );

test( 'step 3 needs at least one service and the message is optional', () => {
	assert.deepEqual( validateStep( 3, data, strings ), {} );
	assert.deepEqual( validateStep( 3, { ...data, services: [] }, strings ), { services: 'Choose one.' } );
	assert.deepEqual( validateStep( 3, { ...data, message: '' }, strings ), {} );
} );

test( 'step 4 has nothing to validate', () => {
	assert.deepEqual( validateStep( 4, { ...data, services: [] }, strings ), {} );
} );

test( 'crumbs: done before, current, todo after; all done once sent', () => {
	assert.deepEqual( crumbStates( 1, false ), [ 'current', 'todo', 'todo', 'todo' ] );
	assert.deepEqual( crumbStates( 3, false ), [ 'done', 'done', 'current', 'todo' ] );
	assert.deepEqual( crumbStates( 4, true ), [ 'done', 'done', 'done', 'done' ] );
} );

test( 'next and previous step stay inside 1 to 4', () => {
	assert.equal( LAST_STEP, 4 );
	assert.equal( nextStep( 1, 0 ), 2 );
	assert.equal( nextStep( 4, 0 ), 4 );
	assert.equal( prevStep( 3 ), 2 );
	assert.equal( prevStep( 1 ), 1 );
} );

test( 'while editing from the summary Next returns straight to step 4', () => {
	assert.equal( nextStep( 2, 4 ), 4 );
	assert.equal( nextStep( 1, 4 ), 4 );
	assert.equal( nextStep( 4, 4 ), 4 );
} );

test( 'summaryRows lists five rows, trims, orders the help by the service list and names the edit step', () => {
	const services = [ { id: '12', title: 'Social Media Marketing' }, { id: '13', title: 'Digital Marketing' }, { id: 'other', title: 'Something else' } ];
	const rows = summaryRows( { ...data, services: [ 'other', '12' ] }, services, strings, 'Tue 6 Oct · 10:30 Sydney time' );

	assert.deepEqual( rows.map( ( row ) => row.key ), [ 'time', 'name', 'email', 'help', 'message' ] );
	assert.deepEqual( rows.map( ( row ) => row.step ), [ 1, 2, 2, 3, 3 ] );
	assert.equal( rows[ 0 ].value, 'Tue 6 Oct · 10:30 Sydney time' );
	assert.equal( rows[ 1 ].value, 'Jane' );
	assert.equal( rows[ 2 ].value, 'jane@example.com' );
	assert.equal( rows[ 3 ].value, 'Social Media Marketing, Something else' );
	assert.equal( rows[ 4 ].value, 'Hi' );
} );

test( 'an empty message shows the "no message" wording', () => {
	const rows = summaryRows( { ...data, message: '  ' }, [], strings, 'Tue 6 Oct · 10:30 Sydney time' );

	assert.equal( rows[ 4 ].value, 'No message' );
} );
```

### B4b. `blocks/contact/tests/time.test.mjs` (new, in full)

```js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { offsetFor, slotInstant, formatInstant, describeSlot } from '../time.js';

// Sydney: UTC+10 until 4 Oct 2026 (daylight saving starts), UTC+11 until 4 Apr 2027.
const OFFSETS = [
	{ from: '2026-10-01', minutes: 600 },
	{ from: '2026-10-04', minutes: 660 },
	{ from: '2027-04-04', minutes: 600 },
];

test( 'offsetFor takes the last entry that has started, and the first before any', () => {
	assert.equal( offsetFor( OFFSETS, '2026-09-30' ), 600 );
	assert.equal( offsetFor( OFFSETS, '2026-10-03' ), 600 );
	assert.equal( offsetFor( OFFSETS, '2026-10-04' ), 660 );
	assert.equal( offsetFor( OFFSETS, '2027-04-03' ), 660 );
	assert.equal( offsetFor( OFFSETS, '2027-04-04' ), 600 );
} );

test( 'slotInstant is the wall clock minus that day offset, across the daylight saving change', () => {
	const iso = ( date, time ) => new Date( slotInstant( date, time, OFFSETS ) ).toISOString();

	assert.equal( iso( '2026-10-03', '10:30' ), '2026-10-03T00:30:00.000Z' ); // UTC+10.
	assert.equal( iso( '2026-10-04', '10:30' ), '2026-10-03T23:30:00.000Z' ); // UTC+11 from the change day.
	assert.equal( iso( '2026-10-06', '10:30' ), '2026-10-05T23:30:00.000Z' ); // The wireframe's example.
	assert.equal( iso( '2027-04-04', '10:30' ), '2027-04-04T00:30:00.000Z' ); // Back to UTC+10.
} );

test( 'formatInstant words an instant in the zone it is given', () => {
	const ms = Date.UTC( 2026, 9, 5, 23, 30 );

	assert.equal( formatInstant( ms, 'en-AU', 'Australia/Sydney' ), 'Tue 6 Oct · 10:30' );
	assert.equal( formatInstant( ms, 'en-AU', 'America/Los_Angeles' ), 'Mon 5 Oct · 16:30' );
	assert.equal( formatInstant( ms, 'en-AU', 'UTC' ), 'Mon 5 Oct · 23:30' );
} );

test( 'midnight is 00, not 24', () => {
	assert.equal( formatInstant( Date.UTC( 2026, 0, 1, 0, 5 ), 'en-AU', 'UTC' ), 'Thu 1 Jan · 00:05' );
} );

test( 'a visitor in another zone sees the same moment on their own clock', () => {
	const slot = describeSlot( { date: '2026-10-06', time: '10:30', offsets: OFFSETS, locale: 'en-AU', timeZone: 'America/Los_Angeles' } );

	assert.equal( slot.site, 'Tue 6 Oct · 10:30' );
	assert.equal( slot.local, 'Mon 5 Oct · 16:30' );
	assert.equal( new Date( slot.ms ).toISOString(), '2026-10-05T23:30:00.000Z' );
} );

test( 'a visitor in the site zone gets no second line', () => {
	const slot = describeSlot( { date: '2026-10-06', time: '10:30', offsets: OFFSETS, locale: 'en-AU', timeZone: 'Australia/Sydney' } );

	assert.equal( slot.site, 'Tue 6 Oct · 10:30' );
	assert.equal( slot.local, '' );
} );
```

### Gate B
```bash
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/contact 'node --test tests/*.test.mjs'
```
Expected: 22 tests pass (8 calendar, 8 steps, 6 time). If `formatInstant` returns `Tue, 6 Oct` or `Sept`-style differences for October/December, stop and report the exact output (ICU version); do not change the test dates.

---

## Phase C. Theme: the block

### C1. `blocks/contact/block.json` (new, in full)

```json
{
	"$schema": "https://schemas.wp.org/trunk/block.json",
	"apiVersion": 3,
	"name": "devportfolio/contact",
	"title": "Contact scheduler",
	"category": "widgets",
	"description": "The Home Contact section: a 4-step card (availability, about you, your need, confirm) and, on phones, a full-screen scheduler.",
	"textdomain": "devportfolio",
	"supports": { "html": false },
	"render": "file:./render.php",
	"style": "file:./style.css",
	"viewScriptModule": "file:./view.js"
}
```

### C2. `functions.php`: register the block and add the helpers
(a) Replace
```php
		register_block_type( __DIR__ . '/blocks/software-tools' );
```
with
```php
		register_block_type( __DIR__ . '/blocks/software-tools' );
		register_block_type( __DIR__ . '/blocks/contact' );
```
(b) **Append** to the end of `functions.php`:

```php

if ( ! function_exists( 'devportfolio_contact_strings' ) ) :
	/**
	 * Every word of the Contact scheduler (DPH-025 to DPH-028). `{name}`, `{n}`, `{total}`, `{date}` and `{zone}` are
	 * replaced by view.js. A test checks that every `strings.<key>` used by the scripts and every `$s['<key>']` used by
	 * render.php is a key here.
	 *
	 * @return array<string,string>
	 */
	function devportfolio_contact_strings() {
		return array(
			'heading'          => __( 'Book a call', 'devportfolio' ),
			'open'             => __( 'Check availability', 'devportfolio' ),
			'progress'         => __( 'Progress', 'devportfolio' ),
			'stepOf'           => __( 'step {n} of {total}', 'devportfolio' ),
			'crumbAvailability' => __( 'Availability', 'devportfolio' ),
			'crumbAbout'       => __( 'About you', 'devportfolio' ),
			'crumbNeed'        => __( 'Your need', 'devportfolio' ),
			'crumbConfirm'     => __( 'Confirm', 'devportfolio' ),
			'title1'           => __( 'Pick a day and time', 'devportfolio' ),
			'title2'           => __( 'Introduce yourself', 'devportfolio' ),
			'title3'           => __( 'What do you need help with?', 'devportfolio' ),
			'title4'           => __( 'Confirm the details', 'devportfolio' ),
			'monthPrev'        => __( 'Previous month', 'devportfolio' ),
			'monthNext'        => __( 'Next month', 'devportfolio' ),
			'legendAvailable'  => __( 'Available', 'devportfolio' ),
			'legendSelected'   => __( 'Selected', 'devportfolio' ),
			'timesHint'        => __( 'Pick a day to see the times.', 'devportfolio' ),
			'timesTitle'       => __( 'Available times', 'devportfolio' ),
			'timezone'         => __( 'Times are shown in {zone} time.', 'devportfolio' ),
			'slotSite'         => __( '{when} {zone} time', 'devportfolio' ),
			'slotLocal'        => __( '{when} in your time zone ({zone})', 'devportfolio' ),
			'nameLabel'        => __( 'Name', 'devportfolio' ),
			'emailLabel'       => __( 'Email', 'devportfolio' ),
			'messageLabel'     => __( 'Tell me more', 'devportfolio' ),
			'messageHint'      => __( 'Optional', 'devportfolio' ),
			'honeypotLabel'    => __( 'Leave this field empty', 'devportfolio' ),
			'back'             => __( 'Back', 'devportfolio' ),
			'next'             => __( 'Next', 'devportfolio' ),
			'edit'             => __( 'Edit', 'devportfolio' ),
			'change'           => __( 'Change', 'devportfolio' ),
			'confirm'          => __( 'Confirm my interest ↗', 'devportfolio' ),
			'sending'          => __( 'Sending…', 'devportfolio' ),
			'summaryTime'      => __( 'Time', 'devportfolio' ),
			'summaryName'      => __( 'Name', 'devportfolio' ),
			'summaryEmail'     => __( 'Email', 'devportfolio' ),
			'summaryHelp'      => __( 'Help with', 'devportfolio' ),
			'summaryMessage'   => __( 'Message', 'devportfolio' ),
			'noMessage'        => __( 'No message', 'devportfolio' ),
			'slotRequired'     => __( 'Choose a day and a time to continue.', 'devportfolio' ),
			'nameRequired'     => __( 'Please enter your name.', 'devportfolio' ),
			'emailRequired'    => __( 'Please enter your email address.', 'devportfolio' ),
			'emailInvalid'     => __( 'That email address does not look right.', 'devportfolio' ),
			'needRequired'     => __( 'Choose at least one, so I know how to help.', 'devportfolio' ),
			'slotGone'         => __( 'That time is no longer available. Please pick another.', 'devportfolio' ),
			'sendError'        => __( 'That did not send. Please try again.', 'devportfolio' ),
			'tooMany'          => __( 'Too many tries. Please wait a while and try again.', 'devportfolio' ),
			'thanks'           => __( 'Thanks, {name}. Interest received.', 'devportfolio' ),
			'thanksTime'       => __( 'Your time: {date}', 'devportfolio' ),
			'thanksNote'       => __( 'I\'ll confirm by email.', 'devportfolio' ),
			'modalTitle'       => __( 'Check my availability', 'devportfolio' ),
			'close'            => __( 'Close', 'devportfolio' ),
			'confirmSlot'      => __( 'Confirm date & time', 'devportfolio' ),
			'noscript'         => __( 'The booking form needs JavaScript.', 'devportfolio' ),
			'noscriptEmail'    => __( 'You can email me instead:', 'devportfolio' ),
		);
	}
endif;

if ( ! function_exists( 'devportfolio_contact_config' ) ) :
	/**
	 * What the Contact scheduler needs, or an empty array when the plugin is not active (the block then renders nothing).
	 *
	 * @return array<string,mixed>
	 */
	function devportfolio_contact_config() {
		if ( ! function_exists( 'cptdp_contact_config' ) ) {
			return array();
		}

		return array_merge( cptdp_contact_config(), array( 'strings' => devportfolio_contact_strings() ) );
	}
endif;
```
Keep the array arrows aligned with `phpcbf`-style spacing if a linter is added later; there is none today.

### C3. `blocks/contact/render.php` (new, in full)

```php
<?php
/**
 * Server render of the Home Contact scheduler (DPH-025 to DPH-028).
 *
 * Markup only: the four step forms, the phone dialog and a JSON payload. The crumbs, the calendar, the times and the
 * summary are built by view.js. Without JavaScript the card and the button stay hidden and the noscript line shows
 * the Contact email. Nothing is rendered when the plugin is not active.
 *
 * @package devportfolio
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

$devportfolio_config = devportfolio_contact_config();
if ( empty( $devportfolio_config ) ) {
	return;
}

$s     = $devportfolio_config['strings'];
$email = function_exists( 'cptdp_get_contact' ) ? (string) cptdp_get_contact( 'email' ) : '';

$devportfolio_json = wp_json_encode(
	$devportfolio_config,
	JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

$devportfolio_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'contact home-section',
		'id'    => 'contact',
	)
);
?>
<section <?php echo $devportfolio_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by core. ?> aria-labelledby="contact-heading" data-contact data-step="1">
	<h2 id="contact-heading" class="contact__heading"><?php echo esc_html( $s['heading'] ); ?></h2>

	<button type="button" class="contact__open" data-contact-open><?php echo esc_html( $s['open'] ); ?></button>

	<div class="contact__card" data-contact-card>
		<ol class="contact__crumbs" data-contact-crumbs aria-label="<?php echo esc_attr( $s['progress'] ); ?>"></ol>

		<p class="contact__slot">
			<span data-contact-slot-text></span>
			<button type="button" class="contact__link" data-contact-change><?php echo esc_html( $s['change'] ); ?></button>
		</p>

		<form class="contact__panel" data-contact-panel="1" novalidate>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title1'] ); ?></h3>
			<div class="contact__body" data-contact-picker></div>
			<footer class="contact__footer">
				<button type="submit" class="contact__btn contact__btn--fill" data-contact-next disabled><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="2" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title2'] ); ?></h3>
			<div class="contact__body">
				<div class="contact__field">
					<label for="contact-name"><?php echo esc_html( $s['nameLabel'] ); ?></label>
					<input id="contact-name" type="text" name="name" autocomplete="name" maxlength="100" aria-required="true" aria-describedby="contact-name-error" data-contact-field="name">
					<p class="contact__error" id="contact-name-error" role="alert" data-contact-error="name"></p>
				</div>
				<div class="contact__field">
					<label for="contact-email"><?php echo esc_html( $s['emailLabel'] ); ?></label>
					<input id="contact-email" type="email" name="email" autocomplete="email" maxlength="254" aria-required="true" aria-describedby="contact-email-error" data-contact-field="email">
					<p class="contact__error" id="contact-email-error" role="alert" data-contact-error="email"></p>
				</div>
				<div class="contact__hp" aria-hidden="true">
					<label for="contact-website"><?php echo esc_html( $s['honeypotLabel'] ); ?></label>
					<input id="contact-website" type="text" name="website" tabindex="-1" autocomplete="off" data-contact-honeypot>
				</div>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill"><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="3" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title3'] ); ?></h3>
			<div class="contact__body">
				<fieldset class="contact__chips" aria-describedby="contact-services-error" data-contact-field="services">
					<legend class="contact__sr"><?php echo esc_html( $s['title3'] ); ?></legend>
					<?php foreach ( $devportfolio_config['services'] as $devportfolio_service ) : ?>
						<label class="contact__chip">
							<input type="checkbox" name="services" value="<?php echo esc_attr( $devportfolio_service['id'] ); ?>" data-contact-service>
							<span><?php echo esc_html( $devportfolio_service['title'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="contact__error" id="contact-services-error" role="alert" data-contact-error="services"></p>
				<div class="contact__field">
					<label for="contact-message"><?php echo esc_html( $s['messageLabel'] ); ?> <span class="contact__optional">(<?php echo esc_html( $s['messageHint'] ); ?>)</span></label>
					<textarea id="contact-message" name="message" rows="5" maxlength="2000" data-contact-field="message"></textarea>
				</div>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill"><?php echo esc_html( $s['next'] ); ?></button>
			</footer>
		</form>

		<form class="contact__panel" data-contact-panel="4" novalidate hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-title><?php echo esc_html( $s['title4'] ); ?></h3>
			<div class="contact__body">
				<dl class="contact__summary" data-contact-summary></dl>
				<p class="contact__error" role="alert" data-contact-form-error></p>
			</div>
			<footer class="contact__footer">
				<button type="button" class="contact__btn" data-contact-back><?php echo esc_html( $s['back'] ); ?></button>
				<button type="submit" class="contact__btn contact__btn--fill" data-contact-send><?php echo esc_html( $s['confirm'] ); ?></button>
			</footer>
		</form>

		<div class="contact__thanks" data-contact-thanks role="status" hidden>
			<h3 class="contact__title" tabindex="-1" data-contact-thanks-title></h3>
			<p data-contact-thanks-time></p>
			<p><?php echo esc_html( $s['thanksNote'] ); ?></p>
		</div>
	</div>

	<noscript>
		<p class="contact__noscript">
			<?php echo esc_html( $s['noscript'] ); ?>
			<?php if ( is_email( $email ) ) : ?>
				<?php echo esc_html( $s['noscriptEmail'] ); ?>
				<a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a>
			<?php endif; ?>
		</p>
	</noscript>

	<dialog class="contact-scheduler" data-contact-dialog aria-labelledby="contact-scheduler-title">
		<div class="contact-scheduler__header">
			<button type="button" class="contact-scheduler__close" data-contact-close aria-label="<?php echo esc_attr( $s['close'] ); ?>">
				<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
			</button>
			<h2 id="contact-scheduler-title" class="contact-scheduler__title"><?php echo esc_html( $s['modalTitle'] ); ?></h2>
		</div>
		<div class="contact-scheduler__body" data-contact-modal-picker></div>
		<div class="contact-scheduler__footer">
			<button type="button" class="contact__btn contact__btn--fill contact__btn--block" data-contact-confirm-slot disabled><?php echo esc_html( $s['confirmSlot'] ); ?></button>
		</div>
	</dialog>

	<script type="application/json" class="contact__data"><?php echo $devportfolio_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with the HEX flags, safe inside a script element. ?></script>
</section>
```

### C4. `blocks/contact/picker.js` (new, in full)

```js
/**
 * The calendar and the times of the Contact scheduler (DPH-025), used twice: in the desktop card (its model is the
 * card's state) and in the phone modal (its model is a draft the modal commits on "Confirm date & time").
 *
 * `model` is `{ date, time, month }`; the picker writes to it and calls `onChange()`. Rebuilding the DOM on every
 * change is cheap here (about 40 buttons), and the focused button is found again by its `data-key`.
 */
import { el } from '../shared/dom.js';
import { monthGrid, monthOf, shiftMonth, monthInRange, parseIso } from './calendar.js';

const text = ( node, value ) => {
	node.textContent = value;
	return node;
};

export function createPicker( { root, model, config, strings, locale, onChange, localNote } ) {
	const utc = ( y, m, d ) => new Date( Date.UTC( y, m - 1, d ) );
	const monthFormat = new Intl.DateTimeFormat( locale, { month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const dayFormat = new Intl.DateTimeFormat( locale, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const weekdayFormat = new Intl.DateTimeFormat( locale, { weekday: 'short', timeZone: 'UTC' } );
	let notice = '';

	function button( className, key, label, content ) {
		const node = el( 'button', className, { type: 'button', 'data-key': key } );
		if ( label ) {
			node.setAttribute( 'aria-label', label );
		}
		node.textContent = content;
		return node;
	}

	function calendar() {
		const { y, m } = model.month;
		const box = el( 'div', 'contact-picker__cal' );

		const bar = el( 'div', 'contact-picker__bar' );
		const prev = button( 'contact-picker__nav', 'prev', strings.monthPrev, '‹' );
		const next = button( 'contact-picker__nav', 'next', strings.monthNext, '›' );
		prev.disabled = ! monthInRange( shiftMonth( model.month, -1 ), config.first, config.last );
		next.disabled = ! monthInRange( shiftMonth( model.month, 1 ), config.first, config.last );
		prev.addEventListener( 'click', () => {
			model.month = shiftMonth( model.month, -1 );
			render();
		} );
		next.addEventListener( 'click', () => {
			model.month = shiftMonth( model.month, 1 );
			render();
		} );
		const title = text( el( 'p', 'contact-picker__month', { 'aria-live': 'polite' } ), monthFormat.format( utc( y, m, 1 ) ) );
		bar.append( prev, title, next );

		const heads = el( 'div', 'contact-picker__weekdays', { 'aria-hidden': 'true' } );
		for ( let i = 0; i < 7; i++ ) {
			heads.append( text( el( 'span' ), weekdayFormat.format( utc( 2026, 1, 5 + i ) ) ) ); // 5 Jan 2026 is a Monday.
		}

		const grid = el( 'div', 'contact-picker__grid', { role: 'group', 'aria-label': monthFormat.format( utc( y, m, 1 ) ) } );
		monthGrid( model.month, config.first, config.last ).flat().forEach( ( cell ) => {
			if ( ! cell ) {
				grid.append( el( 'span', 'contact-picker__pad' ) );
				return;
			}
			const parts = parseIso( cell.iso );
			const day = button( 'contact-picker__day', `d:${ cell.iso }`, dayFormat.format( utc( parts.y, parts.m, parts.d ) ), String( cell.day ) );
			day.disabled = ! cell.selectable;
			day.setAttribute( 'aria-pressed', cell.iso === model.date ? 'true' : 'false' );
			day.addEventListener( 'click', () => {
				model.date = cell.iso;
				model.time = '';
				notice = '';
				render();
				onChange();
			} );
			grid.append( day );
		} );

		const legend = el( 'ul', 'contact-picker__legend' );
		legend.append(
			text( el( 'li', 'contact-picker__legend-free' ), strings.legendAvailable ),
			text( el( 'li', 'contact-picker__legend-selected' ), strings.legendSelected )
		);

		box.append( bar, heads, grid, legend );
		return box;
	}

	function times() {
		const box = el( 'div', 'contact-picker__times' );

		if ( ! model.date ) {
			box.append( text( el( 'p', 'contact-picker__hint' ), strings.timesHint ) );
		} else {
			box.append( text( el( 'p', 'contact-picker__times-title' ), strings.timesTitle ) );
			const list = el( 'div', 'contact-picker__slots', { role: 'group', 'aria-label': strings.timesTitle } );
			config.slots.forEach( ( slot ) => {
				const pill = button( 'contact-picker__time', `t:${ slot }`, '', slot );
				pill.setAttribute( 'aria-pressed', slot === model.time ? 'true' : 'false' );
				pill.addEventListener( 'click', () => {
					model.time = slot;
					render();
					onChange();
				} );
				list.append( pill );
			} );
			box.append( list );
		}

		box.append( text( el( 'p', 'contact-picker__zone' ), strings.timezone.replace( '{zone}', config.timezone ) ) );

		// The chosen moment on the visitor's own clock, when it differs from Dave's.
		const local = model.date && model.time && localNote ? localNote( model.date, model.time ) : '';
		if ( local ) {
			box.append( text( el( 'p', 'contact-picker__local', { role: 'status' } ), local ) );
		}
		if ( notice ) {
			box.append( text( el( 'p', 'contact__error', { role: 'alert' } ), notice ) );
		}
		return box;
	}

	function render() {
		if ( ! model.month ) {
			model.month = monthOf( model.date || config.first );
		}
		const active = root.contains( document.activeElement ) ? document.activeElement.dataset.key : '';
		root.replaceChildren( el( 'div', 'contact-picker' ) );
		root.firstChild.append( calendar(), times() );
		if ( active ) {
			const again = root.querySelector( `[data-key="${ active }"]` );
			if ( again && ! again.disabled ) {
				again.focus();
			}
		}
	}

	return {
		render,
		/** A line under the times ("That time is no longer available"); cleared by the next pick. */
		setNotice( message ) {
			notice = message;
		},
	};
}
```

### C5. `blocks/contact/view.js` (new, in full)

```js
/**
 * Home Contact scheduler (DPH-025 to DPH-028).
 *
 * State lives in memory only: nothing the visitor types is stored in the browser. The card is the same on every
 * screen; at 800px and below it is hidden until a time is confirmed in the full-screen modal (CSS, same query as
 * MOBILE_QUERY, a test compares them). On a phone "step 1" is that modal, so goTo( 1 ) opens it.
 */
import { el } from '../shared/dom.js';
import { createSheetHistory } from '../shared/sheet-back.js';
import { createPicker } from './picker.js';
import { validateStep, crumbStates, nextStep, prevStep, summaryRows, LAST_STEP } from './steps.js';
import { describeSlot } from './time.js';

const MOBILE_QUERY = '(max-width: 800px)';

const text = ( node, value ) => {
	node.textContent = value;
	return node;
};

function init( root ) {
	const payload = root.querySelector( '.contact__data' );
	if ( ! payload ) {
		return;
	}

	const config = JSON.parse( payload.textContent );
	const strings = config.strings;
	const locale = document.documentElement.lang || 'en';
	const mobile = window.matchMedia( MOBILE_QUERY );
	const visitorZone = ( new Intl.DateTimeFormat().resolvedOptions().timeZone ) || '';

	/**
	 * The chosen time for this visitor: Dave's wall clock with his city, and, when the visitor's clock differs, the same
	 * moment on their own. Display only: the server turns the day and time into UTC itself.
	 */
	function describe( date, time ) {
		const slot = describeSlot( { date, time, offsets: config.offsets, locale } );
		const site = strings.slotSite.replace( '{when}', slot.site ).replace( '{zone}', config.timezone );
		const local = slot.local ? strings.slotLocal.replace( '{when}', slot.local ).replace( '{zone}', visitorZone ) : '';
		return { site, local };
	}

	function whenText( date, time ) {
		const parts = describe( date, time );
		return parts.local ? `${ parts.site }\n${ parts.local }` : parts.site;
	}

	const card = root.querySelector( '[data-contact-card]' );
	const panels = [ ...root.querySelectorAll( '[data-contact-panel]' ) ];
	const crumbs = root.querySelector( '[data-contact-crumbs]' );
	const slotText = root.querySelector( '[data-contact-slot-text]' );
	const summary = root.querySelector( '[data-contact-summary]' );
	const formError = root.querySelector( '[data-contact-form-error]' );
	const sendButton = root.querySelector( '[data-contact-send]' );
	const nextOne = panels[ 0 ].querySelector( '[data-contact-next]' );
	const thanks = root.querySelector( '[data-contact-thanks]' );
	const dialog = root.querySelector( '[data-contact-dialog]' );
	const confirmSlot = root.querySelector( '[data-contact-confirm-slot]' );
	const nameInput = root.querySelector( '[data-contact-field="name"]' );
	const emailInput = root.querySelector( '[data-contact-field="email"]' );
	const messageInput = root.querySelector( '[data-contact-field="message"]' );
	const honeypot = root.querySelector( '[data-contact-honeypot]' );
	const serviceBoxes = [ ...root.querySelectorAll( '[data-contact-service]' ) ];

	const state = { step: 1, returnTo: 0, date: '', time: '', month: null, name: '', email: '', services: [], message: '', sent: false, sending: false };
	const draft = { date: '', time: '', month: null };

	// ---- steps ---------------------------------------------------------------------------------------------

	function renderCrumbs() {
		const labels = [ strings.crumbAvailability, strings.crumbAbout, strings.crumbNeed, strings.crumbConfirm ];
		const states = crumbStates( state.step, state.sent );

		crumbs.replaceChildren(
			...states.map( ( status, index ) => {
				const n = index + 1;
				const tappable = status === 'done' && ! state.sent;
				const item = el( 'li', `contact__crumb is-${ status }` );
				const inner = tappable ? el( 'button', 'contact__crumb-btn', { type: 'button' } ) : el( 'span', 'contact__crumb-btn' );
				const label = text( el( 'span', 'contact__crumb-label' ), labels[ index ] );

				if ( tappable ) {
					inner.addEventListener( 'click', () => {
						state.returnTo = 0;
						goTo( n );
					} );
				}
				if ( status === 'current' ) {
					inner.setAttribute( 'aria-current', 'step' );
					label.append( text( el( 'span', 'contact__sr' ), ` (${ strings.stepOf.replace( '{n}', n ).replace( '{total}', LAST_STEP ) })` ) );
				}
				inner.append( text( el( 'span', 'contact__crumb-num' ), String( n ) ), label );
				item.append( inner );
				return item;
			} )
		);
	}

	function renderSummary() {
		summary.replaceChildren(
			...summaryRows( state, config.services, strings, whenText( state.date, state.time ) ).map( ( row ) => {
				const line = el( 'div', 'contact__summary-row' );
				const value = text( el( 'dd' ), row.value );
				const edit = text( el( 'button', 'contact__link', { type: 'button', 'data-edit': String( row.step ), 'aria-label': `${ strings.edit } ${ row.label }` } ), strings.edit );
				value.append( edit );
				line.append( text( el( 'dt' ), row.label ), value );
				return line;
			} )
		);
	}

	function show( n, focus ) {
		state.step = n;
		if ( n === LAST_STEP ) {
			state.returnTo = 0;
			formError.textContent = '';
			renderSummary();
		}
		panels.forEach( ( panel, index ) => {
			panel.hidden = index + 1 !== n;
		} );
		root.dataset.step = String( n );
		renderCrumbs();
		if ( focus ) {
			panels[ n - 1 ].querySelector( '[data-contact-title]' ).focus();
		}
	}

	/** Step 1 on a phone is the modal. */
	function goTo( n ) {
		if ( state.sent ) {
			return;
		}
		if ( n === 1 && mobile.matches ) {
			openModal();
			return;
		}
		show( n, true );
	}

	// ---- errors --------------------------------------------------------------------------------------------

	function setError( field, message ) {
		const line = root.querySelector( `[data-contact-error="${ field }"]` );
		if ( line ) {
			line.textContent = message || '';
		}
		const control = root.querySelector( `[data-contact-field="${ field }"]` );
		if ( control && control.tagName !== 'FIELDSET' ) {
			if ( message ) {
				control.setAttribute( 'aria-invalid', 'true' );
			} else {
				control.removeAttribute( 'aria-invalid' );
			}
		}
	}

	function focusField( field ) {
		const control = root.querySelector( `[data-contact-field="${ field }"]` );
		const target = control && control.tagName === 'FIELDSET' ? control.querySelector( 'input' ) : control;
		if ( target ) {
			target.focus();
		}
	}

	function advance() {
		const errors = validateStep( state.step, state, strings );
		[ 'name', 'email', 'services' ].forEach( ( field ) => setError( field, errors[ field ] ) );

		const first = Object.keys( errors )[ 0 ];
		if ( first ) {
			focusField( first );
			return;
		}
		if ( state.step === LAST_STEP ) {
			send();
			return;
		}
		goTo( nextStep( state.step, state.returnTo ) );
	}

	// ---- sending -------------------------------------------------------------------------------------------

	function setBusy( busy ) {
		state.sending = busy;
		sendButton.disabled = busy;
		sendButton.textContent = busy ? strings.sending : strings.confirm;
	}

	function finish() {
		state.sent = true;
		root.classList.add( 'is-sent' );
		panels.forEach( ( panel ) => {
			panel.hidden = true;
		} );
		renderCrumbs();
		const title = thanks.querySelector( '[data-contact-thanks-title]' );
		text( title, strings.thanks.replace( '{name}', state.name.trim() ) );
		text( thanks.querySelector( '[data-contact-thanks-time]' ), strings.thanksTime.replace( '{date}', whenText( state.date, state.time ) ) );
		thanks.hidden = false;
		title.focus();
	}

	function failed( status, body ) {
		const field = body && body.data && body.data.field;

		if ( status === 422 && ( field === 'date' || field === 'time' ) ) {
			state.date = '';
			state.time = '';
			sync();
			cardPicker.setNotice( strings.slotGone );
			modalPicker.setNotice( strings.slotGone );
			cardPicker.render();
			goTo( 1 );
			return;
		}
		if ( status === 422 && ( field === 'name' || field === 'email' || field === 'services' ) ) {
			goTo( field === 'services' ? 3 : 2 );
			setError( field, body.message );
			focusField( field );
			return;
		}
		formError.textContent = status === 429 ? strings.tooMany : strings.sendError;
	}

	async function send() {
		if ( state.sending ) {
			return;
		}
		setBusy( true );
		formError.textContent = '';
		try {
			const response = await fetch( config.endpoint, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( {
					date: state.date,
					time: state.time,
					timezone: visitorZone,
					name: state.name.trim(),
					email: state.email.trim(),
					services: state.services,
					message: state.message.trim(),
					website: honeypot.value,
				} ),
			} );
			if ( response.ok ) {
				finish();
			} else {
				failed( response.status, await response.json().catch( () => ( {} ) ) );
			}
		} catch ( error ) {
			formError.textContent = strings.sendError;
		} finally {
			setBusy( false );
		}
	}

	// ---- the chosen time -----------------------------------------------------------------------------------

	/** Everything that follows the chosen day and time: Next on step 1, the phone's time line, the layout class. */
	function sync() {
		const has = Boolean( state.date && state.time );
		root.classList.toggle( 'has-slot', has );
		nextOne.disabled = ! has;
		slotText.textContent = has ? whenText( state.date, state.time ) : '';
	}

	const cardPicker = createPicker( {
		root: root.querySelector( '[data-contact-picker]' ),
		model: state,
		config,
		strings,
		locale,
		onChange: sync,
		localNote: ( date, time ) => describe( date, time ).local,
	} );

	// ---- the phone modal -----------------------------------------------------------------------------------

	// Created now, while the module runs: the history listener must be registered before the router's.
	const history = createSheetHistory( { isOpen: () => dialog.open, onBack: () => dialog.close() } );

	const modalPicker = createPicker( {
		root: root.querySelector( '[data-contact-modal-picker]' ),
		model: draft,
		config,
		strings,
		locale,
		onChange: () => {
			confirmSlot.disabled = ! ( draft.date && draft.time );
		},
		localNote: ( date, time ) => describe( date, time ).local,
	} );

	function openModal() {
		draft.date = state.date;
		draft.time = state.time;
		draft.month = null;
		confirmSlot.disabled = ! ( draft.date && draft.time );
		modalPicker.render();
		dialog.showModal();
		history.push();
	}

	function commitSlot() {
		state.date = draft.date;
		state.time = draft.time;
		state.month = null;
		sync();
		cardPicker.render();
		dialog.close();
		if ( state.step === 1 ) {
			show( state.returnTo || 2, true );
		} else if ( state.step === LAST_STEP ) {
			renderSummary();
		}
	}

	dialog.addEventListener( 'close', () => history.pop() );
	root.querySelector( '[data-contact-close]' ).addEventListener( 'click', () => dialog.close() );
	confirmSlot.addEventListener( 'click', commitSlot );
	root.querySelector( '[data-contact-open]' ).addEventListener( 'click', openModal );
	root.querySelector( '[data-contact-change]' ).addEventListener( 'click', openModal );

	mobile.addEventListener( 'change', ( event ) => {
		if ( ! event.matches && dialog.open ) {
			dialog.close();
		}
		if ( event.matches && state.step === 1 && state.date && state.time && ! state.sent ) {
			show( 2, false );
		}
	} );

	// ---- wiring --------------------------------------------------------------------------------------------

	panels.forEach( ( panel ) => {
		panel.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			advance();
		} );
		panel.querySelectorAll( '[data-contact-back]' ).forEach( ( back ) => {
			back.addEventListener( 'click', () => {
				state.returnTo = 0;
				goTo( prevStep( state.step ) );
			} );
		} );
	} );

	summary.addEventListener( 'click', ( event ) => {
		const edit = event.target.closest( '[data-edit]' );
		if ( edit ) {
			state.returnTo = LAST_STEP;
			goTo( Number( edit.dataset.edit ) );
		}
	} );

	nameInput.addEventListener( 'input', () => {
		state.name = nameInput.value;
		setError( 'name', '' );
	} );
	emailInput.addEventListener( 'input', () => {
		state.email = emailInput.value;
		setError( 'email', '' );
	} );
	messageInput.addEventListener( 'input', () => {
		state.message = messageInput.value;
	} );
	serviceBoxes.forEach( ( box ) => {
		box.addEventListener( 'change', () => {
			state.services = serviceBoxes.filter( ( other ) => other.checked ).map( ( other ) => other.value );
			setError( 'services', '' );
		} );
	} );

	cardPicker.render();
	sync();
	show( 1, false );
	root.dataset.ready = 'true';
}

document.querySelectorAll( '[data-contact]' ).forEach( init );
```

Notes for the implementer: `show()` is the only function that changes `state.step`; `goTo()` only adds the phone rule. `commitSlot()` closes the dialog after writing the state, so the `close` event pops the history entry; when the visitor presses the phone's Back, `createSheetHistory` calls `dialog.close()` itself and its `entry` flag is already false, so `pop()` does nothing.

### C6. `blocks/contact/style.css` (new, in full)

```css
/*
 * Home Contact scheduler (DPH-025 to DPH-028). Greyscale tokens only. Desktop: a dark band with a white card.
 * 800px and below: the band shows the heading and one button; the card appears once a time is confirmed.
 * The 800px query is the same as MOBILE_QUERY in view.js (a test compares them).
 */

@media (prefers-reduced-motion: no-preference) {
	html {
		scroll-behavior: smooth;
	}
}

.contact {
	margin-top: var(--wp--preset--spacing--60);
	padding: clamp(24px, 5vw, 56px);
	border-radius: 20px;
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
}

.contact [hidden] {
	display: none !important;
}

.contact__heading {
	margin: 0 0 28px;
	font-size: clamp(26px, 3.4vw, 34px);
	font-weight: 800;
	color: inherit;
}

.contact__sr {
	position: absolute;
	width: 1px;
	height: 1px;
	margin: -1px;
	padding: 0;
	overflow: hidden;
	clip-path: inset(50%);
	white-space: nowrap;
}

.contact__noscript {
	margin: 0;
	color: var(--wp--preset--color--line);
}

.contact__noscript a {
	color: inherit;
}

/* Nothing works without the script: hide the card and the phone button until it is ready. */
.contact:not([data-ready]) .contact__card,
.contact:not([data-ready]) .contact__open {
	display: none;
}

.contact__open {
	display: none;
	min-height: 48px;
	padding: 0 28px;
	border: 0;
	border-radius: 9999px;
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

/* ---- the card ---- */

.contact__card {
	display: flex;
	flex-direction: column;
	max-width: 960px;
	margin: 0 auto;
	padding: 28px;
	border-radius: 16px;
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
}

.contact__crumbs {
	display: flex;
	margin: 0 0 24px;
	padding: 0;
	list-style: none;
}

.contact__crumb {
	position: relative;
	display: flex;
	flex: 1 1 0;
	align-items: center;
	min-width: 0;
	color: var(--wp--preset--color--mid);
}

.contact__crumb:not(:last-child)::after {
	content: '';
	flex: 1 1 auto;
	height: 2px;
	margin: 0 10px;
	background: var(--wp--preset--color--line);
	transition: background-color 0.3s;
}

.contact__crumb.is-done:not(:last-child)::after {
	background: var(--wp--preset--color--contrast);
}

.contact__crumb-btn {
	display: inline-flex;
	align-items: center;
	gap: 8px;
	padding: 0;
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	font-size: 13px;
	white-space: nowrap;
}

button.contact__crumb-btn {
	cursor: pointer;
}

.contact__crumb-num {
	display: inline-grid;
	flex: none;
	place-items: center;
	width: 26px;
	height: 26px;
	border: 1.5px solid var(--wp--preset--color--line);
	border-radius: 50%;
	font-size: 12px;
	font-weight: 600;
}

.contact__crumb.is-done {
	color: var(--wp--preset--color--contrast);
}

.contact__crumb.is-done .contact__crumb-num {
	border-color: var(--wp--preset--color--contrast);
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
}

.contact__crumb.is-current {
	color: var(--wp--preset--color--contrast);
	font-weight: 700;
}

.contact__crumb.is-current .contact__crumb-num {
	border-color: var(--wp--preset--color--contrast);
	box-shadow: 0 0 0 3px var(--wp--preset--color--fill);
}

.contact__slot {
	display: none;
	align-items: center;
	gap: 10px;
	margin: 0 0 16px;
	font-weight: 600;
}

.contact__panel {
	display: flex;
	flex-direction: column;
	flex: 1 1 auto;
}

.contact__title {
	margin: 0 0 18px;
	font-size: 22px;
	font-weight: 800;
	outline: 0;
}

.contact__body {
	flex: 1 1 auto;
}

.contact__field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	max-width: 520px;
	margin-bottom: 16px;
}

.contact__field label {
	font-weight: 600;
}

.contact__optional {
	font-weight: 400;
	color: var(--wp--preset--color--mid);
}

.contact input[type='text'],
.contact input[type='email'],
.contact textarea {
	width: 100%;
	padding: 12px 14px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 8px;
	background: var(--wp--preset--color--base);
	color: inherit;
	font: inherit;
}

.contact textarea {
	resize: vertical;
}

.contact input[aria-invalid='true'] {
	border-color: var(--wp--preset--color--contrast);
	border-width: 2px;
}

.contact__error {
	margin: 0;
	font-weight: 600;
	font-size: 13px;
}

.contact__error:empty {
	display: none;
}

.contact__error::before {
	content: '! ';
}

.contact__hp {
	position: absolute;
	left: -9999px;
	width: 1px;
	height: 1px;
	overflow: hidden;
}

.contact__chips {
	display: flex;
	flex-wrap: wrap;
	gap: 10px;
	margin: 0 0 16px;
	padding: 0;
	border: 0;
}

.contact__chip {
	position: relative;
}

.contact__chip input {
	position: absolute;
	opacity: 0;
	inset: 0;
	margin: 0;
	cursor: pointer;
}

.contact__chip span {
	display: inline-flex;
	align-items: center;
	min-height: 40px;
	padding: 0 16px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 9999px;
}

.contact__chip input:checked + span {
	border-color: var(--wp--preset--color--contrast);
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
}

.contact__chip input:focus-visible + span {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

.contact__summary {
	margin: 0 0 12px;
	border-top: 1px solid var(--wp--preset--color--line);
}

.contact__summary-row {
	display: grid;
	grid-template-columns: 110px minmax(0, 1fr);
	gap: 12px;
	padding: 12px 0;
	border-bottom: 1px solid var(--wp--preset--color--line);
}

.contact__summary dt {
	color: var(--wp--preset--color--mid);
}

.contact__summary dd {
	margin: 0;
	overflow-wrap: anywhere;
	white-space: pre-line;
}

.contact__link {
	margin-left: 10px;
	padding: 0;
	border: 0;
	background: none;
	color: inherit;
	font: inherit;
	font-size: 13px;
	text-decoration: underline;
	cursor: pointer;
}

.contact__footer {
	display: flex;
	justify-content: space-between;
	gap: 12px;
	margin-top: 24px;
	padding-top: 16px;
	border-top: 1px solid var(--wp--preset--color--line);
}

.contact__footer > :only-child {
	margin-left: auto;
}

.contact__btn {
	min-height: 44px;
	padding: 0 24px;
	border: 1.5px solid var(--wp--preset--color--contrast);
	border-radius: 9999px;
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

.contact__btn--fill {
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
}

.contact__btn--block {
	width: 100%;
}

.contact__btn:disabled {
	border-color: var(--wp--preset--color--line);
	background: var(--wp--preset--color--fill);
	color: var(--wp--preset--color--mid);
	cursor: not-allowed;
}

.contact__thanks .contact__title {
	font-size: 26px;
}

.contact__thanks p {
	margin: 0 0 8px;
}

.contact :focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

.contact__open:focus-visible {
	outline-color: var(--wp--preset--color--base);
}

/* ---- the calendar and the times ---- */

.contact-picker {
	display: grid;
	grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
	gap: 32px;
}

.contact-picker__bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 10px;
}

.contact-picker__month {
	margin: 0;
	font-weight: 700;
}

.contact-picker__nav {
	width: 40px;
	height: 40px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 50%;
	background: var(--wp--preset--color--base);
	color: inherit;
	font: inherit;
	font-size: 20px;
	line-height: 1;
	cursor: pointer;
}

.contact-picker__nav:disabled {
	opacity: 0.35;
	cursor: not-allowed;
}

.contact-picker__weekdays,
.contact-picker__grid {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
	gap: 4px;
}

.contact-picker__weekdays {
	margin-bottom: 4px;
	color: var(--wp--preset--color--mid);
	font-size: 12px;
	text-align: center;
}

.contact-picker__day {
	aspect-ratio: 1;
	min-height: 40px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 8px;
	background: var(--wp--preset--color--base);
	color: inherit;
	font: inherit;
	cursor: pointer;
}

.contact-picker__day:disabled {
	border-color: transparent;
	color: var(--wp--preset--color--line);
	cursor: default;
}

.contact-picker__day[aria-pressed='true'],
.contact-picker__time[aria-pressed='true'] {
	border-color: var(--wp--preset--color--contrast);
	background: var(--wp--preset--color--contrast);
	color: var(--wp--preset--color--base);
}

.contact-picker__legend {
	display: flex;
	gap: 18px;
	margin: 12px 0 0;
	padding: 0;
	list-style: none;
	color: var(--wp--preset--color--mid);
	font-size: 12px;
}

.contact-picker__legend li::before {
	content: '';
	display: inline-block;
	width: 12px;
	height: 12px;
	margin-right: 6px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 3px;
	vertical-align: -1px;
}

.contact-picker__legend-selected::before {
	border-color: var(--wp--preset--color--contrast);
	background: var(--wp--preset--color--contrast);
}

.contact-picker__times-title {
	margin: 0 0 10px;
	font-weight: 700;
}

.contact-picker__hint,
.contact-picker__zone {
	margin: 0;
	color: var(--wp--preset--color--mid);
}

.contact-picker__zone {
	margin-top: 12px;
	font-size: 12px;
}

.contact-picker__slots {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
	gap: 8px;
}

.contact-picker__time {
	min-height: 40px;
	border: 1px solid var(--wp--preset--color--line);
	border-radius: 9999px;
	background: var(--wp--preset--color--base);
	color: inherit;
	font: inherit;
	cursor: pointer;
}

.contact-picker__local {
	margin: 10px 0 0;
	font-weight: 600;
}

[data-contact-slot-text],
[data-contact-thanks-time] {
	white-space: pre-line;
}

/* ---- the phone modal ---- */

.contact-scheduler {
	width: 100%;
	max-width: none;
	height: 100%;
	max-height: none;
	margin: 0;
	padding: 0;
	border: 0;
	background: var(--wp--preset--color--base);
	color: var(--wp--preset--color--contrast);
}

.contact-scheduler[open] {
	display: flex;
	flex-direction: column;
}

.contact-scheduler__header {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 12px 16px;
	border-bottom: 1px solid var(--wp--preset--color--line);
}

.contact-scheduler__close {
	display: inline-grid;
	place-items: center;
	width: 44px;
	height: 44px;
	border: 0;
	border-radius: 50%;
	background: none;
	color: inherit;
	cursor: pointer;
}

.contact-scheduler__title {
	margin: 0;
	font-size: 18px;
	font-weight: 800;
}

.contact-scheduler__body {
	flex: 1 1 auto;
	padding: 16px;
	overflow-y: auto;
}

.contact-scheduler__footer {
	padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
	border-top: 1px solid var(--wp--preset--color--line);
}

.contact-scheduler .contact-picker {
	grid-template-columns: minmax(0, 1fr);
	gap: 20px;
}

.contact-scheduler .contact-picker__time {
	min-height: 52px;
}

.contact-scheduler :focus-visible {
	outline: 2px solid var(--wp--preset--color--contrast);
	outline-offset: 2px;
}

html:has(.contact-scheduler[open]) {
	overflow: hidden;
}

/* ---- small screens (same query as MOBILE_QUERY in view.js) ---- */

@media (max-width: 800px) {
	.contact {
		padding: 28px 20px;
	}

	.contact__heading {
		margin-bottom: 20px;
	}

	.contact[data-ready] .contact__open {
		display: inline-flex;
		align-items: center;
	}

	.contact__card {
		display: none;
	}

	.contact.has-slot .contact__open {
		display: none;
	}

	.contact.has-slot .contact__card {
		display: flex;
		min-height: calc(100svh - var(--site-header-height, 61px) - 40px);
		padding: 20px 16px;
	}

	.contact.has-slot:not([data-step='1']) .contact__slot {
		display: flex;
	}

	.contact.is-sent .contact__slot {
		display: none;
	}

	.contact__crumb:not(.is-current) .contact__crumb-label {
		position: absolute;
		width: 1px;
		height: 1px;
		margin: -1px;
		overflow: hidden;
		clip-path: inset(50%);
		white-space: nowrap;
	}

	.contact__footer {
		position: sticky;
		bottom: 0;
		margin-top: auto;
		padding-bottom: calc(12px + env(safe-area-inset-bottom));
		background: var(--wp--preset--color--base);
	}

	.contact__body {
		padding-bottom: 16px;
	}
}

@media (max-width: 700px) {
	.contact-picker {
		grid-template-columns: minmax(0, 1fr);
		gap: 20px;
	}
}

@media (prefers-reduced-motion: reduce) {
	.contact__crumb:not(:last-child)::after {
		transition: none;
	}
}
```

### C7. `templates/front-page.html`: place the section
Find the exact tail

```
		<!-- wp:devportfolio/software-tools /-->

	</div>
	<!-- /wp:group -->

</main>
```
and replace with

```
		<!-- wp:devportfolio/software-tools /-->

	</div>
	<!-- /wp:group -->

	<!-- wp:devportfolio/contact /-->

</main>
```
(Indent as in the file; the block comment is self-closing, so the editor cannot corrupt it.)

### Gate C
```bash
ddev wp theme activate devportfolio
ddev wp eval 'echo do_blocks("<!-- wp:devportfolio/contact /-->") ? "ok" : "empty";'
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
```
Expected: `ok` (needs a Contact email only for the noscript line, not for the render), and the theme suite still all green (the Home template and navigation tests do not yet know the new block; they only check what they list).

---

## Phase D. Theme tests

### D1. `tests/test-contact-block.php` (new, in full)

```php
<?php
/**
 * Tests for the Home Contact scheduler block (DPH-025 to DPH-028).
 *
 * @package devportfolio
 */

/**
 * @group theme
 */
class Devportfolio_Test_Contact_Block extends WP_UnitTestCase {

	private function theme_dir(): string {
		return dirname( __DIR__ );
	}

	private function read( string $relative ): string {
		return (string) file_get_contents( $this->theme_dir() . '/blocks/contact/' . $relative );
	}

	public function set_up(): void {
		parent::set_up();

		do_action( 'cptdp_register_post_types' );

		require_once $this->theme_dir() . '/functions.php';
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'devportfolio/contact' ) ) {
			devportfolio_register_blocks();
		}

		update_option( 'cptdp_contact', array( 'email' => 'dave@example.com' ) );
		devportfolio_memo_flush();
	}

	private function service( string $title, int $order ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_title'  => $title,
				'menu_order'  => $order,
				'post_status' => 'publish',
			)
		);
	}

	private function render(): string {
		devportfolio_memo_flush();

		return do_blocks( '<!-- wp:devportfolio/contact /-->' );
	}

	/** @return array<string,mixed> */
	private function payload( string $html ): array {
		$this->assertSame( 1, preg_match( '#<script type="application/json" class="contact__data">(.*?)</script>#s', $html, $match ) );

		return json_decode( $match[1], true );
	}

	public function test_the_section_is_the_contact_anchor_with_its_heading(): void {
		$html = $this->render();

		$this->assertStringContainsString( 'id="contact"', $html );
		$this->assertStringContainsString( 'aria-labelledby="contact-heading"', $html );
		$this->assertStringContainsString( '<h2 id="contact-heading" class="contact__heading">Book a call</h2>', $html );
		$this->assertStringContainsString( 'data-contact-open', $html );
	}

	public function test_the_nav_contact_link_and_the_section_share_the_anchor(): void {
		$header = (string) file_get_contents( $this->theme_dir() . '/parts/header.html' );

		$this->assertStringContainsString( '"url":"/#contact"', $header );
		$this->assertStringContainsString( 'id="contact"', $this->render() );
	}

	public function test_there_are_four_step_forms_and_one_dialog(): void {
		$html = $this->render();

		foreach ( array( 1, 2, 3, 4 ) as $step ) {
			$this->assertSame( 1, substr_count( $html, 'data-contact-panel="' . $step . '"' ), "step {$step}" );
		}
		$this->assertSame( 4, substr_count( $html, '<form class="contact__panel"' ) );
		$this->assertSame( 4, substr_count( $html, 'novalidate' ) );
		$this->assertSame( 1, substr_count( $html, '<dialog ' ) );
		$this->assertSame( 3, substr_count( $html, 'hidden>' ) - substr_count( $html, 'data-contact-thanks role="status" hidden>' ) );
	}

	public function test_step_one_next_starts_disabled_and_the_modal_confirm_too(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '/data-contact-next disabled>/', $html );
		$this->assertMatchesRegularExpression( '/data-contact-confirm-slot disabled>/', $html );
	}

	public function test_chips_are_the_top_level_services_then_something_else(): void {
		$first  = $this->service( 'Social Media Marketing', 1 );
		$second = $this->service( 'Digital Marketing', 2 );
		self::factory()->post->create(
			array(
				'post_type'   => 'service',
				'post_title'  => 'Analytics',
				'post_parent' => $first,
				'post_status' => 'publish',
			)
		);
		$this->service( 'Draft Service', 3 );
		wp_update_post( array( 'ID' => $this->service( 'Hidden', 4 ), 'post_status' => 'draft' ) );

		$html = $this->render();

		$this->assertSame( 3 + 1, substr_count( $html, 'data-contact-service' ) );
		$this->assertLessThan( strpos( $html, 'Digital Marketing' ), strpos( $html, 'Social Media Marketing' ) );
		$this->assertStringNotContainsString( '>Analytics<', $html );
		$this->assertStringNotContainsString( '>Hidden<', $html );
		$this->assertStringContainsString( 'value="' . $first . '"', $html );
		$this->assertStringContainsString( 'value="' . $second . '"', $html );
		$this->assertStringContainsString( 'value="other"', $html );
		$this->assertGreaterThan( strpos( $html, 'Draft Service' ), strpos( $html, 'Something else' ) );
	}

	public function test_the_payload_carries_the_endpoint_window_slots_and_strings(): void {
		$payload = $this->payload( $this->render() );
		$window  = cptdp_contact_window();

		$this->assertStringContainsString( 'cptdp/v1/contact-request', $payload['endpoint'] );
		$this->assertSame( $window['first'], $payload['first'] );
		$this->assertSame( $window['last'], $payload['last'] );
		$this->assertSame( $window['today'], $payload['today'] );
		$this->assertCount( 16, $payload['slots'] );
		$this->assertNotEmpty( $payload['offsets'] );
		$this->assertSame( $window['today'], $payload['offsets'][0]['from'] );
		$this->assertIsInt( $payload['offsets'][0]['minutes'] );
		$this->assertSame( '09:00', $payload['slots'][0] );
		$this->assertSame( 'other', end( $payload['services'] )['id'] );
		$this->assertSame( 'Book a call', $payload['strings']['heading'] );
	}

	public function test_a_service_title_cannot_close_the_script_element(): void {
		$this->service( 'Bad </script><b>x</b> & "q"', 1 );

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '</script>' ) );
		$this->assertStringNotContainsString( '</script><b>', $html );
		$payload = $this->payload( $html );
		$this->assertSame( 'Bad </script><b>x</b> & "q"', $payload['services'][0]['title'] );
	}

	public function test_the_honeypot_is_hidden_from_people_and_assistive_tech(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '/<div class="contact__hp" aria-hidden="true">/', $html );
		$this->assertMatchesRegularExpression( '/name="website" tabindex="-1" autocomplete="off"/', $html );
	}

	public function test_the_noscript_line_shows_the_email(): void {
		$html = $this->render();

		$this->assertMatchesRegularExpression( '#<noscript>.*mailto:.*</noscript>#s', $html );

		delete_option( 'cptdp_contact' );
		$this->assertStringNotContainsString( 'mailto:', $this->render() );
	}

	public function test_fields_are_labelled_and_errors_are_live_regions(): void {
		$html = $this->render();

		foreach ( array( 'name', 'email', 'message' ) as $field ) {
			$this->assertStringContainsString( 'for="contact-' . $field . '"', $html );
			$this->assertStringContainsString( 'id="contact-' . $field . '"', $html );
		}
		$this->assertSame( 4, substr_count( $html, 'role="alert"' ) );
		$this->assertSame( 4, substr_count( $html, 'data-contact-title' ) );
		$this->assertSame( 4, substr_count( $html, 'tabindex="-1" data-contact-title' ) );
	}

	public function test_every_string_the_scripts_and_the_render_use_exists(): void {
		$strings = devportfolio_contact_strings();

		$used = array();
		foreach ( array( 'view.js', 'picker.js', 'steps.js' ) as $file ) {
			preg_match_all( '/strings\.(\w+)/', $this->read( $file ), $found );
			$used = array_merge( $used, $found[1] );
		}
		preg_match_all( "/\\\$s\\['(\w+)'\\]/", $this->read( 'render.php' ), $found );
		$used = array_unique( array_merge( $used, $found[1] ) );

		$this->assertNotEmpty( $used );
		foreach ( $used as $key ) {
			$this->assertArrayHasKey( $key, $strings, "strings.{$key} is used but not defined." );
			$this->assertNotSame( '', $strings[ $key ], "strings.{$key} is empty." );
		}
	}

	public function test_every_data_hook_the_scripts_query_is_in_the_markup_or_built_by_the_scripts(): void {
		$render = $this->read( 'render.php' );
		$built  = array( 'data-edit', 'data-key' ); // Added by the scripts themselves.

		foreach ( array( 'view.js', 'picker.js' ) as $file ) {
			preg_match_all( '/\[(data-contact[\w-]*|data-edit|data-key)[\]=]/', $this->read( $file ), $found );
			foreach ( array_unique( $found[1] ) as $hook ) {
				if ( in_array( $hook, $built, true ) ) {
					continue;
				}
				$this->assertStringContainsString( $hook, $render, "{$file} queries [{$hook}] but render.php never writes it." );
			}
		}
	}

	public function test_the_page_sends_the_visitor_zone_and_never_a_utc_instant(): void {
		$js = $this->read( 'view.js' );

		$this->assertStringContainsString( 'timezone: visitorZone', $js );
		$this->assertStringContainsString( 'resolvedOptions().timeZone', $js );
		$this->assertDoesNotMatchRegularExpression( '/slot_utc|slotInstant|toISOString/', $js ); // The server alone makes the stored instant.
	}

	public function test_the_mobile_query_is_800px_in_the_script_and_the_css(): void {
		$this->assertStringContainsString( "const MOBILE_QUERY = '(max-width: 800px)';", $this->read( 'view.js' ) );
		$this->assertStringContainsString( '@media (max-width: 800px)', $this->read( 'style.css' ) );
	}

	public function test_the_script_keeps_nothing_in_the_browser_and_uses_the_shared_history(): void {
		$js = $this->read( 'view.js' ) . $this->read( 'picker.js' );

		$this->assertDoesNotMatchRegularExpression( '/localStorage|sessionStorage|indexedDB|document\.cookie/', $js );
		$this->assertStringContainsString( "from '../shared/sheet-back.js'", $js );
		$this->assertMatchesRegularExpression( '/const history = createSheetHistory\(/', $js );
	}

	public function test_the_css_has_the_phone_rules_and_motion_guards(): void {
		$css = $this->read( 'style.css' );

		$this->assertStringContainsString( '.contact:not([data-ready]) .contact__card', $css );
		$this->assertStringContainsString( '.contact.has-slot .contact__card', $css );
		$this->assertStringContainsString( 'position: sticky', $css );
		$this->assertStringContainsString( 'html:has(.contact-scheduler[open])', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: no-preference)', $css );
		$this->assertStringContainsString( '@media (prefers-reduced-motion: reduce)', $css );
		$this->assertStringContainsString( '.contact [hidden]', $css );
	}

	public function test_the_block_renders_nothing_without_the_plugin_config(): void {
		$file = $this->read( 'render.php' );

		$this->assertStringContainsString( 'if ( empty( $devportfolio_config ) )', $file );
		$this->assertStringContainsString( "function_exists( 'cptdp_contact_config' )", (string) file_get_contents( $this->theme_dir() . '/functions.php' ) );
	}
}
```

Notes: `test_there_are_four_step_forms_and_one_dialog` counts `hidden>` occurrences (three panels 2 to 4 plus the thanks `div`): if the count assertion is brittle after a markup edit, replace that single line with `assertSame( 3, preg_match_all( '/<form class="contact__panel"[^>]* hidden>/', $html ) )`. The same applies to `test_fields_are_labelled…` counts (4 alerts: name, email, services, form error). Report, do not weaken a test silently.

### D2. `tests/test-home-template.php`: add one test
Add this method after `test_testimonials_and_tools_follow_selected_work()` (before the closing `}` of the class):

```php

	public function test_contact_closes_the_page_after_the_software_tools(): void {
		$tpl     = $this->template();
		$needles = array(
			'<!-- wp:devportfolio/software-tools /-->',
			'<!-- wp:devportfolio/contact /-->',
			'</main>',
			'<!-- wp:template-part {"slug":"footer"',
		);

		$last = -1;
		foreach ( $needles as $needle ) {
			$pos = strpos( $tpl, $needle );
			$this->assertNotFalse( $pos, "{$needle} missing." );
			$this->assertGreaterThan( $last, $pos, "{$needle} is out of order." );
			$last = $pos;
		}
	}
```

### Gate D (all suites)
```bash
ddev exec --dir=/var/www/html/wp-content/plugins/custom-post-types-dp vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio ../../plugins/custom-post-types-dp/vendor/bin/phpunit
ddev exec --dir=/var/www/html/wp-content/themes/devportfolio/blocks/contact 'node --test tests/*.test.mjs'
```
Expected: plugin = 918 + the new class's tests; theme = 687 + 17 (class) + 1 (template); contact JS 17. Other Node suites unchanged.

---

## Phase E. Browser check (no runner in the repo)

Headless Chrome with puppeteer-core in a scratch folder (not in the repo), against `https://devportfolio.ddev.site/`, at **1280x900** and **390x844**. First: `ddev wp option update cptdp_contact '{"email":"dave@example.com"}' --format=json`, then `ddev wp cptdp seed services` if the services are missing, and open Mailpit (`ddev launch -m`) to read the mail.

Desktop (1280):
1. Open `/#contact`: the page scrolls to the section under the sticky header (heading visible). Four crumbs: 1 current (ringed, bold), 2 to 4 grey. Hint "Pick a day to see the times." Next disabled. Today and every earlier day are faded and disabled; tomorrow is outlined and enabled; the previous-month arrow is disabled in the first month.
2. Pick a day: the 16 pills appear (09:00 to 16:30). Next is still disabled. Pick a time: Next enables. Pick another day: the time clears and Next disables.
3. Next: focus is on "Introduce yourself" (the heading), crumb 1 is black with "1", crumb 2 ringed; clicking crumb 1 goes back, the choice is kept. Empty Next shows two plain messages, announced, focus on Name; typing clears the message. `jane@` shows "does not look right". Enter in Name submits the step.
4. Step 3: no chip, Next shows the message; chips are the three service names + "Something else"; "Tell me more" is optional. Esc does nothing in the card.
5. Step 4: the five rows; "Edit" on Name goes to step 2 and **Next goes straight back to step 4**; nothing is in Mailpit yet. "Confirm my interest ↗" disables while sending, then the card shows "Thanks, Jane. Interest received.", the time and "I'll confirm by email."; all four crumbs are black; one mail in Mailpit with every detail and Reply-To.
6. Failure paths: with the Contact email deleted (`ddev wp option delete cptdp_contact`) a send still succeeds, no mail arrives, and the Interest shows "Email notification: Not sent". With the rate limit filter set to 1 (a one-line mu-plugin) the second send shows the "Too many tries" line.
7. DevTools: Application → Storage is empty for the site (nothing stored); the request payload is JSON to `/wp-json/cptdp/v1/contact-request`.

Interests and spam (wp-admin, 1280):
7a. Akismet: install and connect the Akismet plugin on the dev site (or `define( 'WPCOM_API_KEY', '<key>' );` in `wp-config-ddev.php`). Without any key the Interests list shows the warning "Spam check is off" and every submission is visible and emailed (`Spam check: Not checked (no Akismet key)`).
7b. Sidebar: "Interests" with a lock icon and a badge with the number of submissions not opened yet; "Add New" is nowhere. List columns Date, Name, Email, Service with checkboxes; tabs "All (n) | Potential spam (n)" (and "Trash (n)" once something is trashed) and no "Published" tab; a row has "View | Trash"; the bulk menu has no Edit. The view shows grey read-only blocks under a "🔒 Read-only" badge (Date and time, Name, Email, **IP address**, Service, Requested time, Message, Spam check, Email notification), no Publish box, no Update, and a "Move to Trash" link. Opening a row lowers the badge on the next page load.
7c. Genuine: submit from the card; with Akismet connected the row appears in All as "Not spam (Akismet)", the view shows your own IP address (`127.0.0.1` or the DDEV router address on a local site, which is expected) and the mail arrives.
7d. Spam: submit with the name `viagra-test-123` and the email `akismet-guaranteed-spam@example.com` (Akismet's documented test values; if they do not trigger a spam verdict, check Akismet's current test instructions). The visitor still sees "Thanks, ... Interest received."; no mail; the item is **not** in All and not in the badge; the "Potential spam (1)" tab shows it with View, Not spam and Trash, and it also has an IP address.
7e. "Not spam" moves it to All (`Marked not spam by you`), the badge counts it and the held-back mail arrives. "Trash" on any row moves it to the Trash tab; there "Restore" returns it to the tab it came from (a spam item comes back as potential spam) and "Delete permanently" removes it for good.
7f. Turn the network off for the dev site (or set a wrong key): submissions are still visible and emailed with `Spam check: Not checked (...)`, and the site does not hang (5 s at most).

Time zones (all stored in UTC, all shown locally):
7g. Set the dev site's zone to Sydney (Settings → General → Timezone). Submit a time (say Tue 6 Oct 10:30). `ddev wp post meta get <interest id> cptdp_slot_utc` prints the same moment in UTC with a `Z` (`2026-10-05T23:30:00Z`), `ddev wp post get <id> --field=post_date_gmt` is the UTC time received, and `cptdp_visitor_tz` holds the browser's zone.
7h. In the browser, emulate another zone (DevTools → Sensors → Location → Timezone, or puppeteer `page.emulateTimezone( 'America/Los_Angeles' )`) and reload: after choosing the time the picker shows "Mon 5 Oct · 16:30 in your time zone (America/Los_Angeles)" under the times; the summary row, the phone time line and the thank-you show both lines ("Tue 6 Oct · 10:30 Sydney time" and the local one). With the browser in Sydney there is no second line.
7i. In wp-admin, with the browser in two different zones, the list's Date column and the view's "Date and time" and "Requested time" change to that zone and show its short name (hover for the UTC value); the view also says "Visitor's time: ... (America/Los_Angeles)". With JavaScript off they show the site zone.
7j. Dave's email shows "Time: Tue 6 Oct 2026, 10:30 (Australia/Sydney); 2026-10-05 23:30 UTC; Mon 5 Oct 2026, 16:30 (America/Los_Angeles, the visitor's time)".
7k. Change the site zone to UTC and to Asia/Kolkata and submit again: the stored UTC instants stay correct for the same wall-clock choice; across the Sydney daylight-saving change dates (first Sunday of October and of April) the 10:30 slot maps to 23:30Z / 00:30Z as the node tests say.

Phone (390x844, touch emulation):
8. The band shows only the heading and "Check availability"; no card, no crumbs. The button opens the full-screen modal: ✕ and "Check my availability", the calendar, the legend, no times until a day is picked, then large pills; "Confirm date & time" pinned and disabled until a time is picked. The page behind does not scroll.
9. ✕, Esc and the phone Back button each close the modal without choosing and leave the page on the button (Back does not leave the page: check `history.length` and the URL are unchanged after open/close/Back).
10. Confirm a time: the modal closes, the button is replaced by the card at step 2 with "Tue 6 Oct · 10:30 · Change" at the top, crumbs show only the current name with the numbered dots, Back and Next are pinned at the bottom of a screen-high card. "Change" reopens the modal with that day and time selected; ✕ keeps the old time. Back on step 2 reopens the modal. Step 4 "Edit" on Time reopens the modal; confirming returns to step 4 with the new time in the summary.
11. Rotate or resize across 800px with the modal open: it closes without choosing; at desktop width the card shows at the current step.
12. `prefers-reduced-motion: reduce`: no smooth scroll, no crumb line transition.

## Phase F. AGENTS.md
Add to the **Overview**: *"The **Interests** screen (`includes/post-types/interest.php`, `interest-admin.php`, plugin 0.34.0, DPB-017): CPT `interest` (not public, no REST, no Add New, read-only view, menu position 31, lock icon, badge of unopened items) holds the Home Contact submissions (title = name, content = message, meta `cptdp_email`, `cptdp_ip`, `cptdp_slot_utc` (the requested time in UTC, ISO 8601 with `Z`), `cptdp_visitor_tz`, `cptdp_services`, `cptdp_spam_check`, `cptdp_mail_sent`, `cptdp_seen`; Trash is enabled); the custom status `potential-spam` hides a submission from All and the badge and gives it its own tab with Not spam. `cptdp_akismet_check()` asks Akismet (REST call, key from `WPCOM_API_KEY` or the Akismet plugin's `wordpress_api_key`, filter `cptdp_akismet_key`, fails open) before a submission is saved; the visitor's IP is stored on the Interest, the browser and referrer go only to Akismet. The **contact request** endpoint (`includes/settings/contact-request.php`): `POST cptdp/v1/contact-request` (public; honeypot `website`, 10 requests/hour/client via transient, filters `cptdp_contact_rate_limit` and `cptdp_contact_client_ip`, helper `cptdp_contact_client_ip()`) validates, rate-limits, checks with Akismet, saves an Interest (`publish` or `potential-spam`) and emails the Settings → Contact address (Reply-To the prospect) unless it is spam. `cptdp_contact_time_slots()` (16 half-hour slots 09:00 to 16:30, filter `cptdp_contact_time_slots`), `cptdp_contact_window()` (tomorrow to +365 days in the site zone), `cptdp_contact_service_options()` (top-level Services, then `other`), `cptdp_contact_utc_offsets()` (the site zone's UTC offsets over the window, for the page) and `cptdp_contact_config()` are the single source for the endpoint and the theme (DPH-031 replaces the first two)."* Everything stored is UTC (requested time `cptdp_slot_utc`, received time `post_date_gmt`); the Interests list and view show times in the admin's browser zone through `assets/admin-interest.js` (`<time datetime="…Z" data-cptdp-local>`), and the visitor sees Dave's times plus their own zone (`time.js`). Add under **Theme architecture** a `blocks/contact/` bullet: dynamic block `devportfolio/contact` (DPH-025 to DPH-028), placed last in `front-page.html`, section `#contact`, files (`render.php` markup and JSON payload, `calendar.js`, `steps.js` and `time.js` pure with `node --test`, `picker.js` shared by the card and the phone modal, `view.js` state in memory only, `style.css`), the 800px query (`MOBILE_QUERY` equals the CSS), the phone modal (`createSheetHistory` created while the module runs, a draft committed by "Confirm date & time"), `devportfolio_contact_strings()`/`devportfolio_contact_config()` in `functions.php`, and the test files. Update the test totals once the gates are run, add `tests/test-contact-block.php` to the theme test list and `tests/post-types/test-interest.php`, `tests/settings/test-contact-request.php` to the plugin list.

## Conventional commits (as built)

Branch `feat/DPH-025-026-027-028-Contact-Scheduler` from `main`. Commits are in order, oldest first. Each one was added by staging its own files by path; the suites were run on the final tree (plugin 1035 tests, 12 skipped; theme 734 tests; the nine contact and block Node suites all pass). Co-author on every commit: `Claude Haiku 5.5`.

Plugin (`custom-post-types-dp`):
1. `feat(custom-post-types-dp): add the Interest post type and Akismet check`. Files: `includes/post-types/interest.php`. Refs: DPB-017, DPH-027.
2. `feat(custom-post-types-dp): add the read-only Interests screens`. Files: `includes/post-types/interest-admin.php`, `assets/admin-interest.js`. Refs: DPB-017, DPH-027.
3. `test(custom-post-types-dp): cover the Interest post type and screens`. Files: `tests/post-types/test-interest.php`. Refs: DPB-017, DPH-027.
4. `feat(custom-post-types-dp): let Dave choose the booking times`. Files: `includes/settings/contact.php`. Refs: DPH-025, DPH-026, DPH-027. (Not in the original list: the times are a setting.)
5. `feat(custom-post-types-dp): add the contact request endpoint`. Files: `includes/settings/contact-request.php`, `tests/documents/test-page-rest.php`, `tests/collaterals/test-collateral-pages.php` (the public-route exemption). Refs: DPH-027.
6. `test(custom-post-types-dp): cover the contact request endpoint and booking times`. Files: `tests/settings/test-contact-request.php`, `tests/settings/test-contact-times.php`. Refs: DPH-027.
7. `chore(custom-post-types-dp): bump version to 0.34.0`. Files: `custom-post-types-dp.php`. Refs: DPB-017, DPH-027.
8. `feat(custom-post-types-dp): order the admin sidebar`. Files: `includes/post-types/menu-order.php`, `tests/post-types/test-menu-order.php`, the menu positions in `includes/post-types/{collateral,document,interest,service,software-tool,statistic,video}.php` and their tests. Refs: DPB-017, DPH-027. (Not in the original list: whole-number positions, Comments moved after Videos.)

Theme (`devportfolio`):
9. `feat(theme): add the contact calendar, step and time modules`. Files: `blocks/contact/calendar.js`, `steps.js`, `time.js`. Refs: DPH-025, DPH-026, DPH-027.
10. `test(theme): cover the contact calendar, step and time modules`. Files: `blocks/contact/tests/calendar.test.mjs`, `steps.test.mjs`, `time.test.mjs`. Refs: DPH-025, DPH-026, DPH-027.
11. `feat(theme): add the Home contact scheduler block`. Files: `blocks/contact/block.json`, `render.php`, `picker.js`, `view.js`, `style.css`, `functions.php`, `templates/front-page.html`. Refs: DPH-025, DPH-026, DPH-027, DPH-028.
12. `test(theme): cover the contact block and the Home template`. Files: `tests/test-contact-block.php`, `tests/test-home-template.php`. Refs: DPH-025, DPH-026, DPH-027, DPH-028.

Docs and plan:
13. `docs(agents): document the contact scheduler and Interests`. Files: `AGENTS.md`. Refs: DPB-017, DPH-025 to DPH-028.
14. `docs(agents): record the admin sidebar order`. Files: `AGENTS.md`. Refs: DPB-017, DPH-027.
15. `docs(plans): add DPH-025-026-027-028 plan`. Files: `.claude/plans/home/DPH-025-026-027-028-Home-Contact-Scheduler.md`, `.claude/plans/home/Home-User-Stories-Revised.csv`. Refs: DPH-025 to DPH-028.
16. `docs(plans): record the as-built decisions in the DPH-025 plan`. Files: `.claude/plans/home/DPH-025-026-027-028-Home-Contact-Scheduler.md`. Refs: DPH-025 to DPH-028.

**PR title:** `feat(theme): add the Home contact scheduler and Interests` (63 characters). The PR description is at `Desktop/DPH-025-026-027-028-PR-Description.md`, with the screenshots beside it.

## Risks to watch
- `REMOTE_ADDR` behind a proxy makes the rate limit global (Open decision 3).
- A full-page cache would freeze `today` in the payload; the server still rejects a stale day with 422 and the form shows "That time is no longer available" and returns to step 1, so the failure is visible and recoverable.
- Mail delivery on the live host (SPF/DKIM) decides whether Dave sees the email; `wp_mail()` returning true only means it was handed over. Test one real send on Hostinger before launch.
- The calendar is Tab-navigable, not an arrow-key grid (Open decision 4).
- **Personal data goes to Akismet** (name, email, message, IP, browser) and the name, email, message and IP are stored on the site; the user has accepted this. Deleting an Interest (Trash, then Delete permanently or WordPress's 30-day empty) is how it is erased.
- **Licence:** the free Akismet key is for non-commercial sites (Open decision 8).
- **False positives:** Akismet may hide a real lead. Dave must look at the "Potential spam" tab now and then; the list shows a count on the tab but, by design, not in the sidebar badge.
- **Interests are personal data at rest.** Only users who can open the Interests screen (administrators and editors by the default `post` capabilities) see them; if editors must not, give the type its own `capability_type` in a follow-up.
- **Extra latency:** the Akismet call can add up to 5 s to a submission in the worst case; the button shows "Sending…" meanwhile.
