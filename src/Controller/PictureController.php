<?php

namespace App\Controller;

use App\Entity\Picture;
use App\Form\PictureForm;
use App\Repository\PictureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PictureController extends AbstractController
{
    #[Route('/picture', name: 'app_picture')]
    public function index(PictureRepository $pictureRepository): Response
    {
        return $this->render('picture/index.html.twig', [
            'pictures' => $pictureRepository->findAll(),
        ]);
    }

    #[Route('/picture/create', name: 'create_picture')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $picture = new Picture();
        $form = $this->createForm(PictureForm::class, $picture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($picture);
            $em->flush();

            $this->addFlash('success', 'Image ajoutée avec succès.');
            return $this->redirectToRoute('app_picture');
        }

        return $this->render('picture/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/picture/edit/{id}', name: 'edit_picture')]
    public function edit(Picture $picture, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PictureForm::class, $picture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Image modifiée avec succès.');
            return $this->redirectToRoute('app_picture');
        }

        return $this->render('picture/form.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    #[Route('/picture/delete/{id}', name: 'delete_picture')]
    public function delete(Picture $picture, EntityManagerInterface $em, Request $request): Response
    {
        $em->remove($picture);
        $em->flush();

        return $this->redirectToRoute('app_picture');
    }
}
