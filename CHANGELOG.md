# ChoiceDraft Changelog

## Version 1.3.0 - My Projects Expansion, Skeletons, About Refresh (April 26, 2026)

### Changes

#### My Projects Tab — Tests From Classes + Collaborations
- **[www/home.html]** Removed `if (t.subject_id) return false` filter — class-linked tests now appear in My Projects for their owner.
- **[www/home.html]** Collaborator-linked tests (class-based or public) now appear in My Projects with an amber `Collaborator` chip.
- **[www/home.html]** Test cards show a teal class-name chip when the test belongs to a subject/class.
- **[www/home.html]** Public Tests tab now also excludes collaborator tests (not just owner tests).
- **[www/home.html]** Clicking a class card navigates directly to `subject/index.html?id=…` — the inline `viewSubject` in-page view is removed.

#### Class Page Tests Section
- **[www/subject/index.html]** Added a "Tests in This Class" section below the member roster showing all tests assigned to that class, with status badge, question count, and inline action buttons (Edit / Questions / View / Delete with same lifecycle guards).
- **[www/subject/index.html]** Added skeleton loader — hero and content sections stay hidden until data loads.

#### Skeleton Loaders
- **[www/test/index.html]** Replaced "Loading test details..." plain text with a 4-slot shimmer skeleton (hero + 3 action rows).
- **[www/profile.html]** Added skeleton before the hero banner; hero + stats row + info card are hidden initially and revealed in `populateView()` once user data is loaded.

#### About Page & Global UI
- **[www/about.html]** Version bumped from `1.0.0` → `1.3.0`.
- **[www/about.html]** Subtitle updated to "A Web-Based Multiple Choice Test Platform".
- **[www/about.html]** Privacy banner updated from "stores data locally" → live platform server note with `fa-server` icon.
- **[Global UI]** Replaced the `fa-table-cells-large` (grid) icon in the top right of inner pages with `fa-house` to better represent its function as a "Home / Dashboard" navigation button.
- All 10 original features retained exactly.

---

## Version 1.2.1 - Hotfix: Subject Page Relative Paths (April 26, 2026)

### Bug Fixes
- **[public_html/subject/index.html]** All asset and navigation paths were incorrectly written as `../../` (two levels up). The file lives at `public_html/subject/` — only **one** level inside `public_html/` — so the correct prefix is `../`. Fixed: `css/styles.css`, `js/config.js`, `js/app.js`, `favicon.ico`, `home.html`, `signin.html`, `profile.html`, `settings.html`.

---

## Version 1.2.0 - Feature Batch: Class Management, Test Lifecycle, Anonymous Mode, Profile & Settings (April 26, 2026)

### Database Migrations
- **[users table]** Added `school_id VARCHAR(50)` column — students can register with and teachers search by school ID.
- **[subjects table]** Added `description TEXT` column — classes can now have a description.
- **[test_attempts table]** Added `is_anonymous TINYINT(1)` and `display_name VARCHAR(150)` columns — anonymous public test attempts.

### New Features

#### Class Management (Teacher)
- **[public_html/subject/index.html]** NEW page: full class management portal — inline editing of class name, description, join code; member roster with avatar, school ID, enrolled date; Add student by School ID; Remove student from class; Delete class (with guard: blocked if students enrolled OR Published tests exist).
- **[public_html/home.html]** Teacher class cards now show a ⚙️ Manage button linking to the class management page.
- **[api/subjects.php]** Full rewrite: enriched member list (name, email, school_id), `addBySchoolId` action, `removeEnrollment` on DELETE with user_id param, `description` field in create/update, delete guards (enrolled students + active tests).

#### Test Deletion Rules
- **[api/tests.php]** DELETE endpoint now returns 403 if test status is `Published` (ongoing). Draft and Finished can be deleted.
- **[public_html/home.html]** Test cards show a 🗑️ delete button only for Draft/Finished status. Published tests have no delete option. Confirmation dialog before deletion. Edit button hidden for Published tests.

#### Test Editing Lock (Published)
- **[public_html/test/builder/index.html]** All form inputs and Save button are disabled when test is `Published`. A yellow warning banner explains why and suggests duplicating or finishing the test first.

