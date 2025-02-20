<?php

function adminsIds(): array
{
    $dotenv = Dotenv\Dotenv::createImmutable('../');
    $dotenv->load();

    return array_map('intval', explode(',', $_ENV['ADMIN_IDS']));
}
