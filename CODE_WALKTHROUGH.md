# Rehletna — Codebase Walkthrough & Architecture Guide

This document provides a detailed breakdown of every major file, class, method, and function in the Rehletna Symfony application. Use this guide to understand how the application works under the hood so you can safely modify or extend it.

---

## 1. Controllers (`src/Controller/`)

Controllers are the entry points for all HTTP requests. They receive the user's request, interact with the database or services, and return a response (usually rendering a Twig template or returning JSON).

### `PostController.php`
Handles all front-office views related to the main feed and publications.
- **`feed(Request, ...)`**: Route `/` (Home). Fetches approved posts (either all or matching a search query). Calculates like/comment counts and user "liked" status for each post. Renders `front/feed.html.twig`.
- **`detail(int $id, ...)`**: Route `/post/{id}`. Fetches a single post, its comments, and metadata. Renders `front/post_detail.html.twig`.
- **`new(Request, ...)`**: Route `/post/new`. Handles the creation of a new post. If an image is uploaded, it uses `ImageUploadService`. If an agency is selected, the post is set to `PENDING` status. Renders `front/create_post.html.twig`.
- **`edit(int $id, Request, ...)`**: Route `/post/{id}/edit`. Allows editing an existing post's text and location, and optionally replacing the image.
- **`delete(int $id, ...)`**: Route `/post/{id}/delete`. Removes a post from the database.

### `ApiController.php`
Handles all asynchronous (AJAX/Fetch) requests from the frontend using JavaScript. Returns JSON instead of HTML.
- **`toggleLike(...)`**: Route `/api/like/toggle`. Adds or removes a "Like" for the current user on a specific post.
- **`addComment(...)`**: Route `/api/comment`. Creates a new comment on a post and returns the newly created comment data so JS can append it to the page without reloading.
- **`editComment(...)`**: Route `/api/comment/{id}`. Updates an existing comment's text.
- **`deleteComment(...)`**: Route `/api/comment/{id}`. Deletes a comment.
- **`weather(string $place, ...)`**: Route `/api/weather/{place}`. Calls the OpenWeatherMap API via `WeatherService` and returns the weather data.
- **`placesAutocomplete(...)`**: Route `/api/places/autocomplete`. Calls `PlacesAutocompleteService` to suggest locations as the user types in the "Location" field.
- **`chat(...)`**: Route `/api/chat`. Handles the "Ask Rihla" AI assistant. Stores conversation history in the session and calls `AiChatService`.
- **`chatClear(...)`**: Route `/api/chat/clear`. Clears the Rihla AI chat history from the session.
- **`unsplash(...)`**: Route `/api/unsplash/{query}`. Fetches a travel photo from Unsplash based on a location.

### `AgencyController.php`
Handles the back-office logic for travel agencies (moderation portal).
- **`login(...)`**: Route `/agency/login`. Uses `AgencyRepository` to verify email/password. Stores the agency ID in the session upon success.
- **`dashboard(...)`**: Route `/agency/dashboard`. The main moderation panel. Fetches posts assigned to the logged-in agency and allows filtering by `PENDING`, `APPROVED`, or `REJECTED`.
- **`approve(int $id, ...)`**: Route `/agency/post/{id}/approve`. Changes a post's status to `APPROVED`.
- **`reject(int $id, ...)`**: Route `/agency/post/{id}/reject`. Changes a post's status to `REJECTED`.
- **`logout(...)`**: Route `/agency/logout`. Clears the agency session data.

### `MapController.php`
Handles the interactive Map Explorer.
- **`index()`**: Route `/map`. Renders the base map template.
- **`markers(...)`**: Route `/api/map/markers`. Fetches all approved posts that have a location (`place`), converts the place names to GPS coordinates using `GeocodingService`, and returns them as a JSON array for Leaflet.js to draw pins.

---

## 2. Services (`src/Service/`)

Services contain complex business logic or logic that communicates with external APIs. Keeping this out of the Controllers keeps the code clean and reusable.

### `WeatherService.php`
- **Purpose**: Fetches current weather for a specific location.
- **Method `getWeatherByLocation(string $locationName)`**: Calls OpenWeatherMap API using `OPENWEATHERMAP_API_KEY` from `.env`.
- **Caching**: Temporarily saves (caches) results for 30 minutes to avoid hitting API rate limits.
- **Method `getEmoji(...)`**: Helper that maps weather types (Clear, Rain, Snow) to emojis (☀️, 🌧️, ❄️).

