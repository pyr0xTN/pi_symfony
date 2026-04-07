# Rehletna: Future Improvement Suggestions

These are a few high-impact ideas for taking the Rehletna application to the next level when you're ready to add new features.

---

### 👤 1. Real User Authentication & Profiles (High Priority)
Right now, the app works beautifully but has a "hardcoded" frontend user (`client_id = 1`). 
- **What to do:** Implement Symfony's robust `SecurityBundle` for travelers. Add a registration page, secure login with hashed passwords, and a "Profile Page" where a user can see a grid of only their own posts and a map of places they've visited.

### 📜 2. Infinite Scrolling or Pagination
Currently, the main feed loads every approved post from the database at once.
- **What to do:** If you get hundreds of posts, the page will eventually slow down. We could add **Infinite Scroll** (like Instagram) so it loads 10 posts at a time as you scroll down the page, making it lightning fast. A library like `KnpPaginatorBundle` paired with JavaScript intersection observers makes this fairly easy.

### 🔔 3. Real-Time Notification System
Users want to know when people engage with their content.
- **What to do:** Add a "🔔 Notifications" panel. When someone likes your post or comments on it, you get a notification. We could also notify travelers when an Agency approves or rejects their pending post.

### 🤖 4. Supercharging the "Rihla" AI
Rihla is currently a great conversationalist, but she only knows general travel knowledge.
- **What to do:** We can give Rihla "Tools" so she can actually read your MySQL database. Someone could ask her, *"Show me recent posts about Djerba,"* and she could fetch actual posts from your users and link to them in the chat. We can also save chat history to the database instead of the PHP session so it persists across logins.

### 📸 5. Multi-Image Uploads & Cropping
Currently, a post takes one image.
- **What to do:** Allow a "carousel" of up to 4 images per post. We could also add a quick Javascript cropping tool (e.g. `cropper.js`) so users can perfectly frame their landscape photos before hitting "Publish."

### 📝 6. Rich Text Mentions and Hashtags
- **What to do:** Allow users to type `@username` to notify another traveler, or use `#hashtags` (e.g. `#Sahara`) to automatically link to a filtered view of the feed showing all posts sharing that tag.
