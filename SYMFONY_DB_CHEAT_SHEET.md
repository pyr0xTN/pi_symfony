# Symfony & Database Cheat Sheet

Here are the most essential commands you need to manage the database, test your code, and run your Symfony application. Run all of these from inside your `rehletna-symfony` folder.

---

## 🚀 1. Running the Project

**Start the PHP Development Server**
```bash
php -S localhost:8000 -t public
```
*(Leave this running in a terminal. Your app will be accessible at `http://localhost:8000`)*

**Clear the Cache**
```bash
php bin/console cache:clear
```
> **IMPORTANT:** You must run this command whenever you change:
> - The `.env` file
> - `config/services.yaml`
> - Controller routing (`#[Route(...)]`)
> - Adding/Removing PHP files

---

## 🗄️ 2. Database Management (Doctrine)

Symfony uses Doctrine ORM to manage the database. Whenever you change a PHP class in `src/Entity/` (like adding a new property), you need to update the database to match.

**Step A: Check if your code matches the DB**
```bash
php bin/console doctrine:schema:validate
```
*(This tells you if your PHP Entities are out of sync with your MySQL tables).*

**Step B: Preview the SQL changes (Safe)**
```bash
php bin/console doctrine:schema:update --dump-sql
```
*(This shows you the exact `ALTER TABLE` SQL commands it plans to run. Always do this to make sure it's not going to delete a column by mistake).*

**Step C: Apply the changes to the Database**
```bash
php bin/console doctrine:schema:update --force
```
*(This executes the SQL and updates your MySQL database schema directly).*

**Creating the DB from Scratch (If it doesn't exist)**
```bash
php bin/console doctrine:database:create
```

---

## 🛠️ 3. Testing & Interacting with the DB Manually

You don't always need to open PhpMyAdmin to check your data. You can query the DB directly from the terminal.

**Run a raw SQL query**
```bash
php bin/console doctrine:query:sql "SELECT * FROM publication LIMIT 3"
```

**Run a Doctrine DQL query (Using your Entity objects)**
```bash
php bin/console doctrine:query:dql "SELECT p.id, p.content, p.status FROM App\Entity\Publication p ORDER BY p.id DESC"
```

---

## 🔧 4. Generating New Code

Symfony can automatically generate boilerplate code for you if you want to add new features.

**Create a new Entity (Database Table)**
```bash
php bin/console make:entity MyNewTable
```
*(It will ask you interactively what columns you want to add. Once done, remember to run `doctrine:schema:update --force`!)*

**Create a new Controller (Web Page/API)**
```bash
php bin/console make:controller MyNewController
```

**Generate a full CRUD (Create, Read, Update, Delete) UI for an Entity**
```bash
php bin/console make:crud Publication
```

---

## 🔎 5. Debugging routing & Twig

If you get a 404 error, or a Twig error, use these:

**List all available URLs (Routes)**
```bash
php bin/console debug:router
```

**Check a Twig template for syntax errors before reloading the page**
```bash
php bin/console lint:twig templates/
```

**Find a specific service**
```bash
php bin/console debug:autowiring
```
