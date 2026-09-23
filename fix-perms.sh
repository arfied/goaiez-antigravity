#!/bin/bash
sudo chown -R goaiez:goaiez app/app/Modules/
cd app && php artisan module:scaffold
