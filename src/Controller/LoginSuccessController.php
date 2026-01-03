<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoginSuccessController extends AbstractController
{
    #[Route('/login/success', name: 'app_login_success')]
    public function index(): Response
    {
        // This runs AFTER Symfony has fully authenticated the user
        
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('admin_dashboard');
        }
        
        // Default for regular users
        return $this->redirectToRoute('app_dashboard');
    }
}