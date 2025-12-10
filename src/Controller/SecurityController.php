<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If the user is already logged in, redirect based on role
        $user = $this->getUser();
        if ($user) {
            // Redirect admin users to admin dashboard
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('admin_dashboard');
            }
            // Redirect regular users to user dashboard
            if ($this->isGranted('ROLE_USER')) {
                return $this->redirectToRoute('dashboard');
            }
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony will intercept this route automatically
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
