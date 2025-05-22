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
            $em->persist($group);
            $em->flush();

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
            $em->flush();
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
        $em->remove($group);
        $em->flush();

        return $this->redirectToRoute('app_group');
    }

}
