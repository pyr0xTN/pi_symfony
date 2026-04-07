# Rehletna — Symfony Commands Cheat Sheet

All commands should be run from inside `rehletna-symfony/`.

---

## Starting the Server

```bash
# Start the PHP development server (simplest option)
php -S localhost:8000 -t public

# Or use Symfony CLI if installed
symfony server:start
```

Visit `http://localhost:8000` after starting.

---

## Cache (Clear After Any Config/Template Change)

```bash
# Clear the cache (do this after editing .env, services.yaml, or any config file)
php bin/console cache:clear

# Warmup cache (optional, rebuilds everything)
php bin/console cache:warmup
```

> **When to use:** If you change anything in `config/`, `.env`, or add/remove a service class, always clear cache. Template (Twig) changes don't require this in dev mode.

---

## Database / Doctrine

```bash
# Check your database connection is working
php bin/console doctrine:database:create    # Only if DB doesn't exist yet

# Validate that your Entity classes match the database schema
php bin/console doctrine:schema:validate

# See the SQL that would bring the DB in sync with your entities (DRY RUN)
php bin/console doctrine:schema:update --dump-sql

# Actually apply schema changes to the database
php bin/console doctrine:schema:update --force

# Run a raw DQL query to test
php bin/console doctrine:query:dql "SELECT p FROM App\Entity\Publication p ORDER BY p.id DESC" --max-result=5

# Run a raw SQL query
php bin/console doctrine:query:sql "SELECT * FROM publication LIMIT 5"
```

> **When to use:** If you add a new column to an Entity (e.g., a new `#[ORM\Column]`), run `doctrine:schema:update --force` to add it to the database.

---

## Routing (Check What URLs Exist)

```bash
# List ALL routes in the application
php bin/console debug:router

# Search for a specific route
php bin/console debug:router --show-controllers

# Match a URL to see which controller handles it
php bin/console router:match /post/5
php bin/console router:match /api/like/toggle --method=POST
```

> **When to use:** After adding a new `#[Route(...)]` attribute, run `debug:router` to confirm it registered properly.

---

## Twig Templates (Lint & Debug)

```bash
# Check all templates for syntax errors
php bin/console lint:twig templates/

# Check a single template
php bin/console lint:twig templates/front/feed.html.twig

# See what variables/functions are available in Twig
php bin/console debug:twig
```

> **When to use:** After editing any `.html.twig` file, run `lint:twig` to catch typos before refreshing the browser.

---

## Service Container (Debug Wiring)

```bash
# Check that all services are wired correctly
php bin/console lint:container

# Find a specific service
php bin/console debug:container WeatherService
php bin/console debug:container --show-arguments App\Service\WeatherService

# See all autowired services
php bin/console debug:autowiring
```

> **When to use:** If you get "service not found" or constructor argument errors, use `debug:container` to see what Symfony knows about your service.

---

## Environment & Configuration

```bash
# See all environment variables Symfony knows about
php bin/console debug:dotenv

# See the value of a specific parameter
php bin/console debug:container --parameter=app.upload_dir

# See the full framework config
php bin/console debug:config framework
```

---

## Installing New Packages

```bash
# Install a new PHP package
composer require <package-name>

# Examples:
composer require symfony/mime              # File type detection
composer require symfony/mailer            # Email sending
composer require symfony/validator         # Form validation
composer require knplabs/knp-paginator-bundle  # Pagination

# Remove a package
composer remove <package-name>

# Update all packages
composer update
```

---

## Creating New Files (Maker Bundle)

```bash
# Install maker bundle first (if not installed)
composer require --dev symfony/maker-bundle

# Generate a new controller
php bin/console make:controller MyNewController

# Generate a new entity
php bin/console make:entity MyNewEntity

# Generate a CRUD controller for an entity
php bin/console make:crud Publication
```

---

## Quick Testing Workflow

After making changes, follow this order:

```
1. Save your files

2. If you changed config files or .env:
   php bin/console cache:clear

3. If you changed an Entity (added/removed a column):
   php bin/console doctrine:schema:update --dump-sql   # Preview
   php bin/console doctrine:schema:update --force       # Apply

4. If you changed a Twig template:
   php bin/console lint:twig templates/

5. If you added a new route:
   php bin/console debug:router | findstr "your_route"

6. If you added a new service:
   php bin/console lint:container

7. Refresh the browser and check the result
```

---

## Troubleshooting

| Problem | Command |
|---------|---------|
| Blank page or 500 error | `php bin/console cache:clear` then check `var/log/dev.log` |
| "Service not found" | `php bin/console debug:container YourService` |
| "Route not found" | `php bin/console debug:router` |
| "Table doesn't exist" | `php bin/console doctrine:schema:update --force` |
| "Class not found" | `composer dump-autoload` |
| Twig syntax error | `php bin/console lint:twig templates/` |
| See the full error trace | Open `http://localhost:8000` — Symfony dev mode shows the error page |

---

## Useful Log Files

```
var/log/dev.log    — Full application log (errors, queries, warnings)
```

Open it with any text editor or:
```bash
# Show last 50 lines
Get-Content var/log/dev.log -Tail 50

# Or on Linux/Mac:
tail -50 var/log/dev.log
```
