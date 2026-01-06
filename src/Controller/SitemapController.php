<?php

declare(strict_types=1);

namespace Ecourty\SitemapBundle\Controller;

use Ecourty\SitemapBundle\Contract\SitemapGeneratorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SitemapController
{
    #[Route('/sitemap.xml', name: 'sitemap.index', methods: [Request::METHOD_GET])]
    public function index(SitemapGeneratorInterface $generator): Response
    {
        $xml = $generator->generate();

        return new Response($xml, Response::HTTP_OK, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
