<p align="center">
    <a href="https://github.com/NativeAmericanTours/tour-backend/blob/main/README.md">
        <img src="public/images/global/logo.jpeg" alt="NativeAmeticanTours Logo" width="350">
    </a>
</p>

---
## Introduction

A Booking Software Company That Promotes Native American Tourism. 

https://nativeamericantours.com

## Description

Our multi-tenant web application provides a flexible and scalable solution for managing multiple tenants (clients) within a single instance of the application. This architecture allows us to serve multiple clients while ensuring data isolation and customization based on their unique requirements.

## Requirements, recommended environment
- Laravel 10.0+
- PHP 8.2+
- Nginx OR Apache 2
- MySQL
- Laravel Herd OR Valet
- PHP Imagick Extension
- Mailtrap Account
- Adminer (To manage database)

## Installation

First clone this repository, install the dependencies, and setup your .env file.

```bash
git clone git@github.com:NativeAmericanTours/tour-backend.git
```
Fetch all branch from github and checkout in development branch
```bash
git branch --all

git checkout development
```
Then, install laravel dependencies
```bash
composer install
```
Then, copy the `.env.example` to `.env`
```bash
cp .env.example .env
```
## Environment variables
***Note*** : You can quickly set the database information and other variables in this file and have the application fully working.

```bash
APP_NAME=NativeAmericanTours
APP_ENV=local
APP_DEBUG=true
APP_URL=https://tour-backend.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tour-backend
DB_USERNAME= # YOUR_DB_USERNAME
DB_PASSWORD= # YOUR_DB_PASSWORD

BROADCAST_DRIVER=pusher
CACHE_DRIVER=database
FILESYSTEM_DRIVER=s3
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

MAIL_DRIVER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME= # YOUR_MAILTRAP_INBOX_USERNAME
MAIL_PASSWORD= # YOUR_MAILTRAP_INBOX_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_FROM_NAME="${APP_NAME}"
MAIL_FROM_ADDRESS=from@example.com

#AWS S3
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=replace-me
AWS_SECRET_ACCESS_KEY=replace-me
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=nat-admin
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_URL=https://nat-admin.s3.us-east-1.amazonaws.com

#AWS Cloud Watch
DISABLE_CLOUDWATCH_LOG=false
CLOUDWATCH_LOG_NAME=nat-admin
CLOUDWATCH_LOG_REGION=us-east-1
CLOUDWATCH_LOG_KEY=replace-me
CLOUDWATCH_LOG_SECRET=replace-me
CLOUDWATCH_LOG_STREAM_NAME=nat-admin-staging
CLOUDWATCH_LOG_RETENTION_DAYS=30
CLOUDWATCH_LOG_GROUP_NAME=nat-admin-applog
CLOUDWATCH_LOG_VERSION=latest

PUSHER_APP_ID=1288983
PUSHER_APP_KEY=2b1a9e82b93bf8f6bc12
PUSHER_APP_SECRET=ff93dbb15acfa5d06538
PUSHER_APP_CLUSTER=ap2

# for resource/js/app.js
# need to define below values with 'MIX_' prefix in their name
# laravel-mix sets values in file only if the config settings' names have 'MIX_' prefix
MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

STRIPE_WEBHOOK_SECRET= # Add stripe webhook secret key
STRIPE_API_PUBLIC_KEY= # Add stripe API public secret key
STRIPE_API_SECRET_KEY=replace-me
```
Generate a new application key

```bash
php artisan key:generate
```
Then create storage link
```bash
php artisan storage:link
```

Then create the necessary database.

```bash
php artisan db
create database tour-backend
exit
```
And run the initial migrations and seeders.

```bash
php artisan migrate --seed
```
## Install packages
Install the node dependencies
```bash
npm install
```
For development build CSS and JS assets
```bash
npm run dev
```
For production, if you prefer minified files
```bash
npm run prod
```
If different service is used than Pusher for events broadcasting then need to update corresponding variables in **`resources/js/pusher.js`**

## Run

Start the local development server by running the following command:
```bash
php artisan serve
```
Once the server is running, you can access it at http://localhost:8000

