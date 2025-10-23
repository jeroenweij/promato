# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is "Promato" - a PHP-based project management and resource planning application for managing projects, activities, personnel, and time tracking. The system includes team planning, capacity management, WBSO (Dutch R&D tax credit) tracking, and financial dashboards.

## Architecture

### Database-Driven Page System
- All pages are registered in the `Pages` table with authentication requirements and menu assignments
- Access control is handled by `includes/auth.php` which checks both:
  1. User auth level (`Types` table: 1=Inactive, 2=User, 3=Project Manager, 4=Elevated, 5=Administrator, 6=Total Admin, 7=God)
  2. Explicit page access via `PageAccess` table (allows granting specific users access to restricted pages)
- Skip pages not in the database - they will trigger authentication failures

### Session & Authentication
- Session-based authentication managed via `$_SESSION['user_id']`, `$_SESSION['auth_level']`, `$_SESSION['user_name']`
- Year selection is session-based: `$_SESSION['selectedYear']` (defaults to current year if not set)
- Authentication flow handled through Google OAuth (`login.php`, `callback.php`)

### Standard Page Structure
Every user-facing PHP page follows this pattern:
```php
<?php
$pageSpecificCSS = ['file.css'];  // Optional
require 'includes/header.php';    // Includes auth.php and db.php
// Page logic here
require 'includes/footer.php';
?>
```

### Core Includes
- `includes/db.php` - PDO MySQL connection using `.env.php` constants (DB_HOST, DB_NAME, DB_USER, DB_PASSWORD)
- `includes/auth.php` - Authentication + page access verification. Sets `$pageInfo`, `$userAuthLevel`, `$userId`, `$currentPage`
- `includes/header.php` - Loads auth, sets up `$selectedYear`, renders navbar with dynamic menu from database, includes Bootstrap 4 and custom CSS
- `includes/footer.php` - Closes HTML structure

### Data Model Core Concepts

**Projects & Activities:**
- `Projects` table: id, name, status (1=Lead, 2=Quote, 3=Active, 4=Closed), manager
- `Activities` table: tasks/activities within projects, with Key (sequential within project), StartDate, EndDate, IsTask flag
- Task codes formatted as: `{ProjectId}-{Key padded to 3 digits}` (e.g., "42-007")
- `Budgets` table: yearly budget hours per activity

**Hours Tracking:**
- `Hours` table: tracks individual person hours per activity (Plan, Hours, Status, Year)
- `TeamHours` table: tracks team-level hours per activity (Plan, Hours, Prio, Status, Year)
- Hours stored as integers (multiply by 100): display value 120.5 = stored as 12050
- `HourStatus`: 1=Backlog, 2=Todo, 3=In Progress, 4=Done, 5=Hidden

**Personnel & Teams:**
- `Personel` table: users with Team, Type (auth level), Fultime percentage, WBSO flag
- `Teams` table: departments/teams with Planable flag and display Order
- `Availability` table: yearly available hours per person (if not set, calculated from Fultime as `Fultime * 2080`)

**WBSO:**
- `Wbso` table: R&D tax credit labels that can be assigned to activities
- Activities with WBSO labels tracked separately for reporting

### Key Conventions

**Number Formatting:**
- Use `number_form($number, $decimals=-1)` helper (defined in header.php) for consistent formatting
- Auto-determines decimal places: shows 1 decimal if fractional, 0 if whole number
- Format: comma for decimal, period for thousands (European style)

**Task Codes:**
- Always format as: `{Project}-{str_pad(Key, 3, '0', STR_PAD_LEFT)}`
- Example: Project 15, Activity 7 = "15-007"

**Database Queries:**
- Use prepared statements with PDO
- Common pattern: LEFT JOIN for PageAccess to check explicit access
- Always filter by `$selectedYear` when querying Hours, TeamHours, Budgets, Availability

**Auth Checks in Pages:**
- Check `$userAuthLevel >= X` for feature gating
- Check `$_SESSION['user_id'] == $projectManagerId` for manager-specific features
- Combine auth checks: `if ($userAuthLevel >= 4 || $_SESSION['user_id'] == $activity['ManagerId'])`

## Development Commands

### Database Setup
```bash
# Import schema (ensure .env.php exists with DB credentials)
mysql -u [user] -p [database] < tables.sql
```

