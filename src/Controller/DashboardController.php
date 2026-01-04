<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Favorite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\CodeScanChat;

class DashboardController extends AbstractController
{
    #[Route('/app_dashboard', name: 'app_dashboard')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $tab = $request->query->get('tab', 'articles'); // default to 'articles'
        // Search functionality
        $search = $request->query->get('search', '');
        $articleRepo = $em->getRepository(Article::class);

        $articles = $articleRepo->createQueryBuilder('a')
            ->where('a.title LIKE :search OR a.description LIKE :search')
            ->setParameter('search', "%$search%")
            ->orderBy('a.id', 'DESC') // latest first
            ->getQuery()
            ->getResult();

        // Load favorites
        $favorites = $user
            ? $em->getRepository(Favorite::class)->findBy(['user' => $user])
            : [];

        // Create an array of favorite article IDs for easy lookup in Twig
        $favoriteIds = array_map(fn($f) => $f->getArticle()->getId(), $favorites);

        // === 3. Get code scan chats ===
        $chats = [];
        if ($user) {
            // Try to get chats from CodeScanChat entity
            try {
                $chatRepo = $em->getRepository(CodeScanChat::class);
                $chats = $chatRepo->createQueryBuilder('c')
                    ->where('c.user = :user')
                    ->setParameter('user', $user)
                    ->orderBy('c.updatedAt', 'DESC')
                    ->setMaxResults(10) // Limit to 10 most recent
                    ->getQuery()
                    ->getResult();
            } catch (\Exception $e) {
                // If CodeScanChat doesn't exist yet, use empty array
                $chats = [];
            }
        }
        return $this->render('dashboard/index.html.twig', [
            'active_tab' => $tab,
            'articles' => $articles,
            'favorites' => $favorites,
            'favoriteIds' => $favoriteIds,
            'search' => $search,
            'chats' => $chats,
        ]);
    }

    #[Route('/dashboard/favorite/{id}', name: 'dashboard_add_favorite')]
    public function addFavorite(Article $article, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user) {
            $existing = $em->getRepository(Favorite::class)
                ->findOneBy(['user' => $user, 'article' => $article]);

            if (!$existing) {
                $favorite = new Favorite();
                $favorite->setUser($user)->setArticle($article);
                $em->persist($favorite);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/favorite/remove/{id}', name: 'dashboard_remove_favorite')]
    public function removeFavorite(Article $article, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if ($user) {
            $favorite = $em->getRepository(Favorite::class)
                ->findOneBy(['user' => $user, 'article' => $article]);
            if ($favorite) {
                $em->remove($favorite);
                $em->flush();
            }
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
