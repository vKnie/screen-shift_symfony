<?php

namespace App\Controller;

use App\Entity\Screen;
use App\Entity\Group;
use App\Entity\User;
use App\Form\ScreenForm;
use App\Repository\ScreenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class ScreenController extends AbstractController
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    #[IsGranted('ROLE_ACCESS')]
    #[Route('/screen', name: 'app_screen')]
    public function index(ScreenRepository $screenRepository): Response
    {
        return $this->render('screen/index.html.twig', [
            'screens' => $screenRepository->findAll(),
        ]);
    }

    #[IsGranted('ROLE_ACCESS')]
    #[Route('/screen/create', name: 'create_screen')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $screen = new Screen();
        $form = $this->createForm(ScreenForm::class, $screen);
        $form->handleRequest($request);
       
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $selectedGroupe = $screen->getGroupeScreen();
                $currentUser = $this->getUser();
               
                // Vérifier si l'utilisateur a le rôle du groupe sélectionné
                if (!$this->userHasGroupeRole($currentUser, $selectedGroupe)) {
                    $this->addFlash('error', sprintf('Vous n\'avez pas les permissions pour créer un screen pour le groupe "%s". Rôle requis : %s', 
                        $selectedGroupe->getName(), 
                        $selectedGroupe->getRole()
                    ));
                    return $this->render('screen/form.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }
               
                $em->persist($screen);
                $em->flush();
                $this->addFlash('success', sprintf('Screen "%s" créé avec succès pour le groupe "%s".', 
                    $screen->getName(), 
                    $selectedGroupe->getName()
                ));
                return $this->redirectToRoute('app_screen');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création du screen : ' . $e->getMessage());
            }
        }
       
        return $this->render('screen/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[IsGranted('ROLE_ACCESS')]
    #[Route('/screen/edit/{id}', name: 'edit_screen')]
    public function edit(Screen $screen, Request $request, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur peut modifier ce screen (il doit avoir le rôle du groupe)
        $currentUser = $this->getUser();
        if (!$this->userHasGroupeRole($currentUser, $screen->getGroupeScreen())) {
            $this->addFlash('error', sprintf('Vous n\'avez pas les permissions pour modifier ce screen. Rôle requis : %s', 
                $screen->getGroupeScreen()->getRole()
            ));
            return $this->redirectToRoute('app_screen');
        }
       
        $form = $this->createForm(ScreenForm::class, $screen);
        $form->handleRequest($request);
       
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $selectedGroupe = $screen->getGroupeScreen();
               
                // Vérifier si l'utilisateur a le rôle du nouveau groupe sélectionné
                if (!$this->userHasGroupeRole($currentUser, $selectedGroupe)) {
                    $this->addFlash('error', sprintf('Vous n\'avez pas les permissions pour assigner ce screen au groupe "%s". Rôle requis : %s', 
                        $selectedGroupe->getName(), 
                        $selectedGroupe->getRole()
                    ));
                    return $this->render('screen/form.html.twig', [
                        'form' => $form->createView(),
                        'edit' => true,
                    ]);
                }
               
                $em->flush();
                $this->addFlash('success', sprintf('Screen "%s" modifié avec succès.', $screen->getName()));
                return $this->redirectToRoute('app_screen');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification du screen : ' . $e->getMessage());
            }
        }
       
        return $this->render('screen/form.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    #[IsGranted('ROLE_ACCESS')]
    #[Route('/screen/delete/{id}', name: 'delete_screen')]
    public function delete(Screen $screen, EntityManagerInterface $em): Response
    {
        // Vérifier que l'utilisateur peut supprimer ce screen
        $currentUser = $this->getUser();
        if (!$this->userHasGroupeRole($currentUser, $screen->getGroupeScreen())) {
            $this->addFlash('error', sprintf('Vous n\'avez pas les permissions pour supprimer ce screen. Rôle requis : %s', 
                $screen->getGroupeScreen()->getRole()
            ));
            return $this->redirectToRoute('app_screen');
        }

        try {
            $screenName = $screen->getName();
            $groupName = $screen->getGroupeScreen()->getName();
            
            $em->remove($screen);
            $em->flush();
            
            $this->addFlash('success', sprintf('Screen "%s" du groupe "%s" supprimé avec succès.', $screenName, $groupName));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression du screen : ' . $e->getMessage());
        }
        
        return $this->redirectToRoute('app_screen');
    }

    /**
     * Vérifie si l'utilisateur a le rôle correspondant au groupe
     * ou s'il est administrateur
     */
    private function userHasGroupeRole(?User $user, ?Group $group): bool
    {
        if (!$user || !$group) {
            return false;
        }

        // Les administrateurs ont accès à tout
        if ($this->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Vérifier si l'utilisateur a le rôle spécifique du groupe
        return $this->isGranted($group->getRole());
    }
    
    #[Route('/screen/{id}', name: 'screen_show', requirements: ['id' => '\d+'])]
    public function show(int $id, ScreenRepository $screenRepository): Response
    {
        $screen = $screenRepository->find($id);
       
        if (!$screen) {
            throw $this->createNotFoundException('Screen non trouvé');
        }

        // Filtrer les pictures actives (entre startDate et endDate)
        $activePictures = $this->getActivePictures($screen);

        // Stocker le hash initial des images
        $session = $this->requestStack->getSession();
        $session->set('last_pictures_hash_' . $id, $this->generatePicturesHash($activePictures));
       
        return $this->render('screen/show.html.twig', [
            'screen' => $screen,
            'pictures' => $activePictures,
        ]);
    }

    #[Route('/screen/{id}/check-updates', name: 'screen_check_updates', requirements: ['id' => '\d+'])]
    public function checkUpdates(int $id, ScreenRepository $screenRepository): JsonResponse
    {
        $session = $this->requestStack->getSession();
        
        $screen = $screenRepository->find($id);
        if (!$screen) {
            return new JsonResponse(['hasUpdates' => false]);
        }
        
        // Récupérer les images actuellement actives
        $currentActivePictures = $this->getActivePictures($screen);
        
        // Créer un hash des données importantes pour détecter les changements
        $currentHash = $this->generatePicturesHash($currentActivePictures);
        
        // Récupérer le hash précédent
        $lastHash = $session->get('last_pictures_hash_' . $id, '');
        
        // S'il y a un changement dans le hash, il faut mettre à jour
        $hasUpdates = ($currentHash !== $lastHash);
        
        if ($hasUpdates) {
            $session->set('last_pictures_hash_' . $id, $currentHash);
        }

        // Pour debug
        error_log("Screen $id - Current hash: $currentHash, Last hash: $lastHash, Updates: " . ($hasUpdates ? 'YES' : 'NO'));

        return new JsonResponse(['hasUpdates' => $hasUpdates]);
    }

    #[Route('/screen/{id}/get-slides', name: 'screen_get_slides', requirements: ['id' => '\d+'])]
    public function getSlides(int $id, ScreenRepository $screenRepository): Response
    {
        $screen = $screenRepository->find($id);
        
        if (!$screen) {
            throw $this->createNotFoundException('Screen non trouvé');
        }
        
        // Filtrer les pictures actives
        $activePictures = $this->getActivePictures($screen);
        
        return $this->render('screen/_slides.html.twig', [
            'pictures' => $activePictures,
        ]);
    }

    private function getActivePictures(Screen $screen): array
    {
        $now = new \DateTime();
        $activePictures = [];
        
        foreach ($screen->getPictures() as $picture) {
            // Vérifier si l'image est dans sa période d'affichage
            if ($picture->getStartDate() && $picture->getEndDate()) {
                if ($now >= $picture->getStartDate() && $now <= $picture->getEndDate()) {
                    $activePictures[] = $picture;
                }
            } else {
                // Si pas de dates définies, considérer comme active
                $activePictures[] = $picture;
            }
        }
        
        return $activePictures;
    }

    // MÉTHODE MODIFIÉE AVEC LA POSITION
    private function generatePicturesHash(array $pictures): string
    {
        $data = [];
        
        foreach ($pictures as $picture) {
            $data[] = [
                'id' => $picture->getId(),
                'delay' => $picture->getDelay(),
                'imageName' => $picture->getImageName(),
                'position' => $picture->getPosition(), // POSITION AJOUTÉE
                'backgroundColor' => $picture->getBackgroundColor(), // COULEUR DE FOND AJOUTÉE
                'startDate' => $picture->getStartDate() ? $picture->getStartDate()->format('Y-m-d H:i:s') : null,
                'endDate' => $picture->getEndDate() ? $picture->getEndDate()->format('Y-m-d H:i:s') : null,
                'updatedAt' => $picture->getUpdatedAt() ? $picture->getUpdatedAt()->format('Y-m-d H:i:s') : null,
            ];
        }
        
        return md5(json_encode($data));
    }

    private function hasNewPicturesForScreen(Screen $screen, int $lastCheck): bool
    {
        // Cette méthode n'est plus utilisée avec le système de hash
        // Mais on peut la garder au cas où
        $count = 0;
        
        foreach ($screen->getPictures() as $picture) {
            if ($picture->getUpdatedAt() && $picture->getUpdatedAt()->getTimestamp() > $lastCheck) {
                $count++;
            }
        }

        return $count > 0;
    }
}