#### Anonymous Mode (Public Tests)
- **[public_html/test/take/index.html]** Before the first question on a public test (no subject assignment), a pre-screen asks "Take as [Name]" or "Take Anonymously". Choice is stored as `is_anonymous` flag.
- **[api/attempts.php]** `submitAttempt` now accepts `is_anonymous` and `display_name`. `getAttemptsByTest` uses `CASE WHEN is_anonymous THEN 'Anonymous'` for report display, and `LEFT JOIN` instead of `INNER JOIN` to avoid dropping anonymous records.
- **[public_html/test/take/index.html]** Fixed broken `finishTest` — replaced `updateTest({attempts})` (offline prototype) with actual `apiService.attempts.submit()` call. Attempt is now properly saved to DB.

#### Profile Page Redesign
- **[public_html/profile.html]** Full redesign: dark-gradient hero banner with avatar, role badge, school ID pill; stats row (Tests, Classes, Attempts); info section with icon chips (Name, Email, Institution, School ID, Member Since); slide-up edit modal with real API save via `dataService.updateUser`. Removed broken offline-prototype save logic entirely.

#### Settings Page Cleanup
- **[public_html/settings.html]** Removed "Coming Soon" from title. Removed: 2FA toggle, Email Notifications, Push Notifications, Developer Reset button.
- Added: **Change Password** — wired to API with current-password verification via auth endpoint before saving new password.
- Added: **Institution/School** quick-edit field — saves to DB via `updateUser`.
- Added: **Delete Account** danger zone — triple-confirmed (two dialogs + email confirmation), calls `deleteUser` API (cascades all tests, attempts, enrollments), then clears session and redirects to sign-in.

### API Additions & Updates
- **[api/auth.php]** Register now accepts `school_id` for Student role. Login response includes `school_id`.
- **[api/users.php]** All SELECT queries now include `school_id`. Added `GET ?school_id=` lookup endpoint. `school_id` added to `allowedFields` in `updateUser`.
- **[public_html/register.html]** School ID field added — shown only when Student role is selected. Hidden for Teacher.
- **[public_html/js/config.js]** `subjects` API service: added `getById`, `update`, `delete`, `addBySchoolId`, `removeStudent`. `attempts.submit` now accepts `isAnonymous` param. `auth.register` now passes `school_id`.
- **[public_html/js/app.js]** `dataService` expanded: `getSubjectById`, `updateSubject`, `deleteSubject`, `addStudentBySchoolId`, `removeStudentFromClass`, `updateUser` (with session refresh), `deleteUser`, `submitAttempt`. `createSubject` signature updated to include `description`. `register` now accepts and passes `schoolId`.

---

## Version 1.1.8 - Full camelCase Audit Sweep (April 26, 2026)


### Bug Fixes (Systematic camelCase → snake_case sweep across all frontend files)
- **[test/collaborate/index.html]** Owner card showed hardcoded prototype data (`ownerId === 'user_1'`). Now uses `owner_name` from API. Collaborator remove button now passes `c.user_id` instead of `c.userId`.
- **[test/feedback/index.html]** `passing_score` was read from `currentTest.settings?.passingScore` (nested prototype path) — now reads flat `currentTest.passing_score`. Removed hardcoded fake attempt seed data. Replaced `attempt.userId` lookup via `dataService.data` (offline prototype) with `attempt.user_name`.
- **[test/report/index.html]** Same `settings.passingScore` → `passing_score` fix. `q.correctChoiceId` → `q.correct_choice_id` in question analysis. `a.userId` prototype lookup → `a.user_name`. Removed fake attempt seed data.
- **[test/index.html]** Time limit chip was reading `test.settings.timeLimit` — now reads flat `test.time_limit`.

---

## Version 1.1.7 - Question Editor: Correct Answer Not Saving (April 26, 2026)

### Bug Fixes
- **[test/questions/editor/index.html]** Correct answer was silently discarded on save — the editor was sending `correctChoiceId` (camelCase) but `questions.php` reads `correct_choice_id` (snake_case).
- **[test/questions/editor/index.html]** Formatting flags were spread as `bold/italic/underline` but the API expects `is_bold/is_italic/is_underline` — all three were always saved as `0`.
- **[test/questions/editor/index.html]** Choices were sent without an `is_correct` field — each choice now has `is_correct: 1` if it matches `correctId`, `0` otherwise, before being sent to the API.
- **[test/questions/editor/index.html]** When loading an **existing** question for editing, `correctId` was read from `q.correctChoiceId` (camelCase) and formatting from `q.bold/italic/underline` — both now correctly use `q.correct_choice_id`, `q.is_bold`, `q.is_italic`, `q.is_underline`. Tag also fixed from `q.poolTag` → `q.pool_tag`.

