<?php

use Predis\Client as PredisClient;

return new PredisClient([
    'scheme'   => 'tcp',
    'host'     => 'redis',
    'port'     => 6379,
    'database' => 0,
]);
