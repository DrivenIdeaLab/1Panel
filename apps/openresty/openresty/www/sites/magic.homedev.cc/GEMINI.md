
# GEMINI.md

## Project Overview

This project is a Laravel-based web application called **MagicAI**. It provides a suite of AI-powered tools for content creation, including text generation, image generation, and voiceovers. The application also features a complete user management system, a subscription-based payment model, an affiliate program, and a support ticketing system. It appears to be a multi-tenant SaaS application.

The backend is built with Laravel, and the frontend uses a mix of Blade templates, Livewire, Alpine.js, and Tailwind CSS. It uses Vite for asset bundling.

### Key Technologies

*   **Backend:** Laravel, PHP
*   **Frontend:** Blade, Livewire, Alpine.js, Tailwind CSS, Vite
*   **Database:** (Not explicitly specified, but likely MySQL, as is common with Laravel)
*   **AI Integrations:** OpenAI (or compatible) for text and image generation, and other AI services.

## Building and Running

### Prerequisites

*   PHP 8.2 or higher
*   Composer
*   Node.js and npm

### Installation

1.  **Install PHP dependencies:**
    ```bash
    composer install
    ```

2.  **Install frontend dependencies:**
    ```bash
    npm install
    ```

3.  **Create the environment file:**
    ```bash
    cp .env.example .env
    ```

4.  **Generate an application key:**
    ```bash
    php artisan key:generate
    ```

5.  **Configure your `.env` file** with your database credentials and any other necessary settings (e.g., API keys for AI services).

6.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```

### Development

*   **Start the development server:**
    ```bash
    npm run dev
    ```
    This will start the Vite development server.

*   **Run the Laravel development server:**
    ```bash
    php artisan serve
    ```

### Building for Production

```bash
npm run build
```

This will build the frontend assets for production.

### Testing

*   **Run the test suite:**
    ```bash
    composer test
    ```

*   **Run the linter:**
    ```bash
    composer lint
    ```

## Development Conventions

*   **Coding Style:** The project uses `laravel/pint` for code style. Use `composer lint` to check for and fix any style issues.
*   **Database Migrations:** Database schema changes should be made through migrations.
*   **Frontend:** The project uses Tailwind CSS for styling. Adhere to the existing design system and utility-first approach.
*   **API:** The API is versioned and follows RESTful conventions. All API routes are defined in `routes/api.php`.
*   **Extensibility:** The application has a built-in extension system. New features can be added as extensions.
