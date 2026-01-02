<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/user', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
class UsersController extends AbstractController
{
    private UserRepository $userRepository;
    private EntityManagerInterface $em;

    public function __construct(UserRepository $userRepository, EntityManagerInterface $em)
    {
        $this->userRepository = $userRepository;
        $this->em = $em;
    }

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        // Get all users
        $users = $this->userRepository->findAll();

        return $this->render('admin_user/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/delete/{id}', name: 'delete')]
public function delete(User $user): Response
{
    // Prevent deleting yourself
    if ($user === $this->getUser()) {
        $this->addFlash('error', 'You cannot delete yourself.');
        return $this->redirectToRoute('admin_dashboard', [
            'tab' => 'users'
        ]);
    }

    $this->em->remove($user);
    $this->em->flush();

    $this->addFlash('success', 'User deleted successfully.');

    // Redirect to /admin and force Users tab
    return $this->redirectToRoute('admin_dashboard', [
        'tab' => 'users'
    ]);
}

}
