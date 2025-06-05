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
    
    #[Route('/screen/{id}', name: 'screen_show', requirements: ['id' => '\d+'])]
    public function show(int $id, ScreenRepository $screenRepository): Response
    {
        $screen = $screenRepository->find($id);
       
        if (!$screen) {
            throw $this->createNotFoundException('Screen non trouvé');
        }

        // Filtrer les pictures actives (entre startDate et endDate)
        $activePictures = $this->getActivePictures($screen);

        // Stocker le timestamp et l'ID du screen en session pour le polling
        $session = $this->requestStack->getSession();
        $session->set('last_check_screen_' . $id, time());
        $session->set('current_screen_id', $id);
       
        return $this->render('screen/show.html.twig', [
            'screen' => $screen,
            'pictures' => $activePictures,
        ]);
    }

    #[Route('/screen/{id}/check-updates', name: 'screen_check_updates', requirements: ['id' => '\d+'])]
    public function checkUpdates(int $id, ScreenRepository $screenRepository): JsonResponse
    {
        $session = $this->requestStack->getSession();
        $lastCheck = $session->get('last_check_screen_' . $id, 0);
        
        $screen = $screenRepository->find($id);
        if (!$screen) {
            return new JsonResponse(['hasUpdates' => false]);
        }
        
        // Vérifier s'il y a eu des changements depuis la dernière vérification
        $hasUpdates = $this->hasNewPicturesForScreen($screen, $lastCheck);
        
        if ($hasUpdates) {
            $session->set('last_check_screen_' . $id, time());
        }

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

    private function hasNewPicturesForScreen(Screen $screen, int $lastCheck): bool
    {
        // Vérifier s'il y a de nouvelles pictures pour ce screen depuis $lastCheck
        // Ou si les dates d'activation ont changé
        $count = 0;
        
        foreach ($screen->getPictures() as $picture) {
            // Vérifier si l'image a été modifiée
            if ($picture->getUpdatedAt() && $picture->getUpdatedAt()->getTimestamp() > $lastCheck) {
                $count++;
            }
            
            // Ou si une image devient active maintenant
            $now = new \DateTime();
            if ($picture->getStartDate() && 
                $picture->getStartDate()->getTimestamp() > $lastCheck && 
                $picture->getStartDate()->getTimestamp() <= $now->getTimestamp()) {
                $count++;
            }
        }

        return $count > 0;
    }
}