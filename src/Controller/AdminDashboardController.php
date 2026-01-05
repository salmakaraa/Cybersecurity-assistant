<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\ArticleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin_dashboard', name: 'admin_dashboard')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(
        ArticleRepository $articleRepository,
        UserRepository $userRepository,
        PaginatorInterface $paginator,
        Request $request,
        EntityManagerInterface $em // <-- Added this
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // FIX: Ensure the profile exists so Twig doesn't find a "null" variable
        if ($user && !$user->getUserProfile()) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $em->persist($profile);
            $em->flush();
        }

        $articlesQuery = $articleRepository->createQueryBuilder('a')
            ->orderBy('a.id', 'DESC')
            ->getQuery();

        $articlesPagination = $paginator->paginate(
            $articlesQuery,
            $request->query->getInt('page', 1),
            10
        );

        $users = $userRepository->findAll();
        $activeTab = $request->query->get('tab', 'welcome');

        // Handle AJAX requests for tab switching
        if ($request->isXmlHttpRequest()) {
            $tab = $request->query->get('tab');
            if ($tab === 'articles') {
                return $this->render('admin_article/_list.html.twig', ['pagination' => $articlesPagination]);
            } elseif ($tab === 'users') {
                return $this->render('admin_user/_list.html.twig', ['users' => $users]);
            }
        }

        return $this->render('admin_dashboard/layout.html.twig', [
            'pagination' => $articlesPagination,
            'users' => $users,
            'activeTab' => $activeTab,
        ]);
    }

    #[Route('/admin/profile/update', name: 'admin_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request, 
        EntityManagerInterface $em, 
        SluggerInterface $slugger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            throw $this->createAccessDeniedException('Log in required.');
        }

        $profile = $user->getUserProfile();
        if (!$profile) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $em->persist($profile);
        }

        $profile->setFirstName($request->request->get('firstName'));
        $profile->setLastName($request->request->get('lastName'));
        $profile->setBio($request->request->get('bio'));

        $avatarFile = $request->files->get('avatar');
        if ($avatarFile) {
            $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

            try {
                $avatarFile->move($this->getParameter('avatars_directory'), $newFilename);
                if ($profile->getAvatar()) {
                    $oldPath = $this->getParameter('avatars_directory').'/'.$profile->getAvatar();
                    if (file_exists($oldPath)) { unlink($oldPath); }
                }
                $profile->setAvatar($newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Upload failed.');
            }
        }

        $em->flush();
        $this->addFlash('success', 'Profile updated!');

        return $this->redirectToRoute('admin_dashboard', ['tab' => 'profile']);
    }
}