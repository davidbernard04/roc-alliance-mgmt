<?php

// Very basic routing.

if (count($_FILES) > 0)
{
    include_once __DIR__ . '/controllers/post-screenshots.php';
}
else
{
    include_once __DIR__ . '/controllers/members.php';
}
