<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\UpdateGaransiBarang;
use Illuminate\Support\Facades\Schedule;

Schedule::command(UpdateGaransiBarang::class)->daily();
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
