# Drug and Vaccine Alert System

Web-based drug and vaccine availability alert system for patients in Kenya, using a centralized database.

## Roles and what each can do

| Role | Dashboard | Tasks |
|---|---|---|
| Patient | `patient/` | Register, log in, search drugs/vaccines, see which facilities have stock and how to contact them, request an alert for out-of-stock items, view/cancel alert history |
| Facility staff (pharmacy / health facility) | `facility/` | Register a facility account, update inventory (quantity, restocked date, supplier), see patient demand; updating stock from 0 to above 0 alerts waiting patients automatically |
| Admin | `admin/` | Verify facility accounts, manage user accounts (activate/deactivate), manage the drug catalog, view system reports |

Facilities must be verified by an admin before their staff can update stock, and only verified facilities appear in patient search results.

## Run it locally (XAMPP)

1. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Put this folder at `C:\xampp\htdocs\drug_alert`.
3. Open `http://localhost/phpmyadmin` and click the **SQL** tab:
   - **New install:** paste the contents of `database.sql` and click **Go**.
   - **Already ran the first version:** select the `drug_alert` database, paste `upgrade_roles.sql` and click **Go** (run it once).
4. Open `http://localhost/drug_alert/register.php`.

## Create the first administrator

Register a normal account, then in phpMyAdmin (SQL tab, `drug_alert` selected) run:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

Log out and log in again to reach the admin dashboard.

## Try the whole flow

1. Register a **facility** account (choose "Pharmacy / Health facility").
2. Register a **patient** account in another browser or a private window.
3. As admin, open **Facilities** and verify the facility.
4. As the patient, search for a drug (e.g. BCG), choose **Notify me when available**.
5. As the facility, open **Inventory** and set that drug's quantity above 0 - the patient is alerted.
6. As the patient, open **My Alerts** to see the facility and contact details.

## Before deploying

- Set `DEV_MODE` to `false` in `config.php` and send reset emails with PHPMailer over SMTP.
- Set a fixed `BASE_URL` in `config.php` (https address).
- Change the MySQL `root` user and empty password in `config.php`.
- Use HTTPS.
- SMS / email delivery of alerts (the `notifications.channel` column already supports `sms` and `email`) is not built yet; alerts are currently in-app.
