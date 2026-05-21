# 📚 FSUU Noguchi Library — DigiScan Book Management System

DigiScan is the official **Digital Scanned Book Management System** for the **Father Saturnino Urios University (FSUU) Noguchi Library**. Designed to preserve, manage, and distribute institutional literature, historical archives, and Filipiniana collections, it serves both as an online catalog browser and a secure digital previewing platform.

---

## 🏛️ Background & History

The **Noguchi Library** was officially opened on **June 6, 2013** at Father Saturnino Urios University. The library was named in honor of **Prof. Takashi Noguchi**, a Japanese national, researcher, and retired faculty member of Teikyo University. 

Throughout his scholarly career, Prof. Noguchi focused heavily on Philippine administration and local histories. Wishing to pass this knowledge down to future generations, he donated his entire collection of Filipiniana literature, rare documents, and research materials to FSUU. Today, this collection serves as the foundation of the Noguchi Library.

*   **Current Catalog Size:** 2,379+ Titles | 3,766+ Volumes
*   **Special Collections:** Rare Filipiniana books, political and historical records, Japanese language/culture handbooks, and rare sets such as *Documentary Sources of Philippine History* (Gregorio F. Zaide) and *The Philippine Islands* (Blair & Robertson).

---

## 🚀 Key System Features

The DigiScan system contains two main portals: the **Student Portal** and the **Administrative Dashboard**.

### 1. Catalog & Bibliographic Management (RDA Standards)
Unlike basic libraries, DigiScan incorporates modern cataloging features aligned with **Resource Description and Access (RDA)** standards:
*   **Detailed Metadata:** Tracks Edition, Publisher, Publication Place, and Publication Date.
*   **RDA Classifications:** Cataloging includes *Content Type*, *Media Type*, *Carrier Type*, and *Extent*.
*   **Dewey Decimal System:** Built-in classification mapping using Dewey numbers.
*   **Multi-Cover Carousel:** Upload and display up to 5 cover images per book (saved in a JSON database schema).

### 2. Interactive Digital Readers
Students can read materials directly in the web browser through two viewing engines:
*   **Heyzine Flipbook Integration:** Interactive, smooth, double-page flipbooks loaded securely via Heyzine embeds.
*   **HTML5/CSS3 Flipbook Engine:** A custom-engineered, lightweight CSS transition flipbook for local PDF previews.
*   **Preview Limitations:** Administrators can restrict online access to a custom number of preview pages (e.g. 10 pages) to protect publisher copyright.

### 3. Institutional Security & Copy Protection
To protect high-value intellectual property and prevent unauthorized downloads, the system features a **Secure Preview Mode**:
*   🚫 **Right-Click Restriction:** Context menus are disabled system-wide on reader views.
*   🚫 **Keyboard Event Blocking:** Disables shortcut keys for Inspect Element (`Ctrl+Shift+I` / `F12`), View Source (`Ctrl+U`), Print Document (`Ctrl+P`), Page Saving (`Ctrl+S`), and Copying (`Ctrl+C`).
*   🚫 **Toolbar Removal:** Web PDFs are served with stripped controls (`#toolbar=0&navpanes=0&scrollbar=0`) to prevent print/save actions.

### 4. Physical Copy Reservation System
Since full-text reading requires physical access, students can place reservations:
*   **Reservation Policy:** Reserves the physical book copy for **3 days**.
*   **Auto-Expiration:** If the student does not pick up the material within 3 days, the reservation automatically expires.
*   **Admin Approval Workflow:** Administrative staff can approve, mark as picked up, cancel, or track reservations.

### 5. Automated Reminders & Overdue Tracking
An automated cron processor calculates due dates and borrowing rules:
*   **Upcoming Alerts:** Automatically notifies users 1–2 days before their borrowing deadline.
*   **Overdue Flags:** Automatically transitions expired accounts to "Overdue" status and suspends certain borrowing rights.
*   **Spam Protection:** Restricts overdue notifications to a single alert per book to keep student dashboards clean.

---

## 💻 Tech Stack & Architecture