### `AiChatService.php`
- **Purpose**: Powers the "Rihla" AI travel assistant.
- **Method `chat(array $history)`**: Sends the chat history along with a highly specific "System Prompt" (defining Rihla's personality and rules) to the Groq API (using the Llama 3.3 model).

### `ImageUploadService.php`
- **Purpose**: Handles file uploads for post images.
- **Method `upload(UploadedFile $file)`**: Validates that the file is an image (jpg, png, webp) and under 5MB. Generates a unique secure filename and moves it to `public/uploads/images/`.

### `GeocodingService.php` & `PlacesAutocompleteService.php`
- **Purpose**: Interacts with the free Nominatim OpenStreetMap API.
- **`geocode($placeName)`**: Converts a text location ("Tunis, Tunisia") into Latitude and Longitude for the Map.
- **`getAutocompletePredictions($input)`**: Returns suggested places as the user types in the "Add Location" input block. Enforces a 1-second rate limit to prevent getting blocked by the free API.

### `UnsplashService.php`
- **Purpose**: Fetches high-quality background images for the Post Detail view.
- **Method `fetchPhoto($locationQuery)`**: Searches Unsplash for a landscape photo matching the location name to make the UI look premium.

---

## 3. Entities & Repositories (`src/Entity/` & `src/Repository/`)

Entities represent your database tables. Repositories contain the SQL/DQL queries to interact with those tables.

### `Publication.php` & `PublicationRepository.php`
- **Fields**: `id`, `content`, `datePublication`, `imagePath`, `place`, `agencyId`, `status`.
- **Relationships**: A Publication belongs to one `Client`, and has many `Comments` and `Likes`.
- **Repository Methods**:
  - `findAllApproved()`: For the main feed.
  - `findByAgency($id)`: For the agency dashboard.
  - `searchByKeyword($kw)`: For the search bar.

### `Comment.php` & `Like.php`
- **Relationships**: Both belong to a `Publication` and a `Client`.
- **Repositories**: `countByPublication($id)` is heavily used to show the number of likes/comments without loading the actual records.

### `Client.php` & `Agency.php`
- **Client**: Represents front-office users (Travelers). Currently acts as a mocked user (`client_id = 1`) since full Front-Office auth wasn't part of the scope.
- **Agency**: Represents back-office users. Contains `email` and `password` for login.

---

## 4. Twig Templates (`templates/`)

Symfony uses Twig to generate HTML. 

- **`base.html.twig`**: The absolute foundation containing the `<head>`, CSS links, and JS script tags.
- **`front/layout.html.twig`**: The main structure of the user-facing site (Sidebar, Top Nav with the 🌙 toggle, and the main content area).
- **`components/post_card.html.twig`**: The reusable HTML block for a single post on the grid feed. Determines if the "❤️ Liked" or "🤍 Like" button should show.
- **`front/post_detail.html.twig`**: The detailed view of a post, including the weather widget, dynamic Unsplash hero image, and comment section.
- **`agency/dashboard.html.twig`**: The table view for approving/rejecting submitted posts.

---

## 5. Frontend Assets (`public/`)

Anything in `public/` is directly accessible by the browser.

### `css/app.css`
Contains ALL styling. It is built heavily on CSS Variables (`:root`) to allow seamless switching between Light and Dark mode. 
- Search for `[data-theme="dark"]` to see exactly what colors change in dark mode.
- Includes recent additions like the `.lightbox-overlay` for image zooming.

### `js/app.js`
Contains ALL frontend interactivity written in Vanilla JavaScript.
- **Theme Toggling**: `toggleTheme()` updates CSS variables and saves preference to `localStorage`, also updates the `🌙` / `☀️` emojis.
- **AJAX Likes**: Intercepts clicks on the "Like" buttons, sends a `fetch()` request to `/api/like/toggle`, and updates the UI instantly.
- **Comments**: Handles submitting the comment form via AJAX and inserting the new HTML block dynamically.
- **Rihla AI Chat**: Manages the open/close state of the chat FAB (Floating Action Button) and appends user/bot messages.
- **Lightbox**: Intercepts clicks on `.post-image`, prevents navigation, and displays the full-screen zoom overlay.
