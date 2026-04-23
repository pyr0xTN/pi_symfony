# Session Changes Summary

Here are all the modifications made across the application during our debugging session. I have categorised these changes to help you curate your next commits.

You can also find an exact standard `git diff` generated for you in the following file:
- `all_my_changes_summary.diff` (located in the root folder of your Symfony app)

## 1. Feature: Complete Dark Mode Removal

We completely scrubbed the "Dark Mode toggle" feature from the entire application, permanently anchoring the design in its primary theme.

**Modified Files:**
- **`templates/base.html.twig`**
  - Removed the `globalThemeButton`, `globalThemeIcon`, and `globalThemeLabel` buttons and blocks from the left-side global glass panel layout.
  - Trimmed the CSS/JS `<script>` block in the header, erasing the `getStoredTheme()`, `applyTheme()`, and `toggleTheme()` self-invoking functions.
  - Stripped away corresponding `window.addEventListener('themechange', ...)` Javascript events and any occurrences of the `syncThemeIcon` UI logic across the entire body's initialization.
- **`templates/front/layout.html.twig`**
  - Removed the `theme-toggle-nav` toggle button (moon icon button) from the top global navigation bar.
- **`templates/home/mainpage.html.twig`**
  - Eliminated `.theme-toggle` inline and redundant CSS overrides specific to the index timeline section.
- **`public/css/app.css`**
  - Cleared the `.theme-toggle-nav` and `.theme-toggle-btn` container/hover properties as they are now dead CSS code.
- **`public/js/app.js`**
  - Exised the `function toggleTheme()` block and the `localStorage.getItem('theme')` initialization that would attempt to apply a dark theme asynchronously.

## 2. Bug Fix: Explore Map Missing Render

We investigated why the "Explore Map" full-screen overlay was completely collapsing and rendering invisible to users when clicking the `map-fab-btn`.

**Modified Files:**
- **`templates/front/map.html.twig`**
  - *Issue*: The map template overrode the empty `{% block stylesheets %}` from the parent `base.html.twig` but completely neglected to bundle `app.css`. This meant critical core classes driving the CSS flex-box logic like `map-view-root` simply didn't exist. Thus, Leaflet's engine got instantiated into a 0-height non-existent wrapper element.
  - *Fix*: prepended `<link rel="stylesheet" href="{{ asset('css/app.css') }}">` straight into the asset block so that headers hit their 100vh max width and flex scaling constraints properly, successfully expanding internal map limits.

## 3. Bug Fix: Turbo Navigation & Duplicate Injection

We fixed an issue where returning from full-page isolated modules via the back link resulted in broken dashboard module clicks ("Clicking Posts only makes the button active").

**Modified Files:**
- **`templates/front/layout.html.twig`**
  - Fixed the back arrow `<a>` tag so it correctly targets `path('app_mainpage')` securely without carrying over the `?module=post` variable, breaking out of query-param looping.
- **`templates/home/mainpage.html.twig`**
  - Adjusted jQuery document load logic to securely hook onto `document.addEventListener('turbo:load')`. This ensures modular javascript re-binds `.nav-tab` module swapping properly regardless of instantaneous HTML morphing from Symfony UX Turbo.

## 4. Performance & Layout Patch: Blazing Fast AJAX Engine

We optimized modular loading. The community/posts module was mistakenly taking a "hella long time" to display because it was returning a completely formatted DOM (with redundant duplicated Javascript files and raw headers) instead of the UI fragment.

**Modified Files:**
- **`src/Controller/HomeController.php`**
  - Hardcoded `$this->forward(..., ['embed' => 1])` explicitly. Due to a quirk in how `duplicate()` works within Symfony SubRequest architecture, mutations to the Master request weren't propagating, causing `embed` to falsely toggle off.
- **`templates/empty_body.html.twig`** *[NEW FILE]*
  - Designed a bare-metal skeleton layout containing absolutely no `<head>` components to act as the root skeleton for seamless injections.
- **`templates/front/layout.html.twig`**
  - Swapped the parent layout constraint to point to `empty_body.html.twig` when `embed=true`. Now, the injected module correctly fetches your global toolbars, your Map FAB, and User Chat boxes, but completely sheds the overhead of executing `<head>` scripts dynamically.
  - Placed an inline `<link>` to `app.css` directly securely inside the layout block to ensure grid CSS is retrieved if the head is purged.
  - Isolated the `app.js` loader inside an `{% if not embed %}` check to avoid syntax console errors (like "Identifier has already been declared") due to double script executions.
- **`templates/front/feed.html.twig`**
  - Removed clunky workaround "Mini Nav-bar" blocks now that the app safely routes through the main navigation skeleton simultaneously in `embed` mode without locking up.

## 5. UI Consistency: Avatar & Sidebar Alignment Correction

Fixed massive text misalignment gaps that occurred inside Create Modal and User Info bubbles where avatars strayed awkwardly away from usernames towards the right. 

**Modified Files:**
- **`public/css/app.css`**
  - Killed an inherited rogue `margin: auto;` rule by enforcing `margin: 0 !important;` dynamically onto grid avatars (`.avatar`).
  - Added strict explicit `text-align: left; flex: 1;` parameters across text containers inside `.post-header-info`, `.detail-author-row`, and `.cpd-user-strip`. This immediately cures edge bleeding if center-aligning parents leak their rules downwards into the feed.
 
## 6. Feature: Weather Emoji Badges Integration

Configured the frontend to load and render OpenWeatherMap data flawlessly.

**Modified Files:**
- **.env**: Declared OPENWEATHERMAP_API_KEY to stop internal 500 errors crashing the API endpoint.
- ** emplates/components/post_card*.twig**: Added the empty HTML wrapper for badges.
- **public/js/app.js**: Replaced isolated inline scripts with a robust global fetching method that injects Emoji + Temperature synchronously on grid/list loads.
- ** emplates/front/post_detail.html.twig**: Relied on the new global JS loader instead of an isolated script tag.

## 7. UI Fix: Map Marker Overlap Scatter

A mathematical jitter equation was implemented internally within Leaflet to randomize marker geometry by around 0.005 degrees successfully scattering duplicated markers.

