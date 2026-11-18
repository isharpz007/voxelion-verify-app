<?php

use Illuminate\Console\Scheduling\Schedule;

app(Schedule::class)->command('voxelion:prune')->daily();
