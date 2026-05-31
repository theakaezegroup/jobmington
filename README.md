<p align="center">
  <img src="assets/images/badge.png" alt="Jobmington" width="200">
</p>

<h1 align="center">Jobmington</h1>

<p align="center">
  <strong>Simple hiring for African talent.</strong><br>
  Find work that fits. Hire talent that moves.
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#tech-stack">Tech Stack</a> •
  <a href="#getting-started">Getting Started</a> •
  <a href="#project-structure">Project Structure</a> •
  <a href="#configuration">Configuration</a> •
  <a href="#deployment">Deployment</a> •
  <a href="#license">License</a>
</p>

---

## About

Jobmington is a job marketplace built for the African workforce. It connects job seekers with employers through a clean, focused platform that strips away the noise of traditional job boards.

- **Job seekers** can search and filter jobs, build professional CVs, apply quickly, and track their applications — all from one workspace.
- **Employers** can post roles, manage applications, review candidates, and pay for premium visibility when needed.
- **Admins** can monitor platform health, manage content, and oversee operations from a centralized dashboard.

---

## Features

### 🔍 Job Marketplace
- Advanced search with keyword, category, country, and job type filters
- Featured and curated job listings
- Job detail pages with company info, salary, requirements, and benefits
- Save jobs for later and track application history
- Automated job importing from external sources (We Work Remotely, RemoteOK)

### 📄 CV Studio
- Full CV builder with guided editor
- Multiple professional templates — Executive, Modern, and Technical
- Import from PDF, DOCX, or LinkedIn ZIP export
- AI-powered CV parsing via Google Gemini
- Browser-based A4 export for print-ready resumes
- CV completion tracking and readiness scoring

### 🤖 AI-Powered Tools
- **Andika AI** — Writing assistant for cover letters and professional content
- **Job Match Coach** — Skill-based matching that scores fit and suggests improvements
- **Interview Prep** — Generates likely questions, preparation notes, and practice prompts
- **CV Roast** — Candid AI feedback on your resume

### 🏢 Employer Dashboard
- Company profile management
- Job posting with flexible visibility packages
- Application tracking with status pipeline (pending → reviewed → shortlisted → interview → hired)
- Candidate detail view with CV and cover notes
- Talent pool browsing

### 💳 Payments & Monetization
- Free tier for basic job posts (30-day listing)
- Paid plans for featured visibility (45–60 day listings)
- Paystack payment integration (optimized for African markets)
- Internal Seeds credit system for premium AI tools

### 🎓 Learning Academy
- Course catalog with enrollment and progress tracking
- Module-based lessons with quizzes
- Certificate generation upon completion
- Paystack-powered course purchases

### 🏅 Gamification
- Achievement badges (Bronze → Silver → Gold → Platinum)
- Talent Passport verification system
- Profile completion incentives

### 💬 Community
- Discussion forum with topics and threaded replies
- Blog with categories and posts

### 🛡️ Security
- Argon2id password hashing
- CSRF protection on all forms
- Rate limiting and login lockout
- Secure session handling (HTTP-only, SameSite Strict)
- File upload validation with MIME checks

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| **Backend** | PHP 8.2+ (no framework) |
| **Database** | MySQL / MariaDB via PDO |
| **Frontend** | HTML, CSS, Vanilla JavaScript |
| **Payments** | Paystack API |
| **AI Services** | Google Gemini, OpenRouter, Groq |
| **Fonts** | Google Fonts (Satisfy) |
| **Server** | Apache (.htaccess) or Nginx |

---

## Getting Started

### Prerequisites

- **PHP 8.2+** with extensions: `pdo_mysql`, `fileinfo`, `curl`, `mbstring`, `json`, `zip`
- **MySQL 8.0+** or **MariaDB 10.6+**
- **Composer** (optional — no vendor dependencies currently)
- A local server: XAMPP, Laragon, WAMP, or PHP built-in server

### Installation

1. **Clone the repository**

   ```bash
   git clone git@github.com:theakaezegroup/jobmington.git
   cd jobmington
   ```

2. **Create your environment file**

   ```bash
   cp .env.example .env
   ```

   Edit `.env` with your database credentials and API keys.

3. **Create the database**

   ```sql
   CREATE DATABASE jobmington_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'jobmington_user'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON jobmington_db.* TO 'jobmington_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

4. **Run migrations**

   Open your browser and navigate to:
   ```
   http://localhost/jobmington/setup.php
   ```

   Or run the schema migration directly:
   ```bash
   php database/migrations/setup_full_schema.php
   ```

5. **Start the development server**

   Using PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```

   Or place the project in your web server's root directory (e.g., `htdocs/jobmington` for XAMPP).

6. **Open in browser**

   ```
   http://localhost:8000
   ```

   Or if using XAMPP/Apache:
   ```
   http://localhost/jobmington/
   ```

---

## Project Structure