### Running the Application
This is a traditional PHP application meant to run on a web server (Apache/nginx with PHP):
```bash
# Using PHP's built-in server (for development)
php -S localhost:8000
```

### Dependencies
Composer dependencies are already vendored (Google API client, Guzzle, etc.). To update:
```bash
composer update
```

## File Naming & Organization

**Main Pages:**
- Landing page: `index.php` (shows menu grid)
- User dashboard: `dashboard.php` (personal task overview)
- Project pages: `projects.php` (list), `project_details.php`, `project_edit.php`, `project_add.php`
- Planning: `team_planning.php`, `capacity_planning.php`, `priority_planning.php`, `capacity.php`
- Kanban: `kanban.php`
- Admin: `personel.php`, `access_admin.php`, `team_admin.php`, `wbso.php`
- Finance: `finance.php`, `project_finance.php`, `report.php`
- Utilities: `upload.php`, `export.php`, `backup.php`

**AJAX Update Handlers:**
- Named as `update_*.php` (e.g., `update_status.php`, `update_hours_plan.php`, `update_team_plan.php`)
- Return HTTP 200 on success, 400/500 on error
- Accept POST data, perform database updates, minimal output

**Styles:**
- `style/style.css` - Global styles
- `style/[feature].css` - Feature-specific (loaded via `$pageSpecificCSS` array in pages)
- Common feature CSS: `kanban.css`, `projects.css`, `dashboard.css`, `plantable.css`, `priority.css`

**JavaScript:**
- `js/gantt-chart.js`, `js/progress-chart.js` - Chart rendering

## Common Patterns

### Fetching Activities for a Project
```php
$stmt = $pdo->prepare("
    SELECT a.*, b.Hours AS BudgetHours, w.Name AS WBSO
    FROM Activities a
    LEFT JOIN Budgets b ON a.Id = b.Activity AND b.Year = :year
    LEFT JOIN Wbso w ON a.Wbso = w.Id
    WHERE a.Project = :project
      AND a.Visible = 1
      AND YEAR(a.StartDate) <= :year
      AND YEAR(a.EndDate) >= :year
    ORDER BY a.Key
");
$stmt->execute([':project' => $projectId, ':year' => $selectedYear]);
```

### Calculating Hours Display
```php
// Convert stored hours (int * 100) to display value
$displayHours = $storedHours / 100;

// Format for output
echo number_form($displayHours, 1); // Shows 1 decimal
```

### Checking Overbudget Status
```php
$plannedClass = ($actual > $budget) ? 'overbudget' : '';
echo "<td class='totals $plannedClass'>$actual</td>";
```

### Dynamic Access Control
```php
// Check if current user can edit this project
$isEditable = $userAuthLevel >= 4 || $_SESSION['user_id'] == $project['ManagerId'];

if ($isEditable) {
    // Show edit controls
}
```

## Special Features

### Year Selector
The header includes a year dropdown that POSTs to current page with `newYear` parameter. All pages should respect `$selectedYear` from session.

### Team Planning Table (`team_planning.php`)
Complex synchronized dual-table layout:
- Left (fixed): Task codes, activity names, totals
- Right (scrollable): Team columns with editable hours
- JavaScript syncs horizontal scroll and updates totals in real-time
- Inline editing with `UpdateValue(input)` function POSTs to `update_team_plan.php`

### Kanban Board (`kanban.php`)
Drag-and-drop task management grouped by HourStatus (Backlog, Todo, In Progress, Done).

### Dashboard (`dashboard.php`)
Personalized view showing:
- User's planned vs logged hours
- Task priorities ordered by team priority + status
- Project hour distribution
- Managed projects (if user is a project manager)
- WBSO activities (if user has WBSO flag)

## Important Notes

- **Hours are stored as integers multiplied by 100** - always divide by 100 for display, multiply by 100 for storage
- **Year filtering is critical** - most queries should include `Year = :selectedYear` clause
- **Auth levels are cumulative** - level 5 can do everything levels 1-4 can do
- **Task codes use zero-padded keys** - always pad Activity.Key to 3 digits
- **Project status 3 = Active** - many queries filter `WHERE p.Status = 3` to show only active projects
- **Hours.activity = Activity.Key** - hours.activity does not link to Activity.Id but to Activity.Key instead, same for TeamHours.Activity
- **Environment file `.env.php` contains database credentials** - never commit this file
