# GracePoint Church Login & Role-Based Access System

A PHP + MySQL church portal authentication demo designed for Visual Studio Code and XAMPP.

## Three security requirements
1. **Identification:** unique username or email.
2. **Authentication (2FA demonstration):** password hash verification, then a short-lived six-digit one-time code.
3. **Authorization:** three roles with different permissions:
   - **Admin:** dashboard, leadership summaries, create church user accounts and assign roles.
   - **Pastor:** dashboard and leadership summaries.
   - **Church Secretary:** standard church staff dashboard.

## Open the app later on this computer

The project is currently in `C:\Users\DEVICES\Desktop\simple login system\church_login_system`.
The local PHP development server is not permanent: start it again whenever you want to
use the app.

1. Make sure the XAMPP MySQL service is running. The app's default database settings
   are in `config.php` (`root` with a blank password).
2. Open PowerShell and run:

   ```powershell
   & 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t 'C:\Users\DEVICES\Desktop\simple login system\church_login_system'
   ```

   Keep this PowerShell window open while using the app. To stop the server, press
   `Ctrl+C` in that window.
3. Open this URL in your browser:

   **http://127.0.0.1:8000/index.php**

   This address works on this computer while the PHP server is running. It is not a
   public website URL.
4. The database has already been initialized on this computer. On a fresh database,
   open **http://127.0.0.1:8000/setup.php** once before signing in. Setup creates the
   database and demo accounts.

After entering your password, enter the six-digit code displayed on the verification
page. A new code is generated for every sign-in and expires after five minutes; there
is no permanent verification code. In development mode the code appears on screen
because email delivery has not been configured.

## Demo accounts

| Role | Username | Email | Password |
|---|---|---|---|
| Admin | `admin` | `admin@gracepoint.test` | `Admin123!` |
| Pastor | `pastor` | `pastor@gracepoint.test` | `Pastor123!` |
| Church Secretary | `secretary` | `secretary@gracepoint.test` | `Secretary123!` |

You can sign in using either the username or email with the matching password.
Admins can open **User management** to create additional accounts. Pastors and
Church Secretaries cannot access that page.

## Security notes
This is a classroom demo, not a production-ready church system. With email delivery not configured, the second-factor code is shown on screen in development mode. To deploy:
- Integrate an authenticated SMTP service (e.g. PHPMailer) and send codes to the account's registered email.
- Change `APP_ENV` to `production` only after real email delivery works.
- Enable HTTPS and secure cookie settings, change demo passwords, and remove/restrict `setup.php`.
- Add password reset, account lockout monitoring, audit logs, and tests.
- Add member/attendance/finance modules separately with least-privilege permissions.
