# Link Shortener

A modern URL shortening service built with Laravel and Filament, featuring geographic analytics, role-based permissions, and a powerful admin interface.

## Features

### 🔗 Core Functionality
- **Fast URL Shortening** - Generate custom or automatic short codes
- **Multiple Redirect Types** - Support for 301, 302, 307, and 308 redirects
- **Link Groups** - Organize links with color-coded groups
- **Expiration Dates** - Set automatic link expiration
- **Custom Slugs** - Create memorable short URLs
- **QR Code Generation** - Instant QR codes with multiple download formats

### 📊 Analytics & Tracking
- **Comprehensive Dashboard** - 6 custom widgets with real-time insights
- **Click Trends Chart** - Interactive line graphs with 7/30/90-day filters
- **Top Links Widget** - Most clicked links with performance metrics
- **Geographic Analytics** - Country and city tracking using MaxMind GeoLite2
- **UTM Campaign Tracking** - Automatic UTM parameter pass-through and analytics
- **Campaign Performance Widget** - Top performing campaigns, sources, and mediums
- **Link Health Status** - Real-time monitoring of destination URL availability
- **Performance Metrics** - Click rates, averages, and growth tracking
- **Browser Detection** - Track user agents and devices
- **Referrer Tracking** - See where clicks are coming from

### 📋 Advanced Reporting System
- **Drag-and-Drop Report Builder** - Visual report creation with intuitive interface
- **Container-Based Layouts** - Flexible grid system using CSS Flexbox
- **Cross-Container Component Movement** - Easily reorganize components between containers
- **Multiple Component Types**:
  - Metric cards with comparison data
  - Line, bar, and pie charts with real-time data
  - Data tables with sorting and filtering
  - Text blocks for annotations and insights
- **Custom Layout Options** - Row/column layouts, spacing, alignment controls
- **Real-Time Preview** - Live preview with actual data from your database
- **Permission-Based Access** - Role-based report viewing and editing
- **Public/Team/Private Visibility** - Flexible sharing options
- **Link Filtering** - Focus reports on specific links or groups
- **Date Range Controls** - Dynamic time-based analytics

### 🛡️ Admin & Security
- **Filament Admin Panel** - Modern, responsive admin interface
- **Role-based Permissions** - Flexible permission system with pre-defined roles for convenience:
  - `super_admin` - Unrestricted access to everything
  - `admin` - Limited permissions (configurable)
  - `user` - Basic role for regular users
  - Administrators can create additional custom roles as needed
- **User Management** - Complete user administration with role assignment
- **User Profile Settings** - Password changes and preferences from user menu
- **API Key Management** - Secure key generation with visible keys and easy copying
- **RESTful API** - Complete REST API with permission-based authentication
- **API Key Verification** - `/api/me` endpoint lets integrations validate a key and identify its owner
- **API Link Analytics** - Zero-filled daily time series plus country, referrer and UTM breakdowns per link
- **Rate Limiting** - Protection against abuse

### 🌍 Geographic Features
- **IP Geolocation** - Automatic location detection for clicks using MaxMind GeoLite2
- **Geo-Targeting Rules** - Redirect visitors to different URLs based on their location
- **Flexible Targeting** - Support for countries, continents, and custom regions
- **Custom Regions** - Pre-defined regions like GDPR Zone, Five Eyes, North America
- **Priority-Based Rules** - Multiple rules with priority ordering for complex scenarios
- **Country Statistics** - Top countries dashboard widget with click analytics
- **Location Filtering** - Filter clicks by geographic data in admin interface
- **Smart Caching** - Performance-optimized caching that doesn't interfere with geo-targeting

### 📈 UTM Campaign Tracking
- **Automatic Pass-Through** - UTM parameters added to short links are preserved and passed to destination URLs
- **Parameter Validation** - Only valid UTM parameters (source, medium, campaign, term, content) are processed
- **Smart URL Merging** - UTM parameters merge intelligently with existing query parameters
- **Campaign Analytics** - Track performance of email campaigns, social media, and paid ads
- **Dashboard Widget** - Real-time campaign performance overview with top sources and mediums
- **Click-Level Data** - Every click stores complete UTM attribution for detailed analysis
- **Filtering & Search** - Filter clicks by campaign, source, medium in admin interface
- **Email Marketing Ready** - Works seamlessly with MailChimp, Constant Contact, and other platforms

### 📱 QR Code Features
- **Instant Generation** - QR codes available in the edit screen
- **Multiple Formats** - Download as PNG (200px, 400px) or SVG (vector)
- **Cross-Platform UX** - Clear download buttons work on all devices
- **Professional Quality** - High-resolution codes perfect for print materials

### 🔍 Link Health Monitoring
- **Automated Health Checks** - Periodic checking of destination URLs
- **Smart Scheduling** - Healthy links checked weekly, errors checked daily, timeouts every 3 days
- **Visual Status Indicators** - Color-coded icons show link health at a glance
- **Health Dashboard Widget** - Real-time overview of all link statuses
- **Manual Health Checks** - Check individual or bulk links on demand
- **Detailed Diagnostics** - HTTP status codes, redirect chains, and error messages
- **Queue-Based Processing** - Non-blocking health checks via job queue
- **Timeout Detection** - Separate categorization for links that timeout (likely blocking datacenter IPs)
- **Exclusion Controls** - Individually exclude links from health checks
- **Configurable Timeout** - Set custom timeout duration for health checks (default 10 seconds)

