<?php
declare(strict_types=1);

/**
 * Resolve private app files in either layout:
 * local: project/backyard + project/backyard_private
 * Hostinger: domain/public_html/backyard + domain/backyard_private
 */
function lluPrivatePath(string $relative): string {
    if($relative==='' || str_starts_with($relative,'/') || preg_match('~(?:^|/)\.\.(?:/|$)~',$relative)) {
        throw new InvalidArgumentException('Invalid private application path.');
    }

    $candidates=[];
    $documentRoot=rtrim((string)($_SERVER['DOCUMENT_ROOT']??''),DIRECTORY_SEPARATOR);
    if($documentRoot!=='') $candidates[]=dirname($documentRoot).'/backyard_private';
    $candidates[]=dirname(__DIR__,2).'/backyard_private';
    $candidates[]=dirname(__DIR__,3).'/backyard_private';

    foreach(array_unique($candidates) as $base) {
        $path=$base.'/'.$relative;
        if(is_file($path)) return $path;
    }

    throw new RuntimeException('Private application file could not be located.');
}
