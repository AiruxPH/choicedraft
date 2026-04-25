# ChoiceDraft Changelog

## Version 1.0.0 - Online Release (April 26, 2026)

### New Features
- **Full Online Deployment**: Migrated from localStorage-based storage to PHP API with MySQL database
- **Hostinger Integration**: Configured for deployment on Hostinger hosting
- **Centralized API Configuration**: Added `js/config.js` for managing all API endpoints
- **Test Duration/Availability Period**:
  - Added `start_date` and `end_date` fields to tests table
  - Teachers can set when tests open and close
  - Students can only access tests within the availability window
  - Answer key is hidden until the test end date has passed

### File Structure Changes
- Created `api/` folder containing PHP backend:
  - `config.php` - Database configuration
  - `auth.php` - Authentication endpoints
  - `tests.php` - Test CRUD operations
  - `questions.php` - Question management
  - `attempts.php` - Test submission and results
  - `users.php` - User management
  - `collaborators.php` - Collaboration features
- Created `public_html/` folder for frontend:
  - `js/config.js` - Centralized API path configuration
  - `js/app.js` - Updated to use API instead of localStorage
  - All HTML files updated to use new API service

### Database Changes
- **Migration**: Added `start_date` and `end_date` columns to `tests` table
- **Schema Update**: Updated `test_collaborators` table to match static version:
  - Added `name`, `email`, `role` columns (denormalized data)
  - Collaborator role enum: 'Viewer', 'Editor'
  - No JOIN required when fetching test collaborators
- New SQL file: `database_migration.sql`

### Security Updates
- All data now persists in MySQL database on Hostinger
- API endpoints with CORS configuration
- Session management via localStorage with API validation

### Pages Updated
- `index.html` - Landing page with auto-redirect
- `signin.html` - Login with API authentication
- `register.html` - Registration with API
- `home.html` - Dashboard fetching tests from API
- `create-test.html` - Create test with start/end dates
- `profile.html` - User profile from API
- `settings.html` - App settings
- `admin.html` - Admin dashboard with user management
- `about.html` - About page
- `test/builder/index.html` - Edit test with API
- `test/take/index.html` - Take test with availability check
- `test/results/index.html` - Results with answer key visibility control

### Deployment Instructions
1. Import `choicedraft_schema.sql` to create database tables
2. Run `database_migration.sql` to add date columns
3. Upload `public_html/` contents to Hostinger public_html folder
4. Upload `api/` folder (preferably outside public_html)
5. Update `js/config.js` with your actual domain
6. Test all functionality

---

## Previous Versions (Static/Local Version)

### Version 0.9.0 (Pre-release)
- Initial static version with localStorage
- Embedded data in data.js
- Offline functionality
