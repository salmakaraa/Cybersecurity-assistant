<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminRegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationAdminController extends AbstractController
{
    #[Route('/create-admin', name: 'create_admin')]
    public function createAdmin(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(AdminRegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get plain password
                $plainPassword = $form->get('plainPassword')->getData();
                
                // Debug: Check if password is being set
                dump('Plain password received:', $plainPassword);
                
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
                
                // Debug: Check hashed password
                dump('Hashed password:', $hashedPassword);

                // Admin checkbox
                if ($form->get('isAdmin')->getData()) {
                    $user->setRoles(['ROLE_ADMIN']);
                } else {
                    $user->setRoles(['ROLE_USER']);
                }
                
                // Debug: Check user object before persist
                dump('User object before persist:', $user);
                dump('User email:', $user->getEmail());
                dump('User roles:', $user->getRoles());

                $entityManager->persist($user);
                $entityManager->flush();
                
                // Debug: Check if user was saved
                dump('User ID after flush:', $user->getId());

                $this->addFlash('success', 'User created successfully!');
                return $this->redirectToRoute('admin_dashboard', ['tab' => 'users']);
                
            } catch (\Exception $e) {
                // Show detailed error
                $this->addFlash('error', 'Error: ' . $e->getMessage());
                dump('Exception:', $e->getMessage());
                dump('Trace:', $e->getTraceAsString());
            }
        } elseif ($form->isSubmitted() && !$form->isValid()) {
            // Show form errors
            $errors = $form->getErrors(true);
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
                dump('Form error:', $error->getMessage());
            }
        }

        return $this->render('registration_admin/index.html.twig', [
            'form' => $form->createView(),
        ]);
           
    }
}