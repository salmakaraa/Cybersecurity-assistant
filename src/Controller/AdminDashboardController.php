<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin_dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        ArticleRepository $articleRepository,
        UserRepository $userRepository,
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        // Query for articles pagination
        $articlesQuery = $articleRepository->createQueryBuilder('a')
            ->orderBy('a.id', 'DESC')
            ->getQuery();

        $articlesPagination = $paginator->paginate(
            $articlesQuery,
            $request->query->getInt('page', 1),
            10
        );

        // Get all users for the users section
        $users = $userRepository->findAll();

        // Get active tab from request or default to 'welcome'
        $activeTab = $request->query->get('tab', 'welcome');

        // Check if it's an AJAX request for specific tab
        if ($request->isXmlHttpRequest()) {
            $tab = $request->query->get('tab');
            
            if ($tab === 'articles') {
                return $this->render('admin_article/_list.html.twig', [
                    'pagination' => $articlesPagination,
                ]);
            } elseif ($tab === 'users') {
                return $this->render('admin_user/_list.html.twig', [
                    'users' => $users,
                ]);
            }
        }

        return $this->render('admin_dashboard/layout.html.twig', [
            'pagination' => $articlesPagination,
            'users' => $users,
            'activeTab' => $activeTab,
        ]);
    }
}