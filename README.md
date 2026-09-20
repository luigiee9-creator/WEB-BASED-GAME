# 🇵🇭 Philippine History Memory Card Game

A complete LAMP/XAMPP full-stack educational web application where students flip cards, match historical pairs, and answer quiz questions covering 5 periods of Philippine history.

---

## 📁 File Structure

```
MOR/
├── index.php            ← Landing page / auth router
├── login.php            ← Student & admin login
├── register.php         ← Student registration
├── logout.php           ← Session destroy
├── dashboard.php        ← Student stats + difficulty picker
├── game.php             ← Playable game screen
├── install.php          ← ⚠️ One-time setup wizard (DELETE AFTER USE)
│
├── admin/
│   ├── _sidebar.php     ← Shared sidebar include
│   ├── index.php        ← Admin dashboard + stats
│   ├── cards.php        ← Card CRUD
│   ├── questions.php    ← Quiz question CRUD
│   ├── users.php        ← User management
│   ├── reports.php      ← Performance reports
│   └── export.php       ← CSV / Printable HTML export
│
├── api/
│   ├── game.php         ← Game session API (JSON)
│   ├── dashboard.php    ← Student stats API (JSON)
│   └── admin.php        ← Admin CRUD API (JSON)
│
├── assets/
│   ├── css/main.css     ← Global navy/gold theme
│   ├── css/game.css     ← Flip animations + HUD
│   ├── css/admin.css    ← Admin sidebar + tables
│   ├── js/main.js       ← Shared utilities
│   ├── js/game.js       ← Game engine (MemoryGame class)
│   └── js/admin.js      ← Admin CRUD helpers
│
├── includes/
│   ├── config.php       ← DB credentials + constants
│   ├── db.php           ← PDO singleton
│   ├── auth.php         ← Session / CSRF / roles
│   └── functions.php    ← Utilities + score calc
│
└── database/
    └── schema.sql       ← Full schema + 58 cards + 232 quiz answers
```

---

## 🚀 Quick Setup (XAMPP)

### Option A — Install Wizard (Recommended)

1. Copy the `MOR/` folder to `C:\xampp\htdocs\MOR\`
2. Start Apache and MySQL in the XAMPP Control Panel
3. Open your browser and go to:
   ```
   http://localhost/MOR/install.php
   ```
4. Enter your MySQL credentials and set the admin password
5. Click **Install Now** — the wizard creates the DB and all tables
6. **Delete `install.php` after installation**

### Option B — Manual Import

1. Copy `MOR/` to `C:\xampp\htdocs\MOR\`
2. Open phpMyAdmin → create database `ph_memory_game`
3. Import `database/schema.sql` (File → Import)
4. Edit `includes/config.php` with your MySQL credentials
5. Go to `http://localhost/MOR/`

---

## 🔑 Default Credentials

| Account | Username | Password |
|---------|----------|----------|
| Admin   | `admin`  | `password` *(change after first login!)* |
| Demo Student | `demo` | `password` |

> **Note:** The `schema.sql` seeds these accounts using a known bcrypt hash.  
> Use `install.php` to set a strong admin password.

---

## 🎮 How to Play

1. Register (or use demo account) → Log In
2. From the dashboard, pick **Easy** (4 pairs), **Medium** (8 pairs), or **Hard** (10 pairs)
3. Click cards to flip them — find matching pairs
4. After each match, answer the quiz question for bonus points
5. Finish all pairs to see your final score

### Scoring
| Action | Points |
|--------|--------|
| Pair matched | +10 |
| Correct quiz answer | +20 |
| Time bonus (max) | +100 |

---

## 🗄️ Database Schema (10 Tables)

| Table | Purpose |
|-------|---------|
| `users` | Students and admins with roles |
| `periods` | 5 historical periods |
| `cards` | 58 unique card types |
| `quiz_questions` | 58 quiz questions |
| `quiz_answers` | 232 answers (4 per question) |
| `game_sessions` | Active/completed game sessions |
| `session_matches` | Which cards were matched per session |
| `session_quiz_responses` | Student answer records |
| `performance_records` | Final score summary per session |

---

## 📚 Content Coverage

| Period | Cards | Level |
|--------|-------|-------|
| Spanish Colonization (1565–1898) | 20 cards | Easy (10), Medium (5), Hard (5) |
| Philippine Revolution (1896–1898) | 13 cards | Easy (5), Medium (5), Hard (3) |
| American Period (1898–1946) | 12 cards | Easy (5), Medium (5), Hard (2) |
| World War II (1941–1945) | 8 cards | Easy (5), Medium (3) |
| Contemporary Period (1946–present) | 5 cards | Easy (3), Medium (2) |

---

## 🛡️ Admin Panel Features

- **Cards** — Add, edit, delete, activate/deactivate cards
- **Questions** — Full CRUD for quiz questions with 4 answers each
- **Users** — View all students, toggle active, promote to admin, change passwords
- **Reports** — Filter by difficulty/date, view per-student stats
- **Export** — CSV download (Excel-compatible) + printable HTML for PDF

---

## ⚙️ Technical Notes

- **PHP 7.4+** required (uses `match`, named arguments)
- **MySQL 5.7+** required
- No Composer dependencies — runs on stock XAMPP
- CSRF tokens on all forms
- Passwords hashed with `password_hash()` / `PASSWORD_DEFAULT` (bcrypt)
- PDO prepared statements throughout — no SQL injection surface
- Fully responsive CSS (works on mobile)

---

## 🔧 Configuration

Edit `includes/config.php` to change:

```php
define('PAIRS_EASY',   4);   // cards per easy game
define('PAIRS_MEDIUM', 8);
define('PAIRS_HARD',   10);

define('SCORE_MATCH',        10);
define('SCORE_CORRECT_QUIZ', 20);
define('SCORE_TIME_BONUS',   100);
```

---

*Built for Filipino students as an educational history tool.*