### 📧 Advanced Notification System
- **Multi-Channel Notifications** - Email, Webhook, Slack, Discord, Microsoft Teams
- **Notification Groups** - Create groups with multiple users and channels
- **Configurable Health Reports** - Comprehensive email reports of all failed links (frequency based on your cron setup)
- **Smart Notification Limits** - Set maximum notifications per link (default: 3) to prevent spam
- **Cooldown Periods** - Configure hours between notifications for the same link (default: 24 hours)
- **New vs Previously Failed** - Email templates clearly separate new failures from ongoing issues
- **Status Code Filtering** - Choose exactly which HTTP status codes trigger notifications
- **Timeout Exclusion** - Option to exclude timeout errors from notifications (for servers blocking datacenters)
- **Batch Limits** - Control maximum links per notification email
- **First Failure Tracking** - Track when each link first failed for context
- **Automatic Pause** - Notifications automatically pause after reaching the limit
- **Recovery Detection** - Counters reset when links become healthy again
- **System Alerts** - Manual alerts for operational issues with severity levels
- **Maintenance Notifications** - Scheduled maintenance announcements with timing
- **Professional Email Templates** - Clean templates with direct edit links for easy fixing
- **Link-Specific Assignments** - Assign notification groups to individual links
- **Batched Group Notifications** - Single email per group with all failed links
- **Individual Owner Alerts** - Personal notifications for link creators
- **Rich Platform Integration** - Formatted messages for Slack/Discord/Teams with embeds

### 🚀 Performance Optimization
- **Redis-Based Click Tracking** - Zero database writes during high-traffic campaigns
- **3 Tracking Methods** - Choose between `queue`, `redis`, or `none` based on needs
- **Batch Processing** - Process clicks in configurable batches (100-2000)
- **70% Faster Redirects** - With Redis caching enabled
- **Smart Triggers** - Automatic processing based on thresholds
- **Time-Based Safety Net** - Scheduled processing ensures no clicks are lost
- **Email Campaign Ready** - Handle thousands of simultaneous clicks without database overload

### 🧪 A/B Testing
- **Multiple Destination URLs** - Test different landing pages for the same short link
- **Weighted Traffic Distribution** - Control percentage of traffic to each variant
- **Real-Time Performance Tracking** - Monitor click distribution across variants
- **Time-Based Scheduling** - Set start and end dates for tests
- **Dashboard Widget** - Overview of all active A/B tests with performance metrics
- **Statistical Insights** - Identify leading variants and track performance
- **UTM Compatible** - Works seamlessly with UTM parameter tracking
- **Geo-Targeting Compatible** - Combine with location-based rules for advanced targeting

### 🔒 Security & Access Control
- **Password Protection** - Secure links with password entry before redirect
- **Click Limits** - Automatically disable links after specified number of clicks
- **Session-Based Authentication** - Password entry persists across user sessions
- **Professional UI** - Clean password forms and limit exceeded pages
- **Real-Time Enforcement** - Security checks use live database data, not cached values
- **Admin Management** - Easy bulk operations, filtering, and click count resets
- **Performance Optimized** - Security checks only run for protected links

### 📂 CSV Import System
- **Bulk Link Creation** - Import hundreds of links from CSV files with comprehensive validation
- **Template Download** - Get properly formatted CSV template with examples and column descriptions
- **Smart Processing** - Small imports (≤100 rows) process immediately, large imports use background queue
- **Automatic Group Creation** - Link groups are created automatically if they don't exist
- **Default Group Assignment** - Links without groups automatically go to your default group
- **Unique Slug Generation** - Automatic short code generation when custom slugs aren't provided
- **Smart Auto-Correction** - Invalid redirect types auto-default to 302, links default to active
- **Graceful Error Handling** - Skip invalid rows but continue processing valid ones
- **Comprehensive Validation** - URL validation, slug uniqueness checks, and data type validation
- **Detailed Feedback** - Clear warnings for skipped rows and auto-corrections
- **Permission-Based Access** - Only users with link creation permissions can import
- **Progress Tracking** - Real-time feedback for small imports, notifications for background processing
- **Data Cleanup** - Automatic file cleanup and secure temporary storage

### 🔗 Third-Party Integrations
- **Google Analytics 4 Integration** - Server-side event tracking with GA4 Measurement Protocol
- **Page View Events** - Sends page_view events for standard GA reports compatibility
- **Comprehensive Data Sharing** - Includes geographic, UTM, A/B test, and device data
- **Queue-Based Processing** - Non-blocking analytics with retry logic and exponential backoff
- **Admin Configuration Panel** - Easy setup with connection testing and validation
- **Production-Ready** - SSL verification, IPv4 resolution, and error handling
- **Privacy-Conscious** - Only sends data when explicitly enabled and configured

<!-- TODO: Add screenshots when available
## Screenshots
- Admin Dashboard with real-time statistics
- Link management with QR codes and analytics  
- Geographic analytics with country tracking
-->

## Installation

**⚡ Quick Setup:** Just 5 commands to get running! The automated installer handles all the complex setup for you.

### Requirements
- PHP 8.2+
- Composer
- Node.js 18+ (for frontend asset compilation)
- MySQL 8.0+ or SQLite 3.8.8+
- MaxMind GeoLite2 license key (free, optional but recommended)
- Redis (optional, for high-performance click tracking)

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/robwent/link-shortener.git
   cd link-shortener
   ```

2. **Install dependencies**
   ```bash
   # PHP dependencies
   composer install

   # Frontend dependencies and build
   npm install
   npm run build
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   
   **Configure your environment:**
   - Set `APP_URL` to your domain (e.g., `https://yourdomain.com`)
   - Add your MaxMind license key to `MAXMIND_LICENSE_KEY`

4. **Database setup**
   
   **For SQLite:**
   ```bash
   touch database/database.sqlite
   ```
   
   **For MySQL:**
   ```bash
   # Update .env with your MySQL credentials:
   # DB_CONNECTION=mysql
   # DB_HOST=127.0.0.1
   # DB_PORT=3306
   # DB_DATABASE=your_database
   # DB_USERNAME=your_username
   # DB_PASSWORD=your_password
   ```

