<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('inventory', function () {
    return true;
});
