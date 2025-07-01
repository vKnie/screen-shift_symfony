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
            try {
                $em->persist($picture);
                $em->flush();
                
                $screenName = $picture->getScreenPicture() ? $picture->getScreenPicture()->getName() : 'N/A';
                $this->addFlash('success', sprintf('Image ajoutée avec succès au screen "%s".', $screenName));
                
                return $this->redirectToRoute('app_picture');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'ajout de l\'image : ' . $e->getMessage());
            }
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
            try {
                $em->flush();
                
                $screenName = $picture->getScreenPicture() ? $picture->getScreenPicture()->getName() : 'N/A';
                $this->addFlash('success', sprintf('Image modifiée avec succès pour le screen "%s".', $screenName));
                
                return $this->redirectToRoute('app_picture');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification de l\'image : ' . $e->getMessage());
            }
        }

        return $this->render('picture/form.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    #[Route('/picture/delete/{id}', name: 'delete_picture')]
    public function delete(Picture $picture, EntityManagerInterface $em, Request $request): Response
    {
        try {
            $screenName = $picture->getScreenPicture() ? $picture->getScreenPicture()->getName() : 'N/A';
            $imageName = $picture->getImageName() ?: 'Image sans nom';
            
            $em->remove($picture);
            $em->flush();
            
            $this->addFlash('success', sprintf('Image "%s" supprimée avec succès du screen "%s".', $imageName, $screenName));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression de l\'image : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_picture');
    }
}