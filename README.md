# ChoiceDraft - Online Assessment Platform

A fully-online version of the ChoiceDraft testing platform using Hostinger hosting with PHP API backend.

## Deployment Instructions

### 1. Database Setup

1. Log in to your Hostinger control panel
2. Go to **Databases > MySQL Databases**
3. Create a new database with the provided credentials:
   - Database Name: `ul30348899_choicedraft`
   - Username: `ul30348899_csit6`
   - Password: `CSIT6_pass`
4. Open **phpMyAdmin** and import the database:
   - First, import `choicedraft_schema.sql` to create tables and seed data
   - Then run `database_migration.sql` to add the start_date and end_date columns

### 2. File Upload

Upload files to your Hostinger hosting in this structure:

```
public_html/
├── index.html
├── signin.html
├── register.html
├── home.html
├── create-test.html
├── profile.html
├── settings.html
├── admin.html
├── about.html
├── favicon.ico
├── css/
│   └── styles.css
├── js/
│   ├── config.js          <-- IMPORTANT: Update API_BASE_URL
│   ├── app.js
│   └── (other js files)
└── test/
    ├── builder/
    │   └── index.html
    ├── take/
    │   └── index.html
    └── results/
        └── index.html

api/  (create this folder at the same level as public_html/)
├── config.php
├── auth.php
├── tests.php
├── questions.php
├── attempts.php
├── users.php
└── collaborators.php
```

### 3. Update Configuration

**IMPORTANT:** Edit `public_html/js/config.js` and update the API_BASE_URL:

```javascript
API_BASE_URL: 'https://choicedraft.ccsblock2.com/api'
```

Replace with your actual Hostinger domain.

### 4. API Folder Access

The `api/` folder should be placed at the same level as `public_html/` (not inside it) for security.

If Hostinger doesn't allow this, you can:
1. Place it inside `public_html/` but add `.htaccess` protection
2. Or create a subdomain like `api.choicedraft.ccsblock2.com`

### 5. Test the Setup

1. Visit `https://choicedraft.ccsblock2.com/`
2. Register a new account or sign in with existing credentials
3. Create a test and verify it's saved to the database

## New Features Added

### Test Duration / Availability Period
- Teachers can now set **Start Date** and **End Date** for tests
- Students can only take tests within this time window
- The answer key is hidden until the test **end date** has passed

### File Structure
- **public_html/**: Contains all HTML, CSS, and JavaScript files (frontend)
- **api/**: Contains all PHP API endpoints (backend)
- **js/config.js**: Centralized configuration file for all API paths

### API Endpoints

| Endpoint | Description |
|----------|-------------|
| `auth.php` | Login, register, logout |
| `tests.php` | CRUD operations for tests |
| `questions.php` | Manage questions and choices |
| `attempts.php` | Submit and retrieve test attempts |
| `users.php` | User management (admin) |
| `collaborators.php` | Test collaboration features |

## Database Schema Changes

The `tests` table now includes:
- `start_date` DATETIME - When students can begin taking the test
- `end_date` DATETIME - When the test closes and answer key becomes visible

## Security Notes

1. All passwords are stored in plain text in this implementation - consider adding password hashing for production
2. The API uses CORS headers to allow cross-origin requests from your domain
3. No API authentication tokens are implemented - consider adding JWT or session-based auth for production

## Troubleshooting

### API returns 500 error
- Check that database credentials in `api/config.php` are correct
- Verify the database exists and tables are created
- Check Hostinger error logs for details

### Frontend can't connect to API
- Verify `API_BASE_URL` in `js/config.js` matches your domain
- Check browser console for CORS errors
- Ensure the `api/` folder is accessible from the web

### Tests not loading
- Check browser console for JavaScript errors
- Verify the database migration has been run
- Test the API directly by visiting: `https://yourdomain.com/api/tests.php`
