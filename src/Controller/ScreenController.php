<?php
namespace App\Controller;
use App\Entity\Screen;
use App\Form\ScreenForm;
use App\Repository\ScreenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

final class ScreenController extends AbstractController
{
    private $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

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
            $selectedGroupe = $screen->getGroupeScreen();
            $currentUser = $this->getUser();
            
            // Vérifier si l'utilisateur a le rôle du groupe sélectionné
            if (!$this->userHasGroupeRole($currentUser, $selectedGroupe)) {
                $this->addFlash('error', 'Vous n\'avez pas les permissions pour créer un screen pour ce groupe.');
                return $this->render('screen/form.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
            
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
        // Vérifier que l'utilisateur peut modifier ce screen (il doit avoir le rôle du groupe)
        $currentUser = $this->getUser();
        if (!$this->userHasGroupeRole($currentUser, $screen->getGroupeScreen())) {
            $this->addFlash('error', 'Vous n\'avez pas les permissions pour modifier ce screen.');
            return $this->redirectToRoute('app_screen');
        }
        
        $form = $this->createForm(ScreenForm::class, $screen);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $selectedGroupe = $screen->getGroupeScreen();
            
            // Vérifier si l'utilisateur a le rôle du nouveau groupe sélectionné
            if (!$this->userHasGroupeRole($currentUser, $selectedGroupe)) {
                $this->addFlash('error', 'Vous n\'avez pas les permissions pour assigner ce screen à ce groupe.');
                return $this->render('screen/form.html.twig', [
                    'form' => $form->createView(),
                    'edit' => true,
                ]);
            }
            
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
        // Vérifier que l'utilisateur peut supprimer ce screen
        $currentUser = $this->getUser();
        if (!$this->userHasGroupeRole($currentUser, $screen->getGroupeScreen())) {
            $this->addFlash('error', 'Vous n\'avez pas les permissions pour supprimer ce screen.');
            return $this->redirectToRoute('app_screen');
        }
        
        $em->remove($screen);
        $em->flush();
        $this->addFlash('success', 'Screen supprimé avec succès.');
        return $this->redirectToRoute('app_screen');
    }

    /**
     * Vérifie si l'utilisateur a le rôle requis pour le groupe
     */
    private function userHasGroupeRole($user, $groupeScreen): bool
    {
        if (!$user || !$groupeScreen) {
            return false;
        }
        
        // Si l'utilisateur est admin, il peut tout faire
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }
        
        // Vérifier si l'utilisateur a le rôle spécifique du groupe
        $requiredRole = $groupeScreen->getRole();
        if ($requiredRole && in_array($requiredRole, $user->getRoles())) {
            return true;
        }
        
        // Alternative : vérifier si l'utilisateur appartient au groupe
        // (si vous avez une relation User <-> GroupeScreen)
        // if ($user->getGroupes() && $user->getGroupes()->contains($groupeScreen)) {
        //     return true;
        // }
        
        return false;
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

    private function generatePicturesHash(array $pictures): string
    {
        $data = [];
        
        foreach ($pictures as $picture) {
            $data[] = [
                'id' => $picture->getId(),
                'delay' => $picture->getDelay(),
                'imageName' => $picture->getImageName(),
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