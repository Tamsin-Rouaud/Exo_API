<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class BookController extends AbstractController
{
    #[Route('/api/books', name: 'books', methods:['GET'])]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache  ): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;

        $bookList = $cache->get($idCache, function(ItemInterface $item ) use ($bookRepository,$page, $limit) {
            // Phrase echo pour debug sur Postman
            echo ("L'élément n'est pas encore en cache!\n");
            $item->tag('booksCache');
            return $bookRepository->findAllPageWithPagination($page, $limit);

        });

        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBookList = $serializer->serialize($bookList, 'json', $context);

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    #[Route('/api/books/{id}', name: 'detail_book', methods:['GET'])]
    public function getDetailBook(int $id, Book $book , SerializerInterface $serializer ): JsonResponse
    {
         $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
        }

     #[Route('/api/books/{id}', name: 'delete_book', methods:['DELETE'])]
     #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un livre')]
    public function deleteBook(Book $book , EntityManagerInterface $em, TagAwareCacheInterface $cache  ): JsonResponse
    {
        $cache->invalidateTags(['booksCache']);
        $em->remove($book);
        $em->flush();

        return new JsonResponse( null,Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/books', name:"create_book", methods:['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un livre')]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, AuthorRepository $authorRepository ,UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator ): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        
        $errors = $validator->validate($book);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }
      
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        
        $em->persist($book);
        $em->flush(); 

        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('detail_book', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL );

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" =>$location], true);
    }

    #[Route('/api/books/{id}', name:"update_book", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache  )
    {

        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

         $errors = $validator->validate($currentBook);
        if($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors,'json'), Response::HTTP_BAD_REQUEST, [],true);
        }

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $currentBook->setAuthor($authorRepository->find($idAuthor));

        
        $em->persist($currentBook);
        $em->flush();

         $cache->invalidateTags(['booksCache']);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
        
}
