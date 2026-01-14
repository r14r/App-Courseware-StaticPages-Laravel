default:
	cat justfile

allow:
	@direnv allow
	@direnv allow
	@direnv reload


install-php:
	@pnpm     install

install-composer:
	@composer install

install: install-php install-composer

setup-env:
	@laravel-setup-folder

build:
	@pnpm run build

watch: dev

dev:
	@pnpm run dev

serve *ARGS:
	@php artisan serve --host 0.0.0.0 {{ARGS}}

run: serve

run-backend: serve

run-frontend:
	@pnpm run dev --host 0.0.0.0

migrate:
	@php artisan migrate

