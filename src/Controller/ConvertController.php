<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConvertController extends AbstractController
{
    #[Route('/convert', name: 'app_convert')]
    public function index(): Response
    {
        return $this->render('convert/index.html.twig', [
            'controller_name' => 'Convert',
        ]);
    }
}
