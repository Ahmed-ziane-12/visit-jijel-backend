# VisitJijel — Backend API
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
  <strong>The API and application logic powering VisitJijel.</strong>
</p>

<p align="center">
  <a href="https://github.com/Ahmed-ziane-12/visit-jijel-frontend">Frontend</a> ·
  <a href="https://github.com/Ahmed-ziane-12/visit-jijel-admin-panel">Admin Panel</a>
</p>

---

## Overview

The VisitJijel backend provides the REST API and server-side logic used by the public tourism platform and its administration panel.

Built with Laravel, it handles data persistence, request validation, authentication, authorisation, and the generation of personalised travel itineraries.

## Features

- **REST API** — Endpoints for the platform's resources and application workflows.
- **Authentication** — User authentication through Sanctum.
- **Authorisation** — Server-side access control for protected resources and administrative actions.
- **Tourism Data Management** — Server-side handling of destinations, businesses, and other platform data.
- **Itinerary Generation** — A custom algorithm for constructing suggested itineraries based on trip preferences, reviews and other constraints.
- **Database Integration** — Relational data storage using PostgreSQL.
- **Validation and Error Handling** — Request validation and consistent handling of API errors.


## Tech Stack

- **Language:** PHP
- **Framework:** Laravel
- **Database:** PostgreSQL
- **Authentication:** Laravel Sanctum.
- **API Style:** REST
- **External Services:** Brevo for email verification, Pusher for real-time updates and notifications, 

## Getting Started

### Prerequisites

- PHP version compatible with `composer.json`
- Composer
- PostgreSQL

### Installation

Clone the repository:

```bash
git clone https://github.com/Ahmed-ziane-12/visit-jijel-backend
cd visit-jijel-backend
composer install
```

### Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

### Generate the Application Key

```bash
php artisan key:generate
```

### Database Setup

Create the local PostgreSQL database, then run:

```bash
php artisan migrate
```

Run the appropriate seeders after reviewing their contents:

```bash
php artisan db:seed
```

### Start the Development Server

```bash
php artisan serve
```

By default, Laravel serves the application at `http://localhost:8000`.


## API Documentation

Coming soon ....

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/...` | Retrieve a resource |
| POST | `/api/...` | Create a resource |
| PUT/PATCH | `/api/...` | Update a resource |
| DELETE | `/api/...` | Delete a resource |



## Itinerary Generation

The itinerary-generation process is implemented through the custom `generateItinerary()` algorithm and is triggered by the relevant trip-creation endpoint.

At a high level, the algorithm:

1. Loads candidate destinations, businesses, and events relevant to the trip.
2. Scores candidates against the available preferences and constraints.
3. Sorts candidates by their calculated scores.
4. Constructs the itinerary day by day.
5. Fills available time slots while considering travel time.
6. Removes duplicate items and checks geographic proximity.
7. Produces the resulting itinerary for the client application.

The generated itinerary is a recommendation, not a guarantee of an optimal travel plan. Results depend on the quality of available data, user constraints, and the algorithm's scoring and scheduling rules.

## Security

Security responsibilities include:

- Validating incoming requests.
- Enforcing server-side authentication and authorisation.
- Protecting administrative operations through appropriate policies and middleware.
- Keeping credentials and sensitive configuration out of source control.
- Restricting access to private resources and operations.


## Testing

Automated tests are configured, to run them:

```bash
php artisan test
```

## Related Repositories

- **Public Frontend:** [VisitJijel Frontend](https://github.com/Ahmed-ziane-12/visit-jijel-frontend)
- **Admin Panel:** [VisitJijel Admin](https://github.com/Ahmed-ziane-12/visit-jijel-admin-panel)

## Project Status

VisitJijel is an evolving project. API endpoints, data models, and business logic may change during development.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
