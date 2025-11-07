# Foundation CMS API Setup

## Environment Variables

Add these variables to your `.env` file:

```env
# S3 Configuration
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket_name
AWS_USE_PATH_STYLE_ENDPOINT=false

# Admin Email (optional)
MAIL_ADMIN_EMAIL=admin@foundation.com
```

## Database Setup

1. Run migrations:

```bash
php artisan migrate
```

2. Seed admin user:

```bash
php artisan db:seed
```

This will create an admin user with:

-   Email: admin@foundation.com
-   Password: password

## API Endpoints

### Public Endpoints

#### Events

-   `GET /api/v1/events` - List published events
-   `GET /api/v1/events/{id}` - Get specific event

#### Journals

-   `GET /api/v1/journals` - List published journals
-   `GET /api/v1/journals/{id}` - Get specific journal

#### Applications

-   `POST /api/v1/applications` - Submit volunteer/intern application

#### Contact

-   `POST /api/v1/contact-requests` - Submit contact request

#### Authentication

-   `POST /api/v1/auth/login` - Admin login
-   `POST /api/v1/auth/logout` - Admin logout
-   `GET /api/v1/auth/me` - Get current user

### Admin Endpoints (Requires Authentication)

#### Events Management

-   `GET /api/v1/admin/events` - List all events
-   `POST /api/v1/admin/events` - Create event
-   `GET /api/v1/admin/events/{id}` - Get event
-   `PUT /api/v1/admin/events/{id}` - Update event
-   `DELETE /api/v1/admin/events/{id}` - Delete event

#### Journals Management

-   `GET /api/v1/admin/journals` - List all journals
-   `POST /api/v1/admin/journals` - Create journal
-   `GET /api/v1/admin/journals/{id}` - Get journal
-   `PUT /api/v1/admin/journals/{id}` - Update journal
-   `DELETE /api/v1/admin/journals/{id}` - Delete journal

#### Applications Management

-   `GET /api/v1/admin/applications` - List applications
-   `GET /api/v1/admin/applications/{id}` - Get application
-   `PUT /api/v1/admin/applications/{id}` - Update application
-   `DELETE /api/v1/admin/applications/{id}` - Delete application
-   `PATCH /api/v1/admin/applications/{id}/approve` - Approve application
-   `PATCH /api/v1/admin/applications/{id}/reject` - Reject application

#### Contact Management

-   `GET /api/v1/admin/contact-requests` - List contact requests
-   `GET /api/v1/admin/contact-requests/{id}` - Get contact request
-   `PUT /api/v1/admin/contact-requests/{id}` - Update contact request
-   `DELETE /api/v1/admin/contact-requests/{id}` - Delete contact request
-   `POST /api/v1/admin/contact-requests/{id}/reply` - Reply to contact request

## Features

### Events

-   Full CRUD operations
-   Status management (draft, published, cancelled)
-   Gallery system with S3 storage
-   Public access to published events only
-   Search and filtering

### Journals

-   Full CRUD operations
-   Status management (draft, published)
-   Featured image support
-   Public access to published journals only
-   Search and filtering

### Applications

-   Public submission form
-   Admin approval/rejection system
-   Resume upload to S3
-   Application type selection (volunteer/intern)
-   Admin notes system

### Contact Requests

-   Public submission form
-   Admin reply system
-   Status tracking (new, replied, closed)
-   Email notifications (optional)

## Authentication

The API uses Laravel Sanctum for authentication. Include the token in the Authorization header:

```
Authorization: Bearer {token}
```

## File Storage

All files are stored in AWS S3 with the following structure:

-   Events featured images: `events/featured/`
-   Event galleries: `events/gallery/`
-   Journal images: `journals/`
-   Application resumes: `applications/resumes/`
