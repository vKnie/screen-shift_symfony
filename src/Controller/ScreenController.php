<?php

namespace App\Controller;

use App\Entity\Screen;
use App\Form\ScreenForm;
use App\Repository\ScreenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ScreenController extends AbstractController
{
    #[Route('/screen', name: 'app_screen')]
    public function index(ScreenRepository $screenRepository): Response
    {
        return $this->render('screen/index.html.twig', [
            'screens' => $screenRepository->findAll(),
        ]);
    }

    #[Route('/screen/create', name: 'create_screen')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $screen = new Screen();
        $form = $this->createForm(ScreenForm::class, $screen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($screen);
            $em->flush();

            $this->addFlash('success', 'Screen créé avec succès.');
            return $this->redirectToRoute('app_screen');
        }

        return $this->render('screen/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/screen/edit/{id}', name: 'edit_screen')]
    public function edit(Screen $screen, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ScreenForm::class, $screen);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Screen modifié avec succès.');
            return $this->redirectToRoute('app_screen');
        }

        return $this->render('screen/form.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    #[Route('/screen/delete/{id}', name: 'delete_screen')]
    public function delete(Screen $screen, EntityManagerInterface $em): Response
    {
        $em->remove($screen);
        $em->flush();

        return $this->redirectToRoute('app_screen');
    }
}
