<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Tests\App\Controller;

use Symfony\Component\HttpFoundation\Response;

class TestController
{
    public function home(): Response
    {
        return new Response('<h1>Home</h1>');
    }

    public function about(): Response
    {
        return new Response('<h1>About</h1>');
    }

    public function contact(): Response
    {
        return new Response('<h1>Contact</h1>');
    }

    public function song(string $uid): Response
    {
        return new Response('<h1>Song: ' . \htmlspecialchars($uid) . '</h1>');
    }

    public function article(string $slug): Response
    {
        return new Response('<h1>Article: ' . \htmlspecialchars($slug) . '</h1>');
    }
}
