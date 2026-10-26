<?php
declare(strict_types=1);
require_once __DIR__.'/partials/private-path.php';

require_once lluPrivatePath('app/bootstrap.php');
requireUser();
redirect('competition.php#challenge');
