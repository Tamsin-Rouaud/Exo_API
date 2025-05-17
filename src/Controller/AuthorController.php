<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;

use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'authors', methods:['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache  ): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllAuthors-" . $page . "-" . $limit;

        $authorList = $cache->get($idCache, function(ItemInterface $item ) use ($authorRepository, $page, $limit){
            // Phrase echo pour debug sur Postman
            echo ("L'élément n'est pas encore en cache!\n");
            $item->tag('authorsCache');
            return $authorRepository->findAllPageWithPagination($page, $limit);
        });

         $context = SerializationContext::create()->setGroups(['getAuthors']);
       
        $jsonAuthorList = $serializer->serialize($authorList, 'json', $context);

        return new JsonResponse([
            $jsonAuthorList, Response::HTTP_OK, [], true
        ]);

        
    }

    #[Route('/api/authors/{id}', name: 'detail_author', methods: ['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer) {
            $context = SerializationContext::create()->setGroups(['getAuthors']);
        
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors', name:'create_author', methods:['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un autheur')]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator  ): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

         $errors = $validator->validate($author);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        $location = $urlGenerator->generate('detail_author', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' =>$location], true);
        
    }

    #[Route('/api/authors/{id}', name:'update_author', methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un auteur')]
    public function updateAuthor(Request $request, SerializerInterface $serializer, Author $currentAuthor, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache  )
    {
        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $currentAuthor->setFirstName($newAuthor->getFirstName());
        $currentAuthor->setLastName($newAuthor->getLastName());

        
         $errors = $validator->validate($currentAuthor);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        

        $em->persist($currentAuthor);
        $em->flush();

        $cache->invalidateTags(['authorsCache']);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors/{id}', name:'delete_author', methods:['DELETE'])]
     #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un auteur')]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cache ):JsonResponse
    {
        $cache->invalidateTags(['authorsCache']);
        $em->remove($author);
        $em->flush();

        return new JsonResponse( null,Response::HTTP_NO_CONTENT);
    }

}

