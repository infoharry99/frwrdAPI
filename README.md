# frwrdAPI

FRWRD Tutors Backend REST API built with **Laravel 10**, MySQL, and integrations with **TutorCruncher CRM**, **Network International (N-Genius Payment Gateway)**, and **Stripe**.

## Features

- **Authentication & User Management**: Client login (Bcrypt), password reset, profile management, and multi-student management.
- **Tutors & Booking Engine**: Synchronized with TutorCruncher CRM; handles individual & group lesson bookings across custom package tiers (Silver, Gold, Platinum).
- **Payment Processing**: N-Genius gateway order creation and server-side payment fulfillment (`PaymentFulfillmentService`).
- **Multi-Branch Architecture**: Separate dashboards, cache tables, and reporting for **Branch 1017** and **Branch 28866**.
- **Automated Schedulers**: Artisan console commands replacing all legacy cron jobs (package expiry reminders, session countdowns, tutor/student/appointment/invoice syncs).

---

## Setup & Installation

### 1. Clone & Install Dependencies
```bash
git clone https://github.com/infoharry99/frwrdAPI.git
cd frwrdAPI
composer install
```

### 2. Environment Configuration
Copy `.env.example` to `.env` and fill in the database and API credentials:
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Run the Development Server
```bash
php artisan serve --port=3000
```

### 4. Background Task Scheduler
To run scheduled background tasks (package expiry reminders and data syncs):
```bash
php artisan schedule:work
```

---

## Testing

Run the feature test suite:
```bash
php artisan test
```