5. **Run the automated installer**
   
   ```bash
   php artisan app:install
   ```
   
   That's it! The installer will:
   - ✅ Publish all required configurations and translations
   - ✅ Run database migrations automatically
   - ✅ Install Filament Shield with proper navigation grouping
   - ✅ Create your admin user (you'll be prompted for details)
   - ✅ Set up all roles and permissions automatically
   - ✅ Configure the admin panel with "Settings" menu organization
   - ✅ Clear caches and optimize the application
   
   **You're ready to go!** Login to `/admin` with the credentials you provided.

6. **Access the application**

   Visit your configured domain to see the homepage and `/admin` for the admin panel.

7. **Configure MaxMind GeoLite2 (Optional but recommended)**
   - Sign up for a free account at [MaxMind](https://www.maxmind.com/en/geolite2/signup)
   - Add your license key to the `MAXMIND_LICENSE_KEY` field in `.env`
   - Download the database:
     ```bash
     php artisan geoip:update
     ```
   - **Note:** Geographic features will gracefully degrade without this setup

8. **Configure Queue Processing (Optional for better performance)**

   The application uses queues for async click tracking. Choose one of these options:

   **Option A: Synchronous Processing (Simple, No Setup)**
   ```bash
   # In your .env file, set:
   QUEUE_CONNECTION=sync
   ```
   
   **Option B: Database Queue with Worker (Recommended)**
   ```bash
   # In your .env file, set:
   QUEUE_CONNECTION=database
   
   # Run the queue worker:
   php artisan queue:work --queue=default,clicks,health-checks,analytics
   ```
   
   **Option C: Cron Job for Shared Hosting**
   ```bash
   # Add to your crontab:
   * * * * * cd /path/to/project && php artisan queue:work --queue=default,clicks,health-checks,analytics --stop-when-empty --max-time=59 >> /dev/null 2>&1
   ```
   
   **Option D: Redis Queue for High Performance**
   ```bash
   # In your .env file:
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   REDIS_CLIENT=predis
   CLICK_TRACKING_METHOD=redis
   ```
   
   See the [Queue Processing](#queue-processing) section for detailed setup instructions.

9. **Production optimization (recommended for live servers)**
   ```bash
   # Cache configuration
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   
   # Publish Filament assets
   php artisan filament:assets
   
   # Optimize autoloader (if not done during composer install)
   composer dump-autoload --optimize
   ```

10. **Run tests (optional)**
    ```bash
    php artisan test
    ```

## Usage

### User Management

Once you have a super admin account set up, you can manage other users:

**Creating Additional Users:**
1. Login to `/admin` with your super admin account
2. Navigate to "Settings" → "Users"
3. Click "Create User"
4. Fill in name, email, password
5. Select a role (any existing role or create new ones as needed)
6. Toggle "Email Verified" if needed
7. Save to create the user

**Role Permissions:**
- **Super Admin**: Can do everything, manage all users and roles
- **Admin**: Limited permissions based on what you assign in "Settings" → "Roles"
- **User**: Basic role, assign permissions as needed
- **Custom Roles**: Create additional roles with specific permissions tailored to your needs

**Managing Roles:**
1. Go to "Settings" → "Roles"
2. Click "Create Role" to add new roles or click existing role names to edit
3. Check/uncheck permissions for each role
4. Users with that role will immediately have those permissions

**Important Security Notes:**
- Only super admins can assign the `super_admin` role
- Super admins cannot delete themselves or other super admins
- Regular admins cannot see or assign super admin permissions

### Creating Short Links

**Via Admin Panel:**
1. Login to `/admin`
2. Navigate to "Links" → "Create"
3. Enter the destination URL
4. Optionally set a custom slug, group, and expiration
5. Save to generate your short link

**Via CSV Import (Bulk Creation):**
1. Login to `/admin`
2. Navigate to "System" → "CSV Import"
3. Download the CSV template to see the required format
4. Fill in your data following the template structure
5. Upload your CSV file and click "Import CSV"
6. Small files (≤100 rows) process immediately, larger files process in background

**CSV Format Requirements:**
- **Required:** `original_url` (must be valid URL with http/https)
- **Optional:** `custom_slug` (letters, numbers, hyphens, underscores only)
- **Optional:** `group_name` (will create group if it doesn't exist, defaults to your default group)
- **Optional:** `expires_at` (YYYY-MM-DD or YYYY-MM-DD HH:MM:SS format)
- **Optional:** `password`, `click_limit`, `notes`
- **Optional:** `redirect_type` (301/302/307/308, defaults to 302 for invalid values)
- **Optional:** `is_active` (1 for active, 0 for inactive, defaults to 1/active if empty)

**Via API:**
```bash
curl -X POST http://localhost:8000/api/links \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "original_url": "https://example.com/very-long-url",
    "custom_slug": "my-link",
    "group_id": 1,
    "expires_at": "2024-12-31T23:59:59Z"
  }'
```

### Geographic Features

**Setting up Geolocation:**
The application requires a MaxMind GeoLite2 database for geographic features. Get a free license key from MaxMind and add it to your `.env`:
```env
MAXMIND_LICENSE_KEY=your_license_key_here
```

Then download the database:
```bash
php artisan geoip:update
```

**Geo-Targeting Rules:**
1. Edit any link in the admin panel
2. Go to the "Geo-Targeting Rules" tab
3. Create rules to redirect visitors based on:
   - **Countries** - Target specific countries (US, CA, GB, etc.)
   - **Continents** - Target entire continents (EU, NA, AS, etc.)
   - **Custom Regions** - Pre-defined groups like GDPR Zone, Five Eyes

**Example Use Cases:**
- Privacy policies: EU visitors → GDPR-compliant page
- Language targeting: Spanish speakers → Spanish content
- Compliance: Financial services → region-specific disclaimers
- Marketing: Different landing pages for different markets

**Priority System:**
Rules are evaluated in priority order (lower number = higher priority). First matching rule wins.

**Database Maintenance:**
Update the GeoLite2 database monthly for accuracy:
```bash
php artisan geoip:update
```

### UTM Campaign Tracking

**How UTM Pass-Through Works:**
UTM parameters added to your short links are automatically passed through to the destination URL, enabling end-to-end campaign tracking.

**Example Flow:**
```
Original Link: https://myshortener.example/product
With UTM: https://myshortener.example/product?utm_source=newsletter&utm_medium=email&utm_campaign=spring2024
Destination: https://example.com/product?utm_source=newsletter&utm_medium=email&utm_campaign=spring2024
```

**Supported UTM Parameters:**
- `utm_source` - Traffic source (newsletter, google, facebook)
- `utm_medium` - Marketing medium (email, social, cpc)
- `utm_campaign` - Campaign name (spring2024, black_friday)
- `utm_term` - Paid search keywords
- `utm_content` - A/B test content variation

**Real-World Use Cases:**
- **Email Marketing**: MailChimp automatically adds UTM tags → track email performance
- **Social Media**: Hootsuite/Buffer campaigns → measure social ROI
- **Paid Advertising**: Google Ads/Facebook → attribution across platforms
- **Cross-Channel**: Compare performance across email, social, and paid channels

**Analytics Dashboard:**
1. View "Campaign Performance" widget on dashboard
2. See top campaigns, sources, and mediums with click counts
3. Filter by date range (today, this week, this month)
4. Click into individual links to see detailed UTM breakdown

**Advanced Features:**
- **Parameter Merging**: UTM parameters merge with existing URL query parameters
- **Override Protection**: New UTM parameters take precedence over existing ones
- **Validation**: Only valid UTM parameters are processed and stored
- **Geo-Targeting Compatible**: Works seamlessly with location-based redirects

**Default UTM Parameters (Built-in Solution):**
You don't need a separate "default UTM parameters" feature - just include them in your destination URLs! The system intelligently merges parameters:

```
Destination URL: https://mystore.example/sale?utm_source=website&utm_campaign=spring2024
Newsletter Link: https://myshortener.example/sale?utm_source=newsletter&utm_medium=email
Final Result: https://mystore.example/sale?utm_source=newsletter&utm_campaign=spring2024&utm_medium=email
```

This approach is more flexible and follows standard marketing practices. Campaign-specific UTM parameters override defaults while preserving other values.

### Link Health Monitoring

**Automated Health Checks:**
```bash
# Check links that need it (based on smart scheduling)
php artisan links:check-health

# Process 100 links at a time
php artisan links:check-health --batch=100

# Force check all links
php artisan links:check-health --all

# Check only error links
php artisan links:check-health --status=error
```

**Setting up Automated Checks:**
Add to your crontab:
```cron
# Run health checks daily at 2 AM
0 2 * * * cd /path/to/project && php artisan links:check-health >> /dev/null 2>&1
```

**Processing Jobs:**
The queue worker will automatically process both click tracking and health check jobs:
```bash
php artisan queue:work --queue=default,clicks,health-checks,analytics
```

### Location Data Management

**Update Missing Location Data:**
If you have clicks that are missing geographic information (country/city), you can retroactively update them:

```bash
# Update clicks missing location data
php artisan clicks:update-locations

# Preview what would be updated without making changes
php artisan clicks:update-locations --dry-run

# Process in smaller batches (default is 100)
php artisan clicks:update-locations --batch=50

# Reprocess all clicks, including those with existing location data
php artisan clicks:update-locations --all
```

**Note:** This command automatically skips private/local IP addresses (like 127.0.0.1) that cannot be geolocated. The command requires the MaxMind GeoLite2 database to be installed.

### Notification System

The application includes a comprehensive notification system that monitors link health and sends multi-channel alerts.

**Setting Up Notification Groups:**
1. Login to admin panel at `/admin`
2. Navigate to "Settings" → "Notifications"
3. Click "Create Notification Group"
4. Add users to the group and configure notification channels:
   - **Email** - User email addresses (automatic from group members)
   - **Webhook** - Custom HTTP endpoints with configurable headers
   - **Slack** - Webhook URLs with optional channel targeting
   - **Discord** - Webhook URLs with rich embed formatting
   - **Microsoft Teams** - Webhook URLs with card formatting

**Configuring Notification Limits:**
1. Navigate to "Settings" → "Notification Limits"
2. Configure these settings:
   - **Maximum notifications per link** - How many times to notify before pausing (default: 3, set to 0 for unlimited)
   - **Notification cooldown** - Hours to wait between notifications for the same link (default: 24)
   - **Batch notification limit** - Maximum failed links per email (default: 50)
   - **Check timeout** - Seconds to wait for response before marking as timeout (default: 10)
   - **Exclude timeouts** - Don't notify for timeout errors (useful for servers blocking datacenter IPs)
   - **Status code filtering** - Choose exactly which HTTP status codes trigger notifications

**Automated Health Notifications:**
```bash
# Health notifications (configure frequency as needed)
0 9 * * * cd /path/to/project && php artisan notifications:send-health >/dev/null 2>&1   # Daily at 9 AM
0 */6 * * * cd /path/to/project && php artisan notifications:send-health >/dev/null 2>&1  # Every 6 hours
0 9 * * MON cd /path/to/project && php artisan notifications:send-health >/dev/null 2>&1  # Weekly on Monday

# Dry run to preview what would be sent
php artisan notifications:send-health --dry-run

# Health checks every 30 minutes
*/30 * * * * cd /path/to/project && php artisan links:check-health >/dev/null 2>&1
```

**Manual System Notifications:**
```bash
# System alerts (when issues occur)
php artisan notifications:send system --message="Database connection lost" --severity=high

# Maintenance notifications (before maintenance)
php artisan notifications:send maintenance --message="Maintenance in 1 hour" --schedule="2024-01-15 02:00:00"

# Test notifications
php artisan notifications:test system-alert
php artisan notifications:test maintenance
```

**Link-Specific Notifications:**
- Edit any link in the admin panel
- Go to "Notification Settings" tab
- Assign notification groups to receive alerts for that specific link
- Groups receive batched reports of all failed links based on your cron schedule

### Role Permission Management

**Set up default permissions for all roles:**
```bash
php artisan roles:setup
```

**Reset and reconfigure specific roles:**
```bash
# Reset admin role permissions and apply defaults
php artisan roles:setup --reset --role=admin

# Set up only user and panel_user roles
php artisan roles:setup --role=user --role=panel_user
```

**Default Permission Assignments:**
- **super_admin**: All permissions (automatic, cannot be changed)
- **admin**: Full link management, groups, API keys, all dashboard widgets
- **user**: Basic link management, view groups, limited dashboard widgets
- **Custom roles**: Define permissions based on your specific needs

**Manual Permission Management:**
You can always customize permissions in the admin panel at "Settings" → "Roles".

### API Key Management

**Creating API Keys:**
1. Login to admin panel at `/admin`
2. Navigate to "System" → "API Keys"
3. Click "Create API Key"
4. Set name, permissions, and optional expiration
5. Click on the key in the table to copy it (keys remain visible for easy access)

**Available Permissions:**
- `links:create` - Create new short links
- `links:read` - View existing links
- `links:update` - Modify existing links  
- `links:delete` - Delete links
- `stats:read` - Access click statistics
- `groups:create` - Create new groups
- `groups:read` - View existing groups
- `groups:update` - Modify existing groups
- `groups:delete` - Delete groups

**Note:** Leave permissions empty for full access to all endpoints.

**Note:** `/api/me` is exempt from permission checks - any valid, unexpired key can call it. This lets an integration verify a key it has been given without needing to know which scopes it holds.

**API Authentication Methods:**
```bash
# Method 1: Authorization Header (Recommended)
curl -X GET 'https://example.com/api/links' \
  -H 'Authorization: Bearer sk_your_api_key'

# Method 2: X-API-Key Header  
curl -X GET 'https://example.com/api/links' \
  -H 'X-API-Key: sk_your_api_key'

# Method 3: Query Parameter (Easy for testing)
curl -X GET 'https://example.com/api/links?api_key=sk_your_api_key'
```

### API Endpoints

**Account API:**
| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| `GET` | `/api/me` | Verify a key and identify its owner | None - any valid key |

**Links API:**
| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| `POST` | `/api/links` | Create a new short link | `links:create` |
| `GET` | `/api/links` | List your links | `links:read` |
| `GET` | `/api/links/{id}` | Get link details | `links:read` |
| `PUT` | `/api/links/{id}` | Update a link | `links:update` |
| `DELETE` | `/api/links/{id}` | Delete a link | `links:delete` |
| `GET` | `/api/links/{id}/stats` | Get click statistics | `stats:read` |

**Groups API:**
| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| `GET` | `/api/groups` | List all groups | `groups:read` |
| `GET` | `/api/groups/{id}` | Get group details | `groups:read` |
| `POST` | `/api/groups` | Create a new group | `groups:create` |
| `PUT` | `/api/groups/{id}` | Update a group | `groups:update` |
| `DELETE` | `/api/groups/{id}` | Delete a group | `groups:delete` |

**Query Parameters:**

`GET /api/links`
- `search` - Substring match against short code, custom slug, and destination URL
- `sort` - `created_at` (default) or `click_count`
- `direction` - `asc` or `desc` (default)
- `group_id` / `is_active` - Filter by group or active state
- `per_page` - Results per page (default 15, max 100)

`GET /api/links/{id}/stats`
- `days` - Width of the daily time series (default 30, clamped to 1-365)

`GET /api/groups`
- `simple=true` - Returns simplified list for dropdowns

**Other Parameters:**
- `POST/PUT` on groups with `"is_default": true` - Sets group as default for new links

### Key Verification

`GET /api/me` lets an integration confirm a pasted key is valid and show who it belongs to. It requires only a valid, unexpired key, so a key restricted to a single scope can still identify itself:

```bash
curl -X GET 'https://example.com/api/me' \
  -H 'Authorization: Bearer sk_your_api_key'
```

```json
{
    "data": {
        "user": { "id": 3, "name": "Jane Broker", "email": "jane@example.com" },
        "key": {
            "name": "Intranet key",
            "permissions": null,
            "expires_at": null,
            "last_used_at": "2026-08-01T10:00:00.000000Z"
        }
    }
}
```

`permissions: null` means full access. The key itself is never returned. `last_used_at` reports the *previous* authenticated request rather than the current one, so it stays meaningful when displayed in a UI. Invalid, missing and expired keys each return a distinct 401 message, so a client can tell "never worked" from "stopped working".

### Link Statistics

`GET /api/links/{id}/stats` returns aggregate click data for a single link. All figures are calculated in SQL, so response time does not grow with click volume:

```bash
curl -X GET 'https://example.com/api/links/12/stats?days=30' \
  -H 'Authorization: Bearer sk_your_api_key'
```

Returns:
- `total_clicks`, `today_clicks`, `this_week_clicks`, `this_month_clicks` - Counts per window (weeks start Monday, days are UTC)
- `daily_series` - One zero-filled entry per day for the last `days` days, ready to chart directly
- `top_countries` - Up to 10 countries by click count
- `top_referrers` - Up to 10 referrers **grouped by host** (scheme, `www.`, port, path and query are stripped, so all paths on a domain count together). The `null` bucket is direct/unknown traffic
- `utm.campaigns` / `utm.sources` / `utm.mediums` - Up to 10 values each, nulls excluded

**Note:** `link.click_count` (the denormalised counter on the link) and `stats.total_clicks` (a count of click rows) come from different sources and can briefly disagree under Redis or queued click tracking. Use `stats.total_clicks` when the number must agree with the breakdowns alongside it.

## Third-Party Integrations

### Google Analytics 4 Integration

The application includes built-in Google Analytics 4 integration using the Measurement Protocol API. This provides server-side event tracking that works reliably across all browsers and devices.

**Key Features:**
- **Page View Events** - Sends `page_view` events compatible with standard GA4 reports
- **Comprehensive Data** - Includes geographic, UTM, A/B test, and device information
- **Non-blocking** - Uses Laravel queues for async processing with retry logic
- **Production Ready** - SSL verification, error handling, and IPv4 resolution
- **Privacy Conscious** - Only sends data when explicitly enabled and configured

**Setup Instructions:**

1. **Get GA4 Credentials:**
   - Create a GA4 property in Google Analytics
   - Find your Measurement ID (starts with `G-`)
   - Generate an API Secret in GA4 Admin → Data Streams → [Your Stream] → Measurement Protocol API Secrets

2. **Configure Integration:**
   - Login to admin panel at `/admin`
   - Navigate to "Settings" → "Integrations"
   - Enable Google Analytics integration
   - Enter your GA4 Measurement ID (e.g., `G-XXXXXXXXXX`)
   - Enter your Measurement Protocol API Secret
   - Click "Test Connection" to verify setup
   - Save settings

3. **Register Custom Parameters (Important):**
   Custom parameters must be registered in GA4 to be recorded properly:
   - Go to GA4 Admin → Custom Definitions → Custom Dimensions
   - Create custom dimensions for the parameters you want to track:
     - `custom_link_id` - Link ID (Event-scoped)
     - `custom_link_slug` - Link Slug (Event-scoped)
     - `custom_destination_url` - Destination URL (Event-scoped)
     - `ab_test_id` - A/B Test ID (Event-scoped)
     - `ab_variant_id` - A/B Test Variant (Event-scoped)
     - `device_type` - Device Type (Event-scoped)
   - Standard parameters (country, utm_source, etc.) are automatically available

4. **Queue Setup:**
   Make sure your queue worker includes the `analytics` queue:
   ```bash
   php artisan queue:work --queue=default,clicks,health-checks,analytics
   ```

5. **Verification:**
   - Use "Test Connection" button to verify setup (events appear in GA4 DebugView)
   - Make test clicks on your short links (events appear in standard GA4 reports within 24-48 hours)
   - Events appear as page views with your short link slugs as page titles

**Data Sent to Google Analytics:**

*Standard Parameters (automatically available):*
- **Page Location** - The short link URL (your domain + slug)
- **Page Title** - The short link slug with " - Link Redirect" suffix
- **Page Referrer** - Where the click originated from
- **Timestamp** - Exact click time (important for queued processing)
- **Session ID** - User session identifier for proper event grouping
- **Geographic Data** - Country, region, city (when available)
- **UTM Parameters** - Campaign tracking parameters mapped to GA4 standard names (source, medium, campaign, term, content)

*Custom Parameters (require registration in GA4):*
- **Link Data** - `custom_link_id`, `custom_link_slug`, `custom_destination_url`
- **A/B Test Data** - `ab_test_id`, `ab_variant_id` for optimization campaigns
- **Device Information** - `device_type`, `browser`, `operating_system`

**Note:** Custom parameters must be registered as Custom Dimensions in GA4 Admin → Custom Definitions before they will appear in reports.

**Privacy & Performance Notes:**
- Events are processed asynchronously via Laravel queues
- Failed events are retried with exponential backoff
- GA failures never block or slow down redirects
- No client-side JavaScript or cookies required
- Only sends data for actual clicks, not bot traffic
- **Note:** Real click events appear in standard GA4 reports (not DebugView). Only connection tests use debug mode.

## Development

This project serves as a learning exercise for:
- **Laravel 12.x** - Latest framework features and best practices
- **Filament 5.x** - Modern admin panel with Livewire 4
- **Performance Optimization** - Raw SQL for redirects, caching strategies
- **Geographic Services** - IP geolocation and mapping
- **API Design** - RESTful APIs with proper authentication

### Key Architecture Decisions

- **Database Flexibility** - Supports both MySQL and SQLite for different deployment scenarios
- **File-based Caching** - Fast access to frequently used links
- **Raw SQL for Redirects** - Maximum performance for the core feature
- **Async Click Logging** - Non-blocking analytics collection via queues
- **Graceful Geolocation** - Works with or without MaxMind database

### File Structure

```
app/
├── Http/Controllers/
│   ├── Api/LinkController.php      # Link API endpoints
│   ├── Api/AccountController.php   # Key verification (/api/me)
│   └── RedirectController.php      # Fast redirect handler
├── Filament/
│   ├── Resources/                  # Admin panel resources
│   └── Widgets/                    # Dashboard widgets
├── Models/
│   ├── Link.php                    # Core link model
│   ├── Click.php                   # Analytics model
│   └── LinkGroup.php               # Link groups
├── Jobs/
│   └── LogClickJob.php             # Async click logging
└── Services/
    ├── GeolocationService.php      # IP to location mapping
    ├── LinkStatsService.php        # SQL-side click aggregation
    └── LinkShortenerService.php    # URL generation
```

## Queue Processing

The application uses Laravel's queue system to process click tracking asynchronously, ensuring fast redirect performance. Click data is logged in the background without slowing down the redirect.

### Configuration Options

#### 1. Synchronous Processing (No Setup Required)
```env
QUEUE_CONNECTION=sync
```
- Jobs execute immediately during the request
- No additional processes needed
- Suitable for low-traffic sites
- Adds ~50-100ms to redirect time

#### 2. Database Queue (Recommended)
```env
QUEUE_CONNECTION=database
```

**Running the Worker:**
```bash
# Process all queue jobs (clicks, health checks, etc.)
php artisan queue:work --queue=default,clicks,health-checks,analytics --sleep=3 --tries=3
```

#### 3. Production Deployment Options

**Supervisor (VPS/Dedicated Servers):**
```ini
[program:redirection-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work --queue=default,clicks,health-checks --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/logs/worker.log
```

**Systemd Service (Modern Linux):**
```ini
[Unit]
Description=Redirection Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/artisan queue:work --queue=default,clicks,health-checks --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

**Cron Job (Shared Hosting):**
```cron
# Runs every minute (processes all queues)
* * * * * cd /path/to/project && php artisan queue:work --queue=default,clicks,health-checks,analytics --stop-when-empty --max-time=59 >> /dev/null 2>&1

# Alternative for limited hosting (every 5 minutes, max 10 jobs)
*/5 * * * * cd /path/to/project && php artisan queue:work --queue=default,clicks,health-checks,analytics --stop-when-empty --max-jobs=10 >> /dev/null 2>&1

# ISPConfig format (adjust path as needed)
* * * * * php /var/www/clients/client1/web1/web/artisan queue:work --queue=default,clicks,health-checks --tries=3 --stop-when-empty
```

**Laravel Scheduler (Optional for Time-Based Tasks):**
```cron
# Add this single line to process scheduled tasks (health checks, etc.)
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```
This runs the Laravel scheduler which handles:
- Link health checks (if configured)
- Any other scheduled maintenance tasks

**Redis Click Processing (Recommended for High Traffic):**
```cron
# Process Redis clicks every 2 minutes for reliable batch processing
*/2 * * * * cd /path/to/project && php artisan clicks:process-batch >> /dev/null 2>&1
```

### Monitoring Queue Health

```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Clear old jobs
php artisan queue:flush
```

## Performance

- **Sub-100ms redirects** using optimized database queries
- **File-based caching** for frequently accessed links
- **Async analytics** via queue system (adds only ~5-10ms to redirects)
- **Database indexing** on short codes and active status
- **Rate limiting** to prevent abuse

### High-Traffic Email Campaign Optimization

When sending email campaigns, the system can handle thousands of simultaneous clicks without database overload using Redis-based click tracking.

**Configuration for Email Campaigns:**
```env
# Enable Redis-based click tracking
CLICK_TRACKING_METHOD=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis

# Configure batch processing
REDIS_TRIGGER_THRESHOLD=500  # Start processing at 500 clicks
REDIS_BATCH_SIZE=2000        # Process 2000 clicks per batch
```

**Performance Improvements with Redis:**
- **70% faster redirects** compared to traditional queue method
- **Zero database writes** during redirect (except links with click limits)
- **Batch processing** reduces database load
- **Automatic time-based processing** ensures no clicks are lost

**Click Tracking Methods:**

1. **`queue` (default)** - Traditional queue-based tracking
   - Good for normal traffic
   - 1-2 database writes per click

2. **`redis`** - High-performance batch tracking
   - Perfect for email campaigns
   - Zero database writes during redirect
   - Processes clicks in batches

3. **`none`** - Minimal tracking
   - Fastest possible redirects
   - Only increments click count
   - No detailed analytics

**Monitoring During Campaigns:**
```bash
# Check pending clicks
redis-cli llen clicks:pending

# Process clicks manually if needed
php artisan clicks:process-batch

# Check queue status (if using queue-based processing)
php artisan queue:monitor

# Check cron job effectiveness
tail -f storage/logs/laravel.log | grep "ProcessRedisBatchJob"
```

## Testing

The project includes a comprehensive test suite covering:

- **Feature Tests** - End-to-end redirect functionality, homepage, and user flows
- **Unit Tests** - Individual service classes, models, and business logic
- **Integration Tests** - Database relationships and geographic data handling

**Running Tests:**
```bash
# Run all tests
php artisan test

# Run specific test files
php artisan test --filter=RedirectTest
php artisan test --filter=LinkShortenerServiceTest

# Run with coverage (requires Xdebug)
php artisan test --coverage
```

**Test Coverage:**
- 395+ tests with 1375+ assertions
- Core redirect functionality
- Complete API endpoint testing (links, groups, key verification, and statistics)
- Link generation and validation
- Geographic data processing and geo-targeting rules
- UTM parameter pass-through and analytics tracking
- Click tracking and analytics
- API authentication and permissions
- User profile functionality
- Dashboard widgets and analytics
- QR code generation and downloads
- Model relationships and business logic
- Default group functionality
- Queue job processing
- Link health checking functionality
- Role permission management
- Custom Artisan commands
- Geo-targeting rule evaluation and priority handling
- Redis-based click tracking and batch processing
- Performance optimization features
- **Google Analytics 4 integration** - Complete service, job, and integration testing
- **Third-party integrations** - Settings management and admin panel functionality
- **CSV Import System** - Bulk link creation with validation, queue processing, and error handling
- **API key verification** - `/api/me` with valid, restricted, expired and missing keys
- **API link statistics** - SQL aggregation correctness, time-series zero-fill, top-N ordering, referrer host grouping, and a guard proving click rows are never hydrated

## Changelog

### 2026-08-02 - Fix: admin menu items hidden since the Filament 5 upgrade

**API Keys**, **Groups** and **Settings → Notifications** were missing from the admin navigation for every user, in every role, since the 2026-04-06 upgrade.

**Cause:** that upgrade renamed permissions from the `::` separator to `_` in the database, but five policy files were never updated to match. They kept checking the old names:

```php
// app/Policies/ApiKeyPolicy.php - before
return $user->can('view_any_api::key');   // no such permission exists
// after
return $user->can('view_any_api_key');    // matches the database
```

`can()` on a permission name that doesn't exist returns `false` silently, so `viewAny()` always denied and Filament hid the resource. Shield is configured with `define_via_gate => false`, meaning there is no super-admin bypass — the check applied to everyone, including `super_admin`.

Only multi-word resource names were affected, because `view_any_link` is spelled identically under both conventions. That is why Links, Reports, Users and Roles kept working.

**Fixed policies:** `ApiKeyPolicy`, `LinkGroupPolicy`, `NotificationChannelPolicy`, `NotificationGroupPolicy`, `NotificationTypePolicy`.

**Deployment:** upload `app/Policies/` only. No migrations, no `shield:generate`, no `roles:setup`, no cache clear — the database was always correct, only the code was wrong. Restart PHP-FPM if OPcache runs with `validate_timestamps=0`.

**Diagnosing this class of problem:** compare what the policies ask for against what actually exists:

```bash
# What the policies check
grep -rho "can('[^']*')" app/Policies/ | sed "s/can('\(.*\)')/\1/" | sort -u

# What the database holds
php artisan tinker --execute 'echo \Spatie\Permission\Models\Permission::orderBy("name")->pluck("name")->implode("\n");'
```

Any name in the first list that is absent from the second is a permanently-denied check. Note that `app/Policies/RolePolicy.php` still contains unrendered Shield placeholders (`can('{{ ForceDelete }}')`) on its force-delete, restore and reorder methods. These are harmless — the corresponding permissions do not exist either, so those actions stay denied, which is the intended default.

### 2026-08-02 - API: key verification, expanded link statistics, searchable index

Extends the REST API so external tools can validate their own credentials and build a stats UI without hammering the database.

**New:**
- `GET /api/me` - Verifies a key and returns its owner. Requires only a valid, unexpired key, so permission-restricted keys can still identify themselves. Never echoes the key back
- `GET /api/links` gains `search` (matches short code, custom slug and destination URL), `sort` (`created_at` or `click_count`) and `direction`
- `GET /api/links/{id}/stats` gains `this_month_clicks`, a zero-filled `daily_series`, `top_referrers` and `utm` breakdowns, plus a `days` parameter (default 30, clamped to 1-365)

**Improvements:**
- Link statistics are now aggregated entirely in SQL. The previous implementation loaded every click row into memory and grouped in PHP, which would not survive a link with tens of thousands of clicks. Response cost is now flat regardless of click volume, and a test asserts the query count stays constant as clicks grow
- Referrers are grouped by host in SQL, so every path on a domain counts towards that domain rather than being split across separate rows
- The links index reads the denormalised `links.click_count` column instead of running a per-link aggregate over the clicks table
- Index results are ordered by `id` as a tiebreaker, making pagination stable when sort values tie
- `last_used_at` on an API key is preserved before being overwritten, so `/api/me` reports the previous request rather than always reading "just now"

**Breaking change:**
- Items in the `GET /api/links` response no longer include the `clicks: [...]` pseudo-array, which was an artifact of how counts were previously computed. Use the `click_count` integer field on each item instead

**Deployment:** No migrations, no dependency changes, and no frontend rebuild required. If you cache routes, re-cache them so the new `/api/me` route resolves:

```bash
php artisan optimize:clear   # safe whether or not caches exist
php artisan optimize         # only if you cache config/routes in production
```

Verified against both SQLite and MySQL 8.0 with `ONLY_FULL_GROUP_BY` enabled, since the date and referrer-host expressions are driver-specific.

### 2026-04-06 - Security Update: Filament 5, Livewire 4, Shield 4

Upgraded core dependencies to address a Livewire security vulnerability and an XSS vulnerability in Filament tables (CVE-2026-33080).

**Dependency upgrades:**
- Filament 3.3.49 → 5.4.0
- Livewire 3.6.3 → 4.2.1
- Filament Shield 3.3.6 → 4.2.0
- Spatie Permission 6.18.0 → 7.2.2
- Tailwind CSS config migrated from JS to CSS-only (v4)

> ⚠️ **This upgrade shipped with a bug.** The permission rename below was applied to the database and to `roles:setup`, but five policy files were left checking the old `::` names, silently hiding the API Keys, Groups and Notifications menus for all users. Fixed on 2026-08-02 — see [that entry](#2026-08-02---fix-admin-menu-items-hidden-since-the-filament-5-upgrade). If you are upgrading from 3.x, apply both changes together and verify every admin menu item appears afterwards.

**Breaking changes for existing installations:**
- Permission names changed from `::` separator to `_` (e.g., `view_api::key` → `view_api_key`). Run the rename command in the [deployment notes](#deployment-notes-for-filament-5-upgrade). **Policies must be renamed to match** — the `$user->can('...')` strings in `app/Policies/` are not updated by the rename script or by `shield:generate`.
- Shield config (`config/filament-shield.php`) completely rewritten for v4 format
- `tailwind.config.js` removed — Tailwind v4 uses CSS-based config in `resources/css/app.css`
- Filament assets must be republished: `php artisan filament:assets`
- Frontend must be rebuilt: `npm install && npm run build`

**Other improvements:**
- `roles:setup` command is now additive by default (won't remove existing permissions unless `--reset` is used)
- `roles:setup` auto-includes all page and widget permissions dynamically
- Fixed health_status migrations for SQLite compatibility
- Fixed route precedence for custom redirect URLs
- Fixed 76 pre-existing test failures (371 tests now passing)

#### Deployment Notes for Filament 5 Upgrade

If upgrading an existing installation:

```bash
# 1. Upload all files (except vendor/, node_modules/, .env, storage/)

# 2. Install dependencies
composer install --no-dev --optimize-autoloader

# 3. Run migrations
php artisan migrate --force

# 4. Rebuild frontend and publish assets
npm install && npm run build
php artisan filament:assets

# 5. Rename old permissions (:: → _)
php artisan tinker --execute '
use Spatie\Permission\Models\Permission;
Permission::where("name", "like", "%::%")->each(function ($p) {
    $newName = str_replace("::", "_", $p->name);
    if (!Permission::where("name", $newName)->where("guard_name", $p->guard_name)->exists()) {
        $p->update(["name" => $newName]);
    } else {
        $p->delete();
    }
});
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo "Done\n";
'

# 6. Generate new permissions and set up roles
php artisan shield:generate --all
php artisan roles:setup

# 7. Clear caches
php artisan optimize:clear

# 8. Delete hot file if it exists
rm -f public/hot

# 9. Verify every policy check matches a real permission
#    Anything listed here is a check that will always deny (see the 2026-08-02 fix)
comm -23 \
  <(grep -rho "can('[^']*')" app/Policies/ | sed "s/can('\(.*\)')/\1/" | sort -u) \
  <(php artisan tinker --execute 'echo \Spatie\Permission\Models\Permission::orderBy("name")->pluck("name")->implode("\n");' | grep -E "^[a-z]" | sort -u)
```

**After deploying, log in and confirm every admin menu item is present** — API Keys, Groups, and Settings → Notifications are the ones most likely to disappear, since a permission mismatch hides a resource silently rather than erroring.

## Future Enhancements

### Advanced Features
- **Link Scheduling** - Auto-activate/deactivate at specific times
- **Bulk Operations** - Mass edit/delete links
- **Webhooks** - Real-time click event notifications

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Acknowledgments

- **Laravel** - The amazing PHP framework
- **Filament 5** - Admin panel framework
- **Livewire 4** - Reactive UI components
- **Tailwind CSS 4** - Utility-first CSS framework
- **MaxMind** - GeoLite2 geographic database
- **Heroicons** - Clean, modern icons

---