---

## Version 1.1.6 - Card Fixes, Status Machine & Answer Key Gate (April 26, 2026)

### Bug Fixes
- **[home.html]** Card buttons (✏️ Edit, 📋 Questions, 👁 View) now navigate directly to the correct pages — removed the useless intermediate confirmation modal.
- **[home.html]** Test cards were showing **0 questions** because the JS read `test.questions.length` but `listTests` returns a `question_count` aggregate, not a full questions array. Fixed to use `test.question_count`.
- **[home.html]** Badge now correctly shows **Finished** (purple) in addition to Draft/Published.
- **[css/styles.css]** Added `.badge-finished` style (soft purple `#ede9fe` / `#5b21b6`).
- **[test/results/index.html]** Answer key is now **only revealed when the test status is `Finished`** AND `show_answer_key` is enabled — previously it could show while a test was still live.

### New Features
- **[test/builder/index.html]** Replaced free **Status dropdown** with a one-way **state machine**:
  - `Draft` → shows a **Publish** button
  - `Published` → shows a **Mark as Finished** button (requires confirmation)
  - `Finished` → locked, Save Changes disabled, only Duplicate is available
- **[test/builder/index.html]** Added **Duplicate Test** button — creates a fresh `Draft` copy of the current test and redirects to the new builder.

---

## Version 1.1.5 - Performance: Parallel Loads & N+1 Fix (April 26, 2026)

### Performance
- **[home.html]** `loadDashboard()` now fires `getCurrentUser`, `getTests`, and `getSubjects` in **parallel** via `Promise.all` instead of three sequential `await` calls — dashboard skeleton time reduced by ~60% (saves 2 full round-trips on every page load).
- **[api/tests.php]** Fixed **N+1 query** in `getTest()`: choices were previously fetched with one DB query per question (e.g. 20 questions = 21 queries). Now all choices for the entire test are fetched in a single query and mapped in PHP — test detail load time scales with O(1) queries instead of O(n).

---

## Version 1.1.4 - Visual Polish & Color Corrections (April 26, 2026)

### UI Improvements
- **[css/styles.css]** All 24 fixes appended as a non-destructive override block — zero original rules were modified.
- **Color:** `#a9a9a9` secondary text bumped to `#757575` for WCAG-compliant contrast on white backgrounds.
- **Color:** `nav-icon` was `#002e31` (dark, invisible on teal sidebar) — corrected to translucent white tint.
- **Color:** Inactive tab buttons corrected from near-invisible `#a9a9a9` to readable `#666`.
- **Color:** Draft badge changed from off-brand yellow to neutral `#f0f0f0` / `#666`.
- **Color:** Pool "correct choice" row changed from full-saturation teal fill to a soft `#e0f8f9` tint — text stays readable.
- **Color:** `detail-result-card` teal tint lightened; `action-section-title` uses `#8a9ba8` instead of flat grey.
- **Spacing:** Removed double `margin: 1rem` from `.tests-grid` (was stacking with parent padding).
- **Spacing:** Added `padding-bottom: 100px` on mobile so the FAB no longer covers the last test card.
- **Spacing:** Added `margin-bottom: 20px` to `.tab-container` — tab bar and cards were flush.
- **Spacing:** `.back-link` now has `margin-bottom: 12px` and a wider `gap` between icon and text.
- **Spacing:** `.detail-hero` gets more vertical padding and top margin; `.detail-chips` gets `margin-top: 16px`.
- **Cards:** `.test-card` now has a `border-left: 3px solid var(--primary)` accent, stronger shadow, and a subtle lift on hover.
- **Builder:** Settings rows alternate with a faint teal row tint for visual grouping; number inputs widened to `90px`.
- **Builder:** Setting icons now use `#e8f9fa` background with `--primary-dark` icon color instead of plain grey.
- **Pool:** Edit/delete action buttons enlarged to `38px` and get a scale transform on hover.
- **Results:** Score circle gets `margin-top: 8px`; review choice rows get consistent `padding: 8px 10px`.
- **Desktop:** `.home-content` uses `padding: 32px 36px` on wide screens; grid columns adjusted to `minmax(280px, 1fr)`.

---

## Version 1.1.3 - Show Answer Key & Passing Score Persistence (April 26, 2026)