*   **Backend:** PHP 8.x (Procedural/OOP architecture)
*   **Database:** MySQL (Structured Relational Schema)
*   **Frontend:** HTML5, Vanilla CSS3 (Custom design, grid systems, and glassmorphism cards), FontAwesome 6, Google Fonts (*Outfit* family)
*   **Server Environment:** Optimized for Apache running under XAMPP environments
*   **Dependencies:** FontAwesome (via CDN), Google Fonts

---

## 🗄️ Database Schema Overview

The database (`library_system`) consists of 9 core tables:

| Table | Purpose |
| :--- | :--- |
| `roles` | Defines access privileges: `1` for Admin, `2` for Student. |
| `users` | Contains user profiles, email hashes, role keys, and admin activation states. |
| `books` | Stores metadata (Dewey codes, catalog info, file paths, preview pages, RDA values, Heyzine URLs). |
| `borrowings` | Handles physical transaction history, return dates, overdue status, and reminder state flags. |
| `reservations` | Stores pending, approved, picked-up, and expired reservation states for students. |
| `notifications` | Stores dashboard notifications (alerts, reminders, success cards) for students. |
| `reading_logs` | Logs online reading sessions for analytics and access checks. |
| `system_logs` | Audit trail mapping administrator actions, details, timestamps, and IP addresses. |
| `system_settings` | Holds configuration variables (`download_prevention`, `max_preview_pages`, etc.). |

---

## 🛠️ Installation & Configuration

### Prerequisites
*   **XAMPP Control Panel** (with Apache and MySQL enabled).
*   PHP 7.4 or newer.

### Step 1: Clone or Place the Files
Extract the source code and place it inside your XAMPP server folder:
```bash
C:\xampp\htdocs\DIGISCAN-NOGUCHI-
```

### Step 2: Set Up the Database
1.  Open the **XAMPP Control Panel** and click **Start** next to Apache and MySQL.
2.  Open your browser and navigate to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3.  Create a new database named `library_system`.
4.  Import the SQL files from the `migration/` directory in this chronological order:
    1.  `full_migration_v1.sql` (Creates base tables and structures)
    2.  `rda_enhancement.sql` (Appends RDA cataloging columns)
    3.  `add_dewey_decimal_column.sql` (Appends Dewey Decimal codes)
    4.  `multi_cover_images.sql` (Expands covers database column)
    5.  `reservation_system_v1.sql` (Builds reservations module)
    6.  `notifications_module.sql` (Builds notifications tracker)
    7.  `preview_system_update.sql` (Builds reading history and locations)
    8.  `reminder_tracking.sql` (Tracks due reminders)
    9.  `update_settings.sql` (Sets default configurations)

### Step 3: Configure Database Connection
Edit the file `config/db.php` if you have custom MySQL credentials:
```php
$host = "localhost";
$username = "root";
$password = ""; // Your MySQL password (default is empty in XAMPP)
$database = "library_system";
```

### Step 4: Access the System
*   **Student Portal / Landing page:** Navigate to [http://localhost/DIGISCAN-NOGUCHI-/](http://localhost/DIGISCAN-NOGUCHI-/)
*   **Login page:** [http://localhost/DIGISCAN-NOGUCHI-/registration/login.php](http://localhost/DIGISCAN-NOGUCHI-/registration/login.php)
*   **Register page:** [http://localhost/DIGISCAN-NOGUCHI-/registration/register.php](http://localhost/DIGISCAN-NOGUCHI-/registration/register.php)

---

## ⏰ Automated Task Configuration (Cron Reminders)

To run the due date monitoring automatically, schedule the `admin/cron_reminders.php` file on your server.

### Windows (Task Scheduler)
Create a batch script (`run_cron.bat`) containing:
```bat
"C:\xampp\php\php.exe" -f "C:\xampp\htdocs\DIGISCAN-NOGUCHI-\admin\cron_reminders.php"
```
Schedule this batch script to run once daily via **Windows Task Scheduler**.

### Linux (if deployed to Apache/Linux Server)
Add the following entry to your `crontab`:
```bash
0 0 * * * php /var/www/html/DIGISCAN-NOGUCHI-/admin/cron_reminders.php >/dev/null 2>&1
```

---

## 🔒 Security Disclaimer
The copy prevention functions (JavaScript print block, disable copy, disable right-click) are designed as standard institutional deterrents. Always make sure digital resource PDFs are kept in protected folders with appropriate `.htaccess` parameters to prevent direct URL scraping.
