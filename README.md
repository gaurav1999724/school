# E school SAAS

It is a Saas version of the main E school Product

[//]: # (## Screenshots)

[//]: # (![App Screenshot]&#40;https://via.placeholder.com/468x300?text=App+Screenshot+Here&#41;)

### Setup Instructions

Clone the project

```bash
  https://github.com/wrteam-sagar/eschool-saas.git
```

Go to the project directory

```bash
  cd eschool-saas
```

Install dependencies

```bash
  composer install
```

Copy .env File

```bash
  cp .env.example .env
```

Configure ENV Variables

`DB_HOST`

`DB_PORT`

`DB_DATABASE`

`DB_USERNAME`

`DB_PASSWORD`

Run Migrations

```bash
  php artisan migrate
```

Run Database seeder to create Permissions & Roles

```bash
  php artisan db:seed
```

Start the server

```bash
  php artisan serve
```

Default Credentials for Super Admin

```bash
  superadmin@gmail.com
  superadmin
```
