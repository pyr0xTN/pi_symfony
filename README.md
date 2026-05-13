# Rehletna 🌍✈️

Rehletna (رحلتنا) is a comprehensive, AI-powered travel and tourism management platform built with Symfony 6.4. It offers a complete ecosystem for travelers to discover, plan, book, and share their travel experiences, while providing robust administration tools for platform managers.

## 🚀 Key Features

*   **Intelligent Travel Planning:** Leverage advanced AI (powered by Groq, Gemini, and Anthropic) for personalized itinerary generation, smart activity suggestions, and an integrated AI site assistant.
*   **Comprehensive Booking System:** Search and book flights (AviationStack), hotels (Makcorps API), and local activities. Seamless checkout experience with Stripe and PayPal integrations.
*   **Social & Community (Posts Module):** Share travel moments through a vibrant community feed. Features include posts, likes, comments, and AI-driven sentiment analysis. Export personal travel journals to PDF.
*   **Real-Time Messaging:** Instant peer-to-peer and group conversations built on Symfony Mercure for real-time WebSockets communication.
*   **Interactive Maps & Radar:** Discover nearby attractions, accommodations, and fellow travelers using integrated interactive maps and location-based radar.
*   **Dynamic Admin Dashboard:** A powerful back-office built with EasyAdmin and UX ChartJS to manage users, bookings, offers, and platform analytics.
*   **Multi-Currency Support:** Real-time exchange rates for global pricing transparency (ExchangeRate API).
*   **Secure Authentication:** Standard login paired with seamless Google OAuth integration.

## 🛠️ Technology Stack

*   **Framework:** Symfony 6.4 (PHP 8.2+)
*   **ORM / Database:** Doctrine ORM / MariaDB (MySQL)
*   **Frontend:** Twig, Symfony UX (Turbo, Components, ChartJS), Bootstrap/Tailwind (Custom styling), Stimulus.js
*   **Real-time:** Symfony Mercure
*   **PDF Generation:** KnpSnappyBundle (wkhtmltopdf)
*   **APIs & Services:**
    *   **AI:** Groq API, Anthropic Claude, Google Gemini, OpenAI
    *   **Payments:** Stripe, PayPal
    *   **Travel Data:** AviationStack (Flights), Makcorps (Hotels), OpenWeatherMap
    *   **Utilities:** ExchangeRate API, Unsplash API, MailerSend (SMTP)

## ⚙️ Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/douaJlassi/PiDev.git
    cd rehletna-symfony
    ```

2.  **Install Dependencies:**
    ```bash
    composer install
    ```

3.  **Environment Variables:**
    Duplicate `.env` to `.env.local` and populate your specific API keys:
    ```env
    DATABASE_URL="mysql://user:password@127.0.0.1:3306/pi?serverVersion=10.4.32-MariaDB&charset=utf8mb4"
    MERCURE_JWT_SECRET="your_mercure_secret"
    
    # Fill in the respective API keys
    GROQ_API_KEY=
    STRIPE_SECRET_KEY=
    PAYPAL_CLIENT_ID=
    OPENWEATHERMAP_API_KEY=
    AVIATIONSTACK_API_KEY=
    # ... other keys
    ```

4.  **Database Setup:**
    ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    # (Optional) Load fixtures if available
    # php bin/console doctrine:fixtures:load
    ```

5.  **Run the application:**
    ```bash
    symfony server:start
    # Start Mercure hub for real-time features
    # Start Messenger workers if using async processing
    php bin/console messenger:consume
    ```

## 📂 Project Structure Highlights

*   `src/Controller/`: Contains all the application logic categorized by feature modules (e.g., `Activities`, `Ai`, `Messages`, `Post`, `Admin`).
*   `templates/`: Twig templates organized by module (`front`, `admin`, `components`).
*   `src/Service/`: Business logic and external API integrations (e.g., `CommentManager`, Payment Services, AI Services).

## 🎓 Academic Context

This project was developed as a comprehensive PiDev (Projet Intégré) for our university (ESPRIT - Tunisia) curriculum, demonstrating proficiency in modern web architecture, third-party API integration, artificial intelligence in web applications, and collaborative software engineering.

---
*Crafted with ❤️ by the Rehletna Team.*