For optimal development experience, it is recommended to use Herd or Valet. If you are using Herd or Valet, you can directly access the server at https://tour-backend.test

## Further Ideas

To start with queue and jobs
```bash
php artisan queue:work
```
To enable debugger then add in .env
```bash
DEBUGBAR_ENABLED=TRUE
```
To create new tenant
> Navigate to the central domain and locate the Tenants module. Create a new tenant and ensure to start the queue. Upon creation of the tenant, the system will automatically generate the corresponding database and populate it with basic mock data. You can then access the newly created tenant directly via its subdomain, for example: https://test.tour-backend.test.


> To learn more about the multi-tenant process that we are utilizing, please refer to the official documentation: [Tenancy for Laravel](https://tenancyforlaravel.com/docs/v3/introduction/)


---
## Local Docker Env:
you can run this app in a container on you local. 
#### Add the seed dbs to the 
- copy the required starter DBs
- files must be in tar zip format **.gz** 
- Required db files:
```
.docker-local/data/nat_central.sql.gz
.docker-local/data/tenant_apm.sql.gz
.docker-local/data/tenant_dixies.sql.gz
.docker-local/data/tenant_kens.sql.gz
```
- save the files in "docker-local/data"
- run the docker build command: ``` ./docker-local up-build```
- note: the 1st build may take some time depending on the size of the seed dbs.
- Make sure these are in your /etc/hosts file:. 
```
127.0.0.1 tour-backend.local
127.0.0.1 kens.tour-backend.local
127.0.0.1 dixies.tour-backend.local
127.0.0.1 apm.tour-backend.local
```
- Access the application: 
https://kens.tour-backend.local:8443/

#### Run the docker local helper. 
``` ./docker-local help```
#### Run tests:
``` ./docker-local test```
#### Run a single test
``` ./docker-local test test/path/to/test.php```
####  Manually load data into the container:   
```
docker exec -i nat-mysql mysql -uroot -proot_password nat_central < <(gunzip -c .docker-local/data/nat_central.sql.gz)
```
#### Manually run a migration:
``` 
docker exec nat-app php artisan tenants:run migrate:status --tenants=dixies
```

---

## Contributors ✨
Thanks go to these wonderful people:

[//]: contributor-faces
<a href="https://github.com/dekts"><img src="https://avatars.githubusercontent.com/u/31060232?v=4" title="dekts" width="50" height="50"></a>
<a href="https://github.com/Wyatt-NAT"><img src="https://avatars.githubusercontent.com/u/106847077?v=4" title="Wyatt-NAT" width="50" height="50"></a>
<a href="https://github.com/Jayglorywebs"><img src="https://avatars.githubusercontent.com/u/33888243?v=4" title="Jayglorywebs" width="50" height="50"></a>
<a href="https://github.com/ketan-glorywebs"><img src="https://avatars.githubusercontent.com/u/110371677?v=4" title="ketan-glorywebs" width="50" height="50"></a>
<a href="https://github.com/scottadean"><img src="https://avatars.githubusercontent.com/u/127870431?v=4" title="scottadean" width="50" height="50"></a>
<a href="https://github.com/sumit-glorywebs"><img src="https://avatars.githubusercontent.com/u/85162224?v=4" title="sumit-glorywebs" width="50" height="50"></a>
<a href="https://github.com/avni-glorywebs"><img src="https://avatars.githubusercontent.com/u/108541825?v=4" title="avni-glorywebs" width="50" height="50"></a>
<a href="https://github.com/dimpal-glorywebs"><img src="https://avatars.githubusercontent.com/u/159108910?v=4" title="dimpal-glorywebs" width="50" height="50"></a>
<a href="https://github.com/nilam-gloryweb"><img src="https://avatars.githubusercontent.com/u/159113152?v=4" title="nilam-gloryweb" width="50" height="50"></a>
<a href="https://github.com/pdupris-nat"><img src="https://avatars.githubusercontent.com/u/157048735?v=4" title="pdupris-nat" width="50" height="50"></a>

---
