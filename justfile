default:
	@just -l


migrate:
	@php artisan migrate --force

run:
	@composer run dev
