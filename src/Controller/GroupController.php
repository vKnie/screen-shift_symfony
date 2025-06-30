<?php
namespace App\Controller;

use App\Entity\Group;
use App\Form\GroupForm;
use App\Repository\GroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class GroupController extends AbstractController
{
    #[Route('/group', name: 'app_group')]
    public function index(GroupRepository $groupRepository): Response
    {
        return $this->render('group/index.html.twig', [
            'groups' => $groupRepository->findAll(),
        ]);
    }

    #[Route('/group/create', name: 'create_group')]
    public function create(Request $request, EntityManagerInterface $em): Response
    {
        $group = new Group();
        $form = $this->createForm(GroupForm::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le rôle est automatiquement généré lors du setName() dans l'entité
            $em->persist($group);
            $em->flush();

            $this->addFlash('success', sprintf('Groupe "%s" créé avec le rôle "%s"', $group->getName(), $group->getRole()));
            
            return $this->redirectToRoute('app_group');
        }

        return $this->render('group/form.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/group/edit/{id}', name: 'edit_group')]
    public function edit(Group $group, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(GroupForm::class, $group);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le rôle sera automatiquement mis à jour si le nom change
            $em->flush();

            $this->addFlash('success', sprintf('Groupe "%s" modifié avec le rôle "%s"', $group->getName(), $group->getRole()));
            
            return $this->redirectToRoute('app_group');
        }

        return $this->render('group/form.html.twig', [
            'form' => $form->createView(),
            'edit' => true,
        ]);
    }

    #[Route('/group/delete/{id}', name: 'delete_group')]
    public function delete(Group $group, EntityManagerInterface $em): Response
    {
        $groupName = $group->getName();
        $em->remove($group);
        $em->flush();

        $this->addFlash('success', sprintf('Groupe "%s" supprimé', $groupName));
        
        return $this->redirectToRoute('app_group');
    }
}