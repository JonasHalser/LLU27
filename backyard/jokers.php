<?php
declare(strict_types=1);
require_once dirname($_SERVER['DOCUMENT_ROOT']).'/backyard_private/app/bootstrap.php';
requireUser();
redirect('competition.php#jokers');
