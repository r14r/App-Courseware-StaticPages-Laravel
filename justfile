# Courseware Laravel tasks

set shell := ["bash", "-cu"]


default:
    @just -l
    
install:
    composer install
    npm install

build:
    npm run build

run:
    composer run dev

clear-cache:
    php artisan optimize:clear

check:
    vendor/bin/pint --dirty
    php artisan test --compact
    npm run lint
