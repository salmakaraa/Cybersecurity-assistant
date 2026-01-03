<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/article', name: 'admin_article_')]
class AdminArticleController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(
        ArticleRepository $repo, 
        PaginatorInterface $paginator,
        Request $request
    ): Response {
        $query = $repo->createQueryBuilder('a')
            ->orderBy('a.id', 'DESC')
            ->getQuery();
        
        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1), // Current page number
            10 // Items per page
        );

        return $this->render('admin_article/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(
        Request $request, 
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        if ($request->isMethod('POST')) {
            $article = new Article();
            $article->setTitle($request->request->get('title'));
            $article->setDescription($request->request->get('description'));

            $file = $request->files->get('file');
            if ($file) {
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $article->setFilePath($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload file.');
                    return $this->render('admin_article/new.html.twig');
                }
            }

            // Set the currently logged-in admin as creator
            $article->setCreatedBy($this->getUser());

            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'Article created successfully!');
return $this->redirectToRoute('admin_dashboard', ['tab' => 'articles']);
        }

        return $this->render('admin_article/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(
        Request $request, 
        Article $article, 
        EntityManagerInterface $em,
        SluggerInterface $slugger
    ): Response {
        // Check if current user is the creator
        if ($article->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'You can only edit articles you created.');
            return $this->redirectToRoute('admin_dashboard', ['tab' => 'articles']);
        }

        if ($request->isMethod('POST')) {
            $article->setTitle($request->request->get('title'));
            $article->setDescription($request->request->get('description'));

            $file = $request->files->get('file');
            if ($file) {
                // Remove old file if exists
                $oldFile = $article->getFilePath();
                if ($oldFile) {
                    $oldFilePath = $this->getParameter('uploads_directory').'/'.$oldFile;
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$file->guessExtension();
                
                try {
                    $file->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $article->setFilePath($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload file.');
                    return $this->redirectToRoute('admin_dashboard', ['id' => $article->getId()]);
                }
            }

            $em->flush();
            
            $this->addFlash('success', 'Article updated successfully!');
return $this->redirectToRoute('admin_dashboard', ['tab' => 'articles']);
        }

        return $this->render('admin_article/edit.html.twig', [
            'article' => $article,
        ]);
    }

    #[Route('/{id}', name: 'show')]
    public function show(Article $article): Response
    {
        return $this->render('admin_article/show.html.twig', [
            'article' => $article
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request, 
        Article $article, 
        EntityManagerInterface $em
    ): Response {
        // Check if current user is the creator
        if ($article->getCreatedBy() !== $this->getUser()) {
            $this->addFlash('error', 'You can only delete articles you created.');
            return $this->redirectToRoute('admin_dashboard', ['tab' => 'articles']);

        }

        // CSRF protection
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->get('_token'))) {
            // Remove file if exists
            $filePath = $article->getFilePath();
            if ($filePath) {
                $fullPath = $this->getParameter('uploads_directory').'/'.$filePath;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            
            $em->remove($article);
            $em->flush();
            
            $this->addFlash('success', 'Article deleted successfully!');
        } else {
            $this->addFlash('error', 'Invalid security token.');
        }

        
        return $this->redirectToRoute('admin_dashboard', ['tab' => 'articles']);
    }
}