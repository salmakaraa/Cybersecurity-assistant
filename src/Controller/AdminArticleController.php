<?php

namespace App\Controller;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/article', name: 'admin_article_')]
class AdminArticleController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ArticleRepository $repo): Response
    {
        return $this->render('admin_article/index.html.twig', [
            'articles' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $article = new Article();
            $article->setTitle($request->request->get('title'));
            $article->setDescription($request->request->get('description'));

            $file = $request->files->get('file');
            if ($file) {
                $newFilename = uniqid().'.'.$file->guessExtension();

                try {
                    $file->move($this->getParameter('uploads_directory'), $newFilename);
                    $article->setFilePath($newFilename);
                } catch (FileException $e) {
                    throw $e;
                }
            }

            // ✅ Set the currently logged-in admin as creator
            $article->setCreatedBy($this->getUser());

            $em->persist($article);
            $em->flush();

            return $this->redirectToRoute('admin_article_index');
        }

        return $this->render('admin_article/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'edit')]
    public function edit(Request $request, Article $article, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $article->setTitle($request->request->get('title'));
            $article->setDescription($request->request->get('description'));

            $file = $request->files->get('file');
            if ($file) {
                $newFilename = uniqid().'.'.$file->guessExtension();
                $file->move($this->getParameter('uploads_directory'), $newFilename);
                $article->setFilePath($newFilename);
            }

            $em->flush();
            return $this->redirectToRoute('admin_article_index');
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

    #[Route('/{id}/delete', name: 'delete')]
    public function delete(Article $article, EntityManagerInterface $em): Response
    {
        $em->remove($article);
        $em->flush();

        return $this->redirectToRoute('admin_article_index');
    }
}