```
jobmington/
├── admin/              # Admin dashboard and management panels
├── ai/                 # AI services (Andika, JobMatcher, CV Roast)
├── api/                # REST API endpoints
├── assets/
│   ├── css/            # Stylesheets
│   ├── js/             # Client-side JavaScript
│   └── images/         # Logos, icons, and static images
├── auth/               # Login, register, password reset, email verification
├── blog/               # Blog listing and article pages
├── certificates/       # Course completion certificate generation
├── community/          # Discussion forum
├── config/
│   ├── constants.php   # Site-wide constants and defaults
│   ├── database.php    # PDO database connection
│   └── env.php         # Environment variable loader
├── cron/               # Automated job scrapers and scheduled tasks
├── cv-builder/         # CV Studio (editor, templates, import, export)
├── database/
│   └── migrations/     # SQL and PHP migration scripts
├── employer/           # Employer dashboard, job posting, applications
├── errors/             # Custom error pages (403, 404, 500)
├── includes/
│   ├── functions.php   # Shared helper functions
│   ├── header.php      # Global header partial
│   ├── footer.php      # Global footer partial
│   ├── security.php    # Security utilities (hashing, CSRF, escaping)
│   ├── session.php     # Session management
│   ├── mailer.php      # Email delivery
│   ├── monetization.php# Job posting packages and pricing logic
│   ├── paystack.php    # Paystack API wrapper
│   └── seeds.php       # Internal credits system
├── jobs/               # Job search, browse, detail, apply, saved
├── learn/              # Learning academy (courses, modules, quizzes)
├── libs/               # Third-party libraries (FPDF)
├── payments/           # Payment flows and Paystack callbacks
├── seeker/             # Job seeker dashboard, profile, applications
├── uploads/            # User-uploaded files (avatars, CVs)
├── wallet/             # Wallet, transaction history, talent passports
├── .env.example        # Environment variable template
├── .htaccess           # Apache URL rewriting and security rules
├── index.php           # Homepage
└── pricing.php         # Employer pricing page
```

---

## Configuration

### Environment Variables

Copy `.env.example` to `.env` and configure:

| Variable | Description |
|----------|-------------|
| `DB_HOST` | Database host (default: `localhost`) |
| `DB_NAME` | Database name |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `APP_ENV` | `development` or `production` |
| `APP_DEBUG` | `true` or `false` |
| `APP_TIMEZONE` | Server timezone (default: `Africa/Lagos`) |
| `PAYSTACK_PUBLIC_KEY` | Paystack public key for client-side |
| `PAYSTACK_SECRET_KEY` | Paystack secret key for server-side |
| `PAYSTACK_CALLBACK_URL` | Payment verification callback URL |
| `GEMINI_API_KEY` | Google Gemini API key (for CV import parsing) |
| `OPENROUTER_API_KEY` | OpenRouter API key (for Andika AI) |
| `GROQ_API_KEY` | Groq API key (alternative AI provider) |
| `JOB_SCRAPER_LIMIT` | Max jobs to import per scraper run |

### Automated Job Importing

Jobmington can automatically import jobs from external sources:

```bash
# Import from all sources
php cron/run_job_scrapers.php --limit=80

# Import from specific source
php cron/run_job_scrapers.php --source=wwr --limit=60
php cron/run_job_scrapers.php --source=remoteok --limit=60
```

Set up a cron job for automated importing:

```cron
0 */6 * * * cd /path/to/jobmington && php cron/run_job_scrapers.php --limit=80 >> logs/scraper.log 2>&1
```

---

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
- [ ] Configure Paystack with live keys
- [ ] Set correct `PAYSTACK_CALLBACK_URL` with your production domain
- [ ] Ensure `.env` and `.env.local` are **not** publicly accessible
- [ ] Set up Nginx/Apache to deny access to `config/`, `database/`, `logs/`, and hidden files
- [ ] Configure SSL/TLS (HTTPS)
- [ ] Set up cron jobs for automated job importing
- [ ] Run all database migrations

### Nginx Configuration

The app expects to be served under the `/jobmington` path:

```nginx
location /jobmington {
    alias /var/www/jobmington;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }

    # Deny access to sensitive directories
    location ~ ^/jobmington/(config|database|logs|\.agent|\.env|\.git) {
        deny all;
        return 404;
    }
}
```

---

## User Roles

| Role | Access |
|------|--------|
| **Visitor** | Browse jobs, view details, pricing, public pages |
| **Job Seeker** | Dashboard, CV builder, apply to jobs, save jobs, track applications |
| **Employer** | Company profile, post jobs, manage applications, talent pool |
| **Admin** | Full platform management, KPIs, user/job/content moderation |

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## License

This project is proprietary software owned by The Akaeze Group. All rights reserved.

---

<p align="center">
  Built with ❤️ for African talent<br>
  <a href="https://jobmington.com">jobmington.com</a>
</p>
