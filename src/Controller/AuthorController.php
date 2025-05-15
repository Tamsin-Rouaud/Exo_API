<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthorController extends AbstractController
{
    #[Route('/api/authors', name: 'authors', methods:['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer ): JsonResponse
    {
        $authorList = $authorRepository->findAll();

        $jsonAuthorList = $serializer->serialize($authorList, 'json', ['groups'=>'getAuthors']);

        return new JsonResponse([
            $jsonAuthorList, Response::HTTP_OK, [], true
        ]);

        
    }

    #[Route('/api/authors/{id}', name: 'detail_author', methods: ['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer) {
        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, [], true);
    }

    #[Route('/api/authors', name:'create_author', methods:['POST'])]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator  ): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

         $errors = $validator->validate($author);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $em->persist($author);
        $em->flush();

        $jsonAuthor = $serializer->serialize($author, 'json', ['groups' =>'getAuthors']);

        $location = $urlGenerator->generate('detail_author', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ['Location' =>$location], true);
        
    }

    #[Route('/api/authors/{id}', name:'update_author', methods:['PUT'])]
    public function updateAuthor(Request $request, SerializerInterface $serializer, Author $currentAuthor, EntityManagerInterface $em, ValidatorInterface $validator )
    {
        $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE=>$currentAuthor]);

         $errors = $validator->validate($updatedAuthor);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $em->persist($updatedAuthor);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/authors/{id}', name:'delete_author', methods:['DELETE'])]
    public function deleteAuthor(Author $author, EntityManagerInterface $em):JsonResponse
    {
        $em->remove($author);
        $em->flush();

        return new JsonResponse( null,Response::HTTP_NO_CONTENT);
    }

}

