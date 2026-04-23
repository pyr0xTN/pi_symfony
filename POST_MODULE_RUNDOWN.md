# 📋 Posts Module — Complete Rundown

## Table of Contents
- [External Bundles & APIs](#-external-bundles--apis-used)
- [Feature 1: AI Sentiment Analysis](#1--ai-sentiment-analysis-groq-api)
- [Feature 2: Feed Pagination](#2--feed-pagination-knppaginatorbundle)
- [Feature 3: PDF Travel Journal Export](#3--pdf-travel-journal-export-dompdf)
- [Feature 4: Weather Badges](#4--weather-emoji-badges-openweathermap-api)
- [Feature 5: Custom Toast Validation](#5--custom-toast-form-validation)
- [Feature 6: Explore Map with Jitter](#6--explore-map-with-marker-jitter-leafletjs)
- [Feature 7: AI Travel Assistant](#7--ai-travel-assistant-chat-groq-api)
- [Feature 8: AI Image Suggestion](#8--ai-image-suggestion-unsplash-api)
- [Environment Variables](#-environment-variables)

---

## 📦 External Bundles / APIs Used

| Bundle / Library | Composer Package | Purpose |
|---|---|---|
| **KnpPaginatorBundle** | `knplabs/knp-paginator-bundle` | Paginate the community feed (6 posts/page) |
| **Dompdf** | `dompdf/dompdf` | Generate PDF travel journal export |
| **Symfony UX Turbo** | `symfony/ux-turbo` | SPA-like navigation without full page reloads |
| **Leaflet.js** | CDN (`unpkg.com/leaflet@1.9.4`) | Interactive map for the "Explore Map" feature |

| External API | Provider | Purpose |
|---|---|---|
| **Groq (LLaMA 3.3 70B)** | `api.groq.com` | AI Sentiment Analysis + AI Travel Chat Assistant |
| **OpenWeatherMap** | `api.openweathermap.org` | Real-time weather badges on posts with locations |
| **Google Places** | via `PlacesAutocompleteService` | Location autocomplete when creating/editing posts |
| **Unsplash** | `api.unsplash.com` | Photo fetching for post illustrations |

---

## New Features Added This Session

### 1. 🧠 AI Sentiment Analysis (Groq API)

**What it does:** Automatically analyzes each post's content using LLaMA 3.3 and displays a mood emoji badge (e.g. `🏔️ adventurous`, `🌴 relaxing`, `😊 happy`) on every post card.

**Files involved:**
| File | Role |
|---|---|
| `src/Service/SentimentService.php` | **[NEW]** Sends post content to Groq, parses JSON response into mood + emoji |
| `src/Controller/ApiController.php` | Added `GET /api/sentiment/{id}` endpoint |
| `config/services.yaml` | Registered `SentimentService` with `GROQ_API_KEY` |
| `templates/components/post_card.html.twig` | Added `.mood-badge` span element |
| `templates/components/post_card_list.html.twig` | Added `.mood-badge` span element |
| `public/js/app.js` | Added `loadMoodBadges()` global JS function |
| `public/css/app.css` | Added `.mood-badge` purple pill styling |

**How it works:**
1. On page load, `loadMoodBadges()` finds all `.mood-badge` spans
2. For each, fetches `GET /api/sentiment/{postId}`
3. Backend sends post content to Groq with a strict system prompt
4. Groq returns `{"mood": "adventurous", "emoji": "🏔️"}`
5. Badge is rendered as a purple pill next to the place tag

---

### 2. 📄 Feed Pagination (KnpPaginatorBundle)

**What it does:** Instead of loading every post at once, the feed now paginates at 6 posts per page with styled navigation controls.

**Files involved:**
| File | Role |
|---|---|
| `src/Controller/PostController.php` | Injected `PaginatorInterface`, paginate query at 6/page |
| `src/Repository/PublicationRepository.php` | Added `findAllApprovedQb()` and `searchByKeywordQb()` QueryBuilder methods |
| `templates/front/feed.html.twig` | Added `{{ knp_pagination_render(posts) }}` block |
| `config/packages/knp_paginator.yaml` | Already configured with Bootstrap 5 template |
| `public/css/app.css` | Added `.pagination-wrapper` and `.page-item` styling |

**How it works:**
1. `PostController::feed()` gets a QueryBuilder instead of a result array
2. KnpPaginator wraps it with `?page=N` support
3. Twig renders styled page numbers at the bottom of the feed
4. CSS matches the app's accent gradient theme

---

### 3. 📑 PDF Travel Journal Export (Dompdf)

**What it does:** Users can click "📄 Journal" in the nav bar to download all their posts as a beautifully formatted A4 PDF travel diary.

**Files involved:**
| File | Role |
|---|---|
| `src/Controller/PostController.php` | Added `GET /community/journal/pdf` route using Dompdf |
| `templates/front/journal_pdf.html.twig` | **[NEW]** Styled HTML template for the PDF (cover page, post entries, footer) |
| `templates/front/layout.html.twig` | Added "📄 Journal" secondary button in the nav bar |
| `public/css/app.css` | Added `.secondary-btn` outline button style |

**How it works:**
1. User clicks the Journal button
2. Controller queries all posts by the logged-in user
3. Renders `journal_pdf.html.twig` with embedded CSS (Dompdf-compatible)
4. Dompdf converts the HTML to a PDF and returns it as a download
5. Includes: author avatar, post dates, locations, content, images, like/comment stats

---

### 4. 🌤️ Weather Emoji Badges (OpenWeatherMap API)

**What it does:** Posts with a location automatically show a weather badge like `☀️ 28°C` fetched in real-time from OpenWeatherMap.

**Files involved:**
| File | Role |
|---|---|
| `src/Service/WeatherService.php` | Fetches weather data, parses temp/emoji/description |
| `src/Controller/ApiController.php` | `GET /api/weather/{place}` endpoint |
| `templates/components/post_card.html.twig` | Added `.weather-badge` span with `data-place` |
| `templates/components/post_card_list.html.twig` | Added `.weather-badge` span with `data-place` |
| `templates/front/post_detail.html.twig` | Added `.weather-badge` span with `data-place` |
| `public/js/app.js` | Added `loadWeatherBadges()` global JS function |
| `public/css/app.css` | `.weather-badge` blue pill styling |

---

### 5. 🔔 Custom Toast Form Validation

**What it does:** Replaces the ugly browser-default "Please fill out this field" tooltip with animated toast notifications that slide in from the right, plus a red shake animation on the invalid field.

**Files involved:**
| File | Role |
|---|---|
| `public/js/app.js` | `showToast()` function + `initFormValidation()` with capture-phase listener |
| `public/css/app.css` | `.toast-notification`, `.toast-error/success/info`, `.field-error`, `@keyframes shake` |
| `templates/front/create_post.html.twig` | Added `minlength="3"` and `maxlength="2000"` to textarea |

**Validation checks:** `required`, `minlength`, `maxlength`, `pattern`

**Key technical detail:** Uses `e.stopImmediatePropagation()` in the **capture phase** to prevent Symfony UX Turbo from starting its fetch cycle on validation failure.

---

### 6. 🗺️ Explore Map with Marker Jitter (Leaflet.js)

**What it does:** Full-screen interactive map showing all posts that have locations. Markers for identical locations are randomly scattered to prevent stacking.

**Files involved:**
| File | Role |
|---|---|
| `templates/front/map.html.twig` | Leaflet map initialization + jitter formula |
| `src/Controller/ApiController.php` (via MapController) | `GET /api/map/markers` endpoint |
| `public/css/app.css` | `.map-view-root`, `.map-header`, `.map-popup` styling |
| `templates/front/layout.html.twig` | Added `data-turbo="false"` to the map FAB to ensure Leaflet renders correctly |

**Turbo Compatibility:** To avoid Leaflet rendering in a 0x0 container during partial page swaps, Turbo is disabled specifically for the map link, forcing a clean initialization.

**Jitter formula:**
```js
const jitterLat = (Math.random() - 0.5) * 1.005;
const jitterLon = (Math.random() - 0.5) * 1.005;
L.marker([m.lat + jitterLat, m.lon + jitterLon])
```

---

### 7. 💬 AI Travel Assistant Chat (Groq API)

**What it does:** "Ask Rihla" floating button opens a chat panel powered by Groq's LLaMA model, specialized in Tunisian travel advice.

**Files involved:**
| File | Role |
|---|---|
| `src/Service/AiChatService.php` | Manages conversation history, sends to Groq |
| `src/Controller/ApiController.php` | `POST /api/chat` and `POST /api/chat/clear` endpoints |
| `templates/front/layout.html.twig` | Chat panel HTML with input/send button |
| `public/js/app.js` | `sendChatMessage()`, `toggleChatPanel()` functions |

---

### 8. ✨ AI Image Suggestion (Unsplash API)

**What it does:** Help users find perfect cover photos for their posts using AI-powered search on Unsplash.

**Files involved:**
| File | Role |
|---|---|
| `src/Service/UnsplashService.php` | Fetches landscape travel photos based on location/content |
| `src/Controller/ApiController.php` | Added `GET /api/unsplash/{query}` endpoint |
| `templates/front/create_post.html.twig` | Added "AI Suggest" button and auto-assignment logic |
| `public/css/app.css` | Added styles for photographer attribution and loading states |

**How it works:**
1. User clicks "✨ AI Suggest" in the post toolbar.
2. The app uses the current Location (or post content keywords) as a search query.
3. Fetch returns a high-res travel photo from Unsplash.
4. Photo is instantly applied to the preview with mandatory photographer attribution.

---

## 🔐 Environment Variables

Add these to your `.env.local` (never commit real keys!):

```env
OPENWEATHERMAP_API_KEY=your_key_here
GROQ_API_KEY=gsk_your_key_here
UNSPLASH_ACCESS_KEY=your_key_here
GOOGLE_PLACES_API_KEY=your_key_here
```

---

## 🏗️ Architecture Overview

```
PostController
├── feed()          → Paginated feed (KnpPaginator)
├── detail()        → Single post view with comments
├── new()           → Create post form
├── edit()          → Edit post form
├── delete()        → Delete post
└── journalPdf()    → PDF export (Dompdf)

ApiController
├── /api/like/toggle        → Like/unlike posts
├── /api/comment            → Add/edit/delete comments
├── /api/weather/{place}    → Weather data (OpenWeatherMap)
├── /api/sentiment/{id}     → AI mood analysis (Groq)
├── /api/places/autocomplete → Location search (Google)
├── /api/chat               → AI travel assistant (Groq)
├── /api/map/markers        → Map pin data
└── /api/unsplash/{query}   → Photo fetch (Unsplash)
```