### New Features
- **[Database]** Added `show_answer_key` (TINYINT, default 0) and `passing_score` (TINYINT, default 70) columns to the `tests` table via live `ALTER TABLE` migration.
- **[api/tests.php]** Both fields are now read, written on `createTest`, and updatable via `updateTest`.
- **[test/builder/index.html]** "Show Answer Key" toggle and "Passing Score" input now load from and save to the database.
- **[test/results/index.html]** Pass/Fail verdict now uses the test's actual `passing_score` instead of a hardcoded 60%. The answer key review section is now conditional — if `show_answer_key` is OFF, students see only ✓/✗ per question with a lock message instead of the correct choices.

---

## Version 1.1.2 - General Bug Hunt & UI Fixes (April 26, 2026)

### Bug Fixes
- **[test/index.html]** Fixed multiple camelCase vs snake_case property mismatches (`ownerId`→`owner_id`, `userId`→`user_id`, `poolTag`→`pool_tag`, `correctChoiceId`→`correct_choice_id`, `bold/italic/underline`→`is_bold/is_italic/is_underline`) — the "Test Maker" section (Edit, Builder, Collaboration, Report) was permanently hidden for all users because `isOwner` was always `false`.
- **[create-test.html]** Fixed `subjectId` → `subject_id` key mismatch — tests created inside a Class were silently not being linked to their subject.
- **[js/app.js]** Fixed `login()` returning `undefined` instead of `{ success: false }` when credentials are wrong — previously caused a silent crash in the sign-in form.
- **[js/app.js]** Fixed `hasTestEnded()` incorrectly returning `true` (ended) when a test has no `end_date` set. Now correctly returns `false` (ongoing).
- **[js/app.js]** Fixed malformed SVG path coordinate in the app logo icon (a `110.498` value was corrected to `109.438`).
- **[api/tests.php]** Fixed `listTests()` query — when fetching by `user_id`, it no longer leaks enrolled-class tests into the "My Projects" result set. Subject-scoped tests are now correctly accessed only through the Subject/Class view.
- **[public_html/home.html]** Fixed test card description always appending `...` even on short descriptions. Ellipsis now only appears when the description exceeds 80 characters.
- **[public_html/signin.html]** Removed demo account credentials (`alex@example.com / password123`) that were exposed on the public Sign-In page.
- **[test/builder/index.html]** Fixed `teacherId`→`teacher_id`, `subjectId`→`subject_id`, and `test.settings.*`→flat snake_case DB fields (`shuffle_questions`, `shuffle_choices`, `time_limit`, `start_date`, `end_date`). Save payload now sends correct keys to the API.
- **[test/questions/index.html]** Fixed `poolTag`→`pool_tag`, `correctChoiceId`→`correct_choice_id`, `bold/italic/underline`→`is_bold/is_italic/is_underline` — question cards now correctly highlight the correct answer and apply text formatting.
- **[test/take/index.html]** Fixed `ownerId`→`owner_id`, all `test.settings.*`→flat snake_case fields for timer, shuffle, start/end date blocking, and password protection. Scoring now correctly uses `correct_choice_id`.

### UI Improvements
- **[home.html]** Classes tab "Create Class" and "Join Class" actions moved from a bar near the tabs into the Floating Action Button — cleaner layout.
- **[home.html]** Join class input redesigned as a unified pill-shaped search bar with animated focus border.
- **[home.html / css/styles.css]** Added pulsing skeleton card loader to replace plain text "Loading tests..." on dashboard load.
- **[home.html]** Tabs and FAB are now hidden by default and only revealed after user role is confirmed — eliminates Teacher UI flickering for Student logins.

---

## Version 1.1.0 - Advanced UI & Subjects Integration (April 26, 2026)


### New Features
- **Classes/Subjects Management**: Users can now create classes (subjects) and join them using join codes.
- **Advanced UI Ported**: Merged the full, feature-rich HTML/CSS UI from the offline `ChoiceDraft` prototype to the live `public_html` environment.
- **Frontend Bridging**: Rewrote `js/app.js` to act as an API bridge for the advanced UI.

### Database Changes
- Added `subjects` table.
- Added `subject_enrollments` table.
- Updated `tests` table to include `subject_id`.
- Created manual execution script `database_migration_subjects.sql`.

### API Changes
- **New Endpoint**: `api/subjects.php` for CRUD operations on classes/subjects.
- **Updated Endpoint**: `api/tests.php` now supports `subject_id`.

### Bug Fixes
- **Test Visibility (api/tests.php)**: Fixed a critical logic flaw where students couldn't see tests assigned to their enrolled classes. `listTests()` now joins the `subject_enrollments` table.

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